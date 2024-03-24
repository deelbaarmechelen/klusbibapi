<?php

namespace Api\Delivery;

use Api\Mail\MailManager;
use Api\Model\DeliveryItem;
use Api\Model\DeliveryType;
use Api\Model\InventoryItem;
use Api\Model\Lending;
use Api\Model\Reservation;
use Api\Model\Contact;
use Api\Util\HttpResponseCode;
use Api\Validator\DeliveryValidator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\DeliveryMapper;
use Api\Authorisation;
use Api\AccessType;
use Api\Model\Delivery;
use Api\Model\DeliveryState;
use DateInterval;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;

class DeliveryController 
{
    protected $logger;
    protected $token;
    protected $mailManager;

    public function __construct($logger, $token, MailManager $mailManager = null) {
        $this->logger = $logger;
        $this->token = $token;
        $this->mailManager = $mailManager;
    }

    public function getAll(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib GET '/deliveries' route");

        try {
           Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.list"]);
        } catch (ForbiddenException $ex) {
           return $response->withStatus($ex->getCode()); // Unauthorized
        }
        parse_str($request->getUri()->getQuery(), $queryParams);
        $sortdir = $queryParams['_sortDir'] ?? null;
        if (!isset($sortdir)) {
           $sortdir = 'asc';
        }
        $sortfield = $queryParams['_sortField'] ?? null;
        if (!Delivery::canBeSortedOn($sortfield) ) {
           $sortfield = 'reservation_id';
        }
        $page = $queryParams['_page'] ?? null;
        if (!isset($page)) {
           $page = '1';
        }
        $perPage = $queryParams['_perPage'] ?? null;
        if (!isset($perPage)) {
           $perPage = '50';
        }
        $query= $queryParams['_query'] ?? null; // TODO: use query value

        $querybuilder = Capsule::table('kb_deliveries')
            ->select('*');

        $deliveries = $querybuilder->orderBy($sortfield, $sortdir)->get();
        $deliveries_page = array_slice($deliveries->all(), ($page - 1) * $perPage, $perPage);

        $data = [];
        foreach ($deliveries_page as $delivery) {
            $this->logger->info(\json_encode($delivery, JSON_THROW_ON_ERROR));
            $deliveryData = DeliveryMapper::mapDeliveryToArray($delivery);
            array_push($data, $deliveryData);
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($deliveries));
    }

    public function getByID(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib GET '/deliveries/id' route");
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }

        $data = DeliveryMapper::mapDeliveryToArray($delivery);
        return $response->withJson($data);
    }

    public function create(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib POST '/deliveries' route");

        try {
            Authorisation::checkAccessByToken($this->token,
                ["deliveries.all", "deliveries.create", "deliveries.create.owner", "deliveries.create.owner.donation_only"]);
        } catch (ForbiddenException $e) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->withJson(["error" => "no applicable allowed scope in token"]);
        }
        parse_str($request->getUri()->getQuery(), $data);
        if (empty($data)) { // check for content in body
            $data = $request->getParsedBody();
        }

        $errors = [];
        if (!DeliveryValidator::isValidDeliveryData($data, $this->logger, $errors)) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
        }
        $user = Contact::find($data["user_id"]);
        if (null == $user) {
            $errors = ["message" => "No user found with id " . $data["user_id"]];
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors);
        }
        $this->logger->info("Delivery request is valid");
        $delivery = new \Api\Model\Delivery();

        if(isset($data["user_id"])){
            $delivery->user_id = $data["user_id"];
        }
        if(isset($data["reservation_id"])) {
            $delivery->reservation_id = $data["reservation_id"];
        }
        if(isset($data["state"])){
            $delivery->state = $data["state"];
        }
        if(isset($data["type"])){
            $delivery->type = $data["type"];
        }
        if(isset($data["pick_up_address"])) {
            $delivery->pick_up_address = $data["pick_up_address"];
        }
        if(isset($data["drop_off_address"])) {
            $delivery->drop_off_address = $data["drop_off_address"];
        }
        if(isset($data["price"])) {
            $delivery->price = $data["price"];
        } else {
            if ($delivery->type == DeliveryType::PICK_UP) {
                $delivery->price = 0; // pick up is free
            }
        }
        if(isset($data["comment"])) {
            $delivery->comment = $data["comment"];
        }
        if(isset($data["consumers"])) {
            $delivery->consumers = $data["consumers"];
        }
        if(isset($data["pick_up_date"])) {
            $delivery->pick_up_date = $data["pick_up_date"];
        }
        if(isset($data["drop_off_date"])) {
            $delivery->drop_off_date = $data["drop_off_date"];
        }

        $access = Authorisation::checkDeliveryAccess($this->token, "create", $delivery, $this->logger);
        $this->logger->info("Delivery access check: " . $access);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN);
        }

        if ($access !== AccessType::FULL_ACCESS) {
            $delivery->state = DeliveryState::REQUESTED;
        }

        $delivery->save();
        $items = $data["items"] ?? [];
        if (is_array($items)) {
            foreach ($items as $rcvdItem) {
                $item = InventoryItem::find($rcvdItem["tool_id"]);
                if (null != $item) {
                    $this->logger->info ("Adding Item to delivery: " . \json_encode($item, JSON_THROW_ON_ERROR) . "\n\n");
                    $deliveryItem = new DeliveryItem();
                    $deliveryItem->inventory_item_id = $item->id;
                    $delivery->deliveryItems()->save($deliveryItem);
                    $item->deliveryItems()->save($deliveryItem);

                    if (isset($rcvdItem["reservation_id"])) {
                        $this->logger->info ("Delivery Item: " . \json_encode($deliveryItem, JSON_THROW_ON_ERROR) . "\n\n");
                        $reservation = Reservation::find($rcvdItem["reservation_id"]);
                        if ($reservation !== null) {
                            $this->logger->info ("Reservation found: " . \json_encode($reservation, JSON_THROW_ON_ERROR) . "\n\n");
                            $reservation->deliveryItem()->save($deliveryItem);
                        }
                    }

                    if (isset($rcvdItem["lending_id"])) {
                        $this->logger->info ("Delivery Item: " . \json_encode($deliveryItem, JSON_THROW_ON_ERROR) . "\n\n");
                        $lending = Lending::find($rcvdItem["lending_id"]);
                        if ($lending !== null) {
                            $this->logger->info ("Lending found: " . \json_encode($lending, JSON_THROW_ON_ERROR) . "\n\n");
                            $lending->deliveryItem()->save($deliveryItem);
                        }
                    }
                } else {
                    $this->logger->warn("No inventory item found for item " . \json_encode($rcvdItem, JSON_THROW_ON_ERROR));
                }
            }
        }

        if ($delivery->state === DeliveryState::REQUESTED) {
            $this->logger->info("Sending delivery request notification to " . DELIVERY_NOTIF_EMAIL . " (delivery: " . \json_encode($delivery, JSON_THROW_ON_ERROR) . ")");
            $isSendSuccessful = $this->mailManager->sendDeliveryRequestNotification(DELIVERY_NOTIF_EMAIL, $delivery, $user);
            if ($isSendSuccessful) {
                $this->logger->info('notification email sent successfully to ' . DELIVERY_NOTIF_EMAIL);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending delivery notification email: '. $message);
            }
            $this->logger->info("Sending delivery request to " . $user->email . " (delivery: " . \json_encode($delivery, JSON_THROW_ON_ERROR) . ")");
            $isSendSuccessful = $this->mailManager->sendDeliveryRequest($user->email, $delivery, $user);
            if ($isSendSuccessful) {
                $this->logger->info('delivery email sent successfully to ' . $user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending delivery request email: '. $message);
            }
        }

        return $response->withJson(DeliveryMapper::mapDeliveryToArray($delivery))
            ->withStatus(HttpResponseCode::CREATED);
    }
    
    public function update(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib PUT '/delivery/id' route" . $args['deliveryid']);
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        parse_str($request->getUri()->getQuery(), $data);
        if (empty($data)) { // check for content in body
            $data = $request->getParsedBody();
        }
        $this->logger->info("Klusbib PUT body: ". json_encode($data, JSON_THROW_ON_ERROR));
        $errors = [];
        if (!DeliveryValidator::isValidDeliveryData($data, $this->logger, $errors, false)) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
        }
        $access = Authorisation::checkDeliveryAccess($this->token, "update", $delivery, $this->logger);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN); // Unauthorized
        }
        $confirmation = false;
        $cancellation = false;
        if ($access === AccessType::FULL_ACCESS) {
            if (isset($data["user_id"])) {
                $this->logger->info("Klusbib PUT updating user_id from " . $delivery->user_id . " to " . $data["user_id"]);
                $delivery->user_id = $data["user_id"];
            }
            if (isset($data["reservation_id"])) {
                $this->logger->info("Klusbib PUT updating reservation_id from " . $delivery->reservation_id . " to " . $data["reservation_id"]);
                $delivery->reservation_id = $data["reservation_id"];
            }
        }
        if (isset($data["pick_up_address"])) {
            $this->logger->info("Klusbib PUT updating pick up address from " . $delivery->pick_up_address . " to " . $data["pick_up_address"]);
            $delivery->pick_up_address = $data["pick_up_address"];
        }
        if (isset($data["drop_off_address"])) {
            $this->logger->info("Klusbib PUT updating drop off address from " . $delivery->drop_off_address . " to " . $data["drop_off_address"]);
            $delivery->drop_off_address = $data["drop_off_address"];
        }
        if (isset($data["state"])) {
            $this->logger->info("Klusbib PUT updating state from " . $delivery->state . " to " . $data["state"]);
            if ($delivery->state != DeliveryState::CONFIRMED
                && $data["state"] == DeliveryState::CONFIRMED) {
                $confirmation = true;
            }
            if ($delivery->state != DeliveryState::CANCELLED
                && $data["state"] == DeliveryState::CANCELLED) {
                $cancellation = true;
            }
            $delivery->state = $data["state"];
        }
        if (isset($data["type"])) {
            $this->logger->info("Klusbib PUT updating type from " . $delivery->type . " to " . $data["type"]);
            $delivery->type = $data["type"];
        }
        if (isset($data["pick_up_date"])) {
            $this->logger->info("Klusbib PUT updating pick up date from " . $delivery->pick_up_date . " to " . $data["pick_up_date"]);
            $delivery->pick_up_date = $data["pick_up_date"];
        }
        if (isset($data["drop_off_date"])) {
            $this->logger->info("Klusbib PUT updating drop off date from " . $delivery->drop_off_date . " to " . $data["drop_off_date"]);
            $delivery->drop_off_date = $data["drop_off_date"];
        }
        if (isset($data["price"])) {
            $this->logger->info("Klusbib PUT updating price from " . $delivery->price . " to " . $data["price"]);
            $delivery->price = $data["price"];
        }
        if (isset($data["consumers"])) {
            $this->logger->info("Klusbib PUT updating consumers from " . $delivery->consumers . " to " . $data["consumers"]);
            $delivery->consumers = $data["consumers"];
        }
        if (isset($data["comment"])) {
            $this->logger->info("Klusbib PUT updating comment from " . $delivery->comment . " to " . $data["comment"]);
            $delivery->comment = $data["comment"];
        }
        $delivery->save();
        $reason = "";
        if ($confirmation) {
            $reason = "confirmation";
            // Send notification to confirm the delivery
            $this->logger->info('Sending notification for confirmation of delivery ' . json_encode($delivery, JSON_THROW_ON_ERROR));
            $isSendSuccessful = $this->mailManager->sendDeliveryConfirmation($delivery->user->email, $delivery, $delivery->user);
            if ($isSendSuccessful) {
                $this->logger->info('confirm notification email sent successfully to ' . $delivery->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending confirm_delivery notification email: '. $message);
            }
        }
        if ($cancellation) {
            $reason = "cancellation";
            // Send notification to cancel the delivery
            $this->logger->info('Sending notification for cancel of delivery ' . json_encode($delivery, JSON_THROW_ON_ERROR));
            $isSendSuccessful = $this->mailManager->sendDeliveryCancellation($delivery->user->email, $delivery, $delivery->user);
            if ($isSendSuccessful) {
                $this->logger->info('cancel notification email sent successfully to ' . $delivery->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending cancel_delivery notification email: '. $message);
            }
        }
        $this->mailManager->sendDeliveryUpdateNotification(DELIVERY_NOTIF_EMAIL, $delivery, $reason);
        return $response->withJson(DeliveryMapper::mapDeliveryToArray($delivery));
    }

    public function delete(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib DELETE '/deliveries/id' route");
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.delete", "deliveries.delete.owner"]);
        // TODO: only allow delete of own reservations if access is reservations.delete.owner
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NO_CONTENT);
        }
        $delivery->delete();
        return $response->withStatus(HttpResponseCode::OK);
    }

    public function addItem(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib POST '/deliveries/{delivery_id}/items' route");
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND)
                ->withJson(["message" => "Delivery not found!"]);
        }
        $data = $request->getParsedBody();
        $item = InventoryItem::find($data['item_id']);
        if (null == $item) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                ->withJson(["message" => "Missing item_id"]);
        }
        $this->addItemToDelivery($item, $delivery);

        return $response->withStatus(HttpResponseCode::CREATED);
    }
    public function updateItem(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib PUT '/deliveries/{deliveryid}/items/{itemid}' route");
        // update quantity?
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $data = $request->getParsedBody();

        foreach( $delivery->deliveryItems()->where('inventory_item_id', $args['itemid'])->get() as $deliveryItem) {
            if (isset($data["reservation_id"])) {
                $this->logger->info("Klusbib PUT updating reservation_id from " . $deliveryItem->reservation_id . " to " . $data["reservation_id"]);
                $deliveryItem->reservation_id = $data["reservation_id"];
            }
            if (isset($data["fee"])) {
                $this->logger->info("Klusbib PUT updating fee from " . $deliveryItem->fee . " to " . $data["fee"]);
                $deliveryItem->fee = $data["fee"];
            }
            if (isset($data["size"])) {
                $this->logger->info("Klusbib PUT updating size from " . $deliveryItem->size . " to " . $data["size"]);
                $deliveryItem->size = $data["size"];
            }
            if (isset($data["comment"])) {
                $this->logger->info("Klusbib PUT updating comment from " . $deliveryItem->comment . " to " . $data["comment"]);
                $deliveryItem->comment = $data["comment"];
            }
            $deliveryItem->save();
        }
        $delivery->refresh();

        return $response->withJson(DeliveryMapper::mapDeliveryToArray($delivery))->withStatus(HttpResponseCode::OK);
    }
    public function removeItem(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib DELETE '/deliveries/{delivery_id}/items/{item_id}' route");
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $item = InventoryItem::find($args['itemid']);
        if (null == $item) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND)
                ->withJson(["message" => "Item not found!"]);
        }
        $delivery->deliveryItems()->delete($args['itemid']);
        $delivery->save();

        return $response->withStatus(HttpResponseCode::OK);
    }

    /**
     * @param $delivery
     * @param $item
     */
    private function addItemToDelivery($item, $delivery): void
    {
        $deliveryItem = new DeliveryItem();
        $deliveryItem->save();
        $item->deliveryItems()->save($deliveryItem);
        $delivery->deliveryItems()->save($deliveryItem);
    }

}