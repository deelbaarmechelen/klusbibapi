<?php

namespace Api\Delivery;

use Api\Mail\MailManager;
use Api\Model\InventoryItem;
use Api\Model\User;
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

    public function getAll($request, $response, $args) {
       $this->logger->info("Klusbib GET '/deliveries' route");

       try {
           Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.list"]);
       } catch (ForbiddenException $ex) {
           return $response->withStatus($ex->getCode()); // Unauthorized
       }

        /*         $sortdir = $request->getQueryParam('_sortDir');
               if (!isset($sortdir)) {
                   $sortdir = 'asc';
               }
               $sortfield = $request->getQueryParam('_sortField');
               if (!Reservation::canBeSortedOn($sortfield) ) {
                   $sortfield = 'reservation_id';
               }
               $page = $request->getQueryParam('_page');
               if (!isset($page)) {
                   $page = '1';
               }
               $perPage = $request->getQueryParam('_perPage');
               if (!isset($perPage)) {
                   $perPage = '50';
               }
               $query= $request->getQueryParam('_query'); */

        $querybuilder = Capsule::table('deliveries')
            ->select('*');

/*         if ($sortfield == "username") {
            $sortfield = 'users.firstname';
        } else {
            $sortfield = 'reservations.' . $sortfield;
        } */
        $deliveries = $querybuilder->get();

        return $response->withJson($deliveries)
            ->withHeader('X-Total-Count', count($deliveries));
    }

    public function getByID($request, $response, $args) {
        $this->logger->info("Klusbib GET '/deliveries/id' route");
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }

        $data = DeliveryMapper::mapDeliveryToArray($delivery);
        return $response->withJson($data);
    }

    public function create($request, $response, $args) {
        $this->logger->info("Klusbib POST '/deliveries' route");

        Authorisation::checkAccessByToken($this->token,
            ["deliveries.all", "deliveries.create", "deliveries.create.owner", "deliveries.create.owner.donation_only"]);

        $data = $request->getParsedBody();
        $errors = array();
        if (!DeliveryValidator::isValidDeliveryData($data, $this->logger, $errors)) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
        }
        $user = User::find($data["user_id"]);
        if (null == $user) {
            $errors = array("message" => "No user found with id " . $data["user_id"]);
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
        if(isset($data["pick_up_address"])) {
            $delivery->pick_up_address = $data["pick_up_address"];
        }
        if(isset($data["drop_off_address"])) {
            $delivery->drop_off_address = $data["drop_off_address"];
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
            return $response->withStatus(403); // Unauthorized
        }

        if ($access !== AccessType::FULL_ACCESS) {
            $delivery->state = DeliveryState::REQUESTED;
        }

        $delivery->save();
        if ($delivery->state === DeliveryState::REQUESTED) {
            $this->mailManager->sendDeliveryRequestNotification(DELIVERY_NOTIF_EMAIL, $delivery, $user);
        }
        return $response->withJson(DeliveryMapper::mapDeliveryToArray($delivery))
            ->withStatus(201);
    }
    
    public function update($request, $response, $args) {
        $this->logger->info("Klusbib PUT '/delivery/id' route" . $args['deliveryid']);
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(404);
        }
        $data = $request->getParsedBody();
        $this->logger->info("Klusbib PUT body: ". json_encode($data));
        $errors = array();
        if (!DeliveryValidator::isValidDeliveryData($data, $this->logger, $errors)) {
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
            /*if ($delivery->state != DeliveryState::CONFIRMED
                && $data["state"] == DeliveryState::CONFIRMED) {
                $confirmation = true;
            }
            if ($delivery->state != DeliveryState::CANCELLED
                && $data["state"] == DeliveryState::CANCELLED) {
                $cancellation = true;
            }*/
            $delivery->state = $data["state"];
        }
        if (isset($data["pick_up_date"])) {
            $this->logger->info("Klusbib PUT updating pick up date from " . $delivery->pick_up_date . " to " . $data["pick_up_date"]);
            $delivery->pick_up_date = $data["pick_up_date"];
        }
        if (isset($data["drop_off_date"])) {
            $this->logger->info("Klusbib PUT updating drop off date from " . $delivery->drop_off_date . " to " . $data["drop_off_date"]);
            $delivery->drop_off_date = $data["drop_off_date"];
        }
        if (isset($data["comment"])) {
            $this->logger->info("Klusbib PUT updating comment from " . $delivery->comment . " to " . $data["comment"]);
            $delivery->comment = $data["comment"];
        }
        $delivery->save();
        /*if ($confirmation) {
            // Send notification to confirm the delivery
            $this->logger->info('Sending notification for confirmation of reservation ' . json_encode($delivery));
            $tool = $this->toolManager->getById($delivery->tool_id);
            $isSendSuccessful = $this->mailManager->sendReservationConfirmation($delivery->user->email,
                $delivery->user, $tool, $delivery, DELIVERY_NOTIF_EMAIL);
            if ($isSendSuccessful) {
                $this->logger->info('confirm notification email sent successfully to ' . $delivery->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending confirm_delivery notification email: '. $message);
            }
        }
        if ($cancellation) {
            // Send notification to confirm the delivery
            $this->logger->info('Sending notification for cancel of delivery ' . json_encode($delivery));
            $tool = $this->toolManager->getById($reservation->tool_id);
            $isSendSuccessful = $this->mailManager->sendReservationCancellation($delivery->user->email,
                $reservation->user, $tool, $reservation, DELIVERY_NOTIF_EMAIL, $this->token->decoded->sub);
            if ($isSendSuccessful) {
                $this->logger->info('cancel notification email sent successfully to ' . $delivery->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending cancel_delivery notification email: '. $message);
            }
        }*/
        return $response->withJson(DeliveryMapper::mapDeliveryToArray($delivery));
    }

    public function delete($request, $response, $args) {
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

    public function addItem($request, $response, $args) {
        $this->logger->info("Klusbib POST '/deliveries/{delivery_id}/items' route");
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND)
                ->withJson(array("message" => "Delivery not found!"));
        }
        $data = $request->getParsedBody();
        $this->logger->info("Klusbib PUT body: ". json_encode($data));
        $item = InventoryItem::find($data['item_id']);
        if (null == $item) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND)
                ->withJson(array("message" => "Item not found!"));
        }
        $delivery->items()->attach($item);
        $delivery->save();

    }
    public function updateItem($request, $response, $args) {
        $this->logger->info("Klusbib PUT '/deliveries/{delivery_id}/items/{item_id}' route");
        // update quantity?
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
    }
    public function removeItem($request, $response, $args) {
        $this->logger->info("Klusbib DELETE '/deliveries/{delivery_id}/items/{item_id}' route");
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.update", "deliveries.update.owner"]);
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $item = InventoryItem::find($args['itemid']);
        if (null == $item) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND)
                ->withJson(array("message" => "Item not found!"));
        }
        $delivery->items()->detach($args['itemid']);
        $delivery->save();

    }

}