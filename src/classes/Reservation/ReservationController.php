<?php

namespace Api\Reservation;

use Api\Delivery\DeliveryManager;
use Api\Tool\ToolManager;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\ReservationMapper;
use Api\Authorisation;
use Api\Validator\ReservationValidator;
use Api\AccessType;
use Api\Model\Reservation;
use Api\Model\ReservationState;
use Api\Mail\MailManager;
use DateInterval;
use Illuminate\Support\Facades\Http;

class ReservationController implements ReservationControllerInterface
{
    protected $logger;
    protected $token;
    protected $mailManager;
    protected $toolManager;

    public function __construct($logger, $token, MailManager $mailManager, ToolManager $toolManager) {
        $this->logger = $logger;
        $this->token = $token;
        $this->mailManager = $mailManager;
        $this->toolManager = $toolManager;
    }

    public function getAll($request, $response, $args) {
        $this->logger->info("Klusbib GET '/reservations' route");

        parse_str($request->getUri()->getQuery(), $queryParams);
        $sortdir = $queryParams['_sortDir'] ?? null;
        if (!isset($sortdir)) {
            $sortdir = 'asc';
        }
        $sortfield = $queryParams['_sortField'] ?? null;
        if (!Reservation::canBeSortedOn($sortfield) ) {
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
        $query= $queryParams['_query'] ?? null;
        $querybuilder = Capsule::table('kb_reservations')
            ->join('contact', 'kb_reservations.user_id', '=', 'contact.id')
            ->select('kb_reservations.*', 'contact.first_name', 'contact.last_name');
        $isOpen = $queryParams['isOpen'] ?? null;
        if (isset($isOpen) && $isOpen == 'true') {
            $querybuilder->whereIn('kb_reservations.state', array(ReservationState::REQUESTED, ReservationState::CONFIRMED));
        }
        if (isset($query)) {
            $querybuilder->where('contact.first_name', 'LIKE', '%'.$query.'%' )
                ->orWhere('contact.last_name', 'LIKE', '%'.$query.'%' );
        }

        if ($sortfield == "username") {
            $sortfield = 'contact.first_name';
        } else {
            $sortfield = 'kb_reservations.' . $sortfield;
        }
        $reservations = $querybuilder->orderBy($sortfield, $sortdir)->get();
        $reservations_page = array_slice($reservations->all(), ($page - 1) * $perPage, $perPage);

        $data = array();
        foreach ($reservations_page as $reservation) {
            $reservationData = ReservationMapper::mapReservationToArray($reservation);
            $reservationData["username"] = $reservation->first_name . " " . $reservation->last_name;
            $tool = $this->toolManager->getById($reservation->tool_id);
            $reservationData["deliverable"] = $tool != null ? $tool->deliverable : false;
            array_push($data, $reservationData);
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($reservations));
    }
    public function getByID($request, $response, $args) {
        $this->logger->info("Klusbib GET '/reservations/id' route");
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }

        $data = ReservationMapper::mapReservationToArray($reservation);
        return $response->withJson($data);
    }

    public function create($request, $response, $args) {
        $this->logger->info("Klusbib POST '/reservations' route");
        try {
            Authorisation::checkAccessByToken($this->token,
                ["reservations.all", "reservations.create", "reservations.create.owner", "reservations.create.owner.donation_only"]);
        } catch (ForbiddenException $e) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->withJson(array("error" => "no applicable allowed scope in token"));
        }
        $data = $request->getParsedBody();
        $errors = array();
        if (!ReservationValidator::isValidReservationData($data, $this->logger, $this->toolManager, $errors)) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
        }
        $this->logger->info("Reservation request is valid");
        $reservation = new \Api\Model\Reservation();
        // 	$reservation->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
        // TODO: check restricted reservation periods for this tool
        // TODO: check conflicts with other reservations
        $reservation->tool_id = $data["tool_id"];
        $reservation->user_id = $data["user_id"];
        if (isset($data["title"])) {
            $reservation->title = $data["title"];
        }
        if (isset($data["type"])) {
            $reservation->type = $data["type"];
        } else {
            $reservation->type = "reservation";
        }
        if (isset($data["state"])) {
            $reservation->state = $data["state"];
        } else {
            $reservation->state = ReservationState::REQUESTED;
        }
        if (isset($data["startsAt"])) {
            // TODO: if not admin, only allow future dates
            $startsAt = new \DateTime($data["startsAt"]);
            $reservation->startsAt = new \DateTime($data["startsAt"]);
        } else {
            $reservation->startsAt = new \DateTime("now");
        }
        if (isset($data["endsAt"])) {
            $reservation->endsAt = new \DateTime($data["endsAt"]);
        } else {
            $reservation->endsAt = clone $reservation->startsAt;
            $reservation->endsAt->add(new DateInterval('P7D'));
        }
        if (isset($data["comment"]) || isset($data["cancel_reason"])) {
            if (isset($data["comment"]) && !isset($data["cancel_reason"])) {
                $newComment = $data["comment"];
            } else if (!isset($data["comment"]) && isset($data["cancel_reason"])) {
                $newComment = "Cancel reason: " . $data["cancel_reason"];
            } else if (isset($data["comment"]) && isset($data["cancel_reason"])) {
                $newComment = $data["comment"] . "\nCancel reason: " . $data["cancel_reason"];
            }
            $reservation->comment = $newComment;
        }
        // 	$this->logger->debug('tool =' . json_encode($reservation->tool));
        $toolOwnerId = isset($reservation->tool) ? $reservation->tool->owner_id : null;
        $access = Authorisation::checkReservationAccess($this->token, "create", $reservation, $toolOwnerId);
        $this->logger->info("Reservation access check: " . $access);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN); // Unauthorized
        }
        // TODO: add state on reservation: REQUESTED / CONFIRMED / CANCELLED / CLOSED
        if ($access !== AccessType::FULL_ACCESS) {
            $reservation->state = ReservationState::REQUESTED;
        }
        if ($access !== AccessType::FULL_ACCESS) {
            $reservation->type = "reservation";
        }
        // TODO: if not admin, should be max startsAt + 7 days

        if (!$reservation->save()) {
            return $response->withJson(array("result" => "failed", "message" => "Unable to store reservation"))
                ->withStatus(HttpResponseCode::INTERNAL_ERROR);
        }
        if ($reservation->state === ReservationState::REQUESTED) {
            // Send notification to confirm the reservation
            $this->logger->info('Sending notifications for new reservation ' . json_encode($reservation));
            $tool = $this->toolManager->getById($reservation["tool_id"]);
            $isSendSuccessful = $this->mailManager->sendReservationRequest($reservation->user->email,
                $reservation->user, $tool, $reservation, RESERVATION_NOTIF_EMAIL);
            if ($isSendSuccessful) {
                $this->logger->info('notification email sent successfully to ' . $reservation->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending reservation notification email: '. $message);
            }
            // TODO: also send a confirmation to requester?
        }
        return $response->withJson(ReservationMapper::mapReservationToArray($reservation))
            ->withStatus(HttpResponseCode::CREATED);
    }
    public function update($request, $response, $args) {
        $this->logger->info("Klusbib PUT '/reservations/id' route" . $args['reservationid']);
        Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.update", "reservations.update.owner"]);
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $data = $request->getParsedBody();
        $this->logger->info("Klusbib PUT body: ". json_encode($data));
        $errors = array();
        if (!ReservationValidator::isValidReservationData($data, $this->logger, $this->toolManager, $errors)) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
        }
        $toolOwnerId = isset($reservation->tool) ? $reservation->tool->owner_id : null;
        $access = Authorisation::checkReservationAccess($this->token, "update", $reservation, $toolOwnerId, $this->logger);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN); // Unauthorized
        }
        $confirmation = false;
        $cancellation = false;
        if ($access === AccessType::FULL_ACCESS) {
            if (isset($data["tool_id"])) {
                $this->logger->info("Klusbib PUT updating tool_id from " . $reservation->tool_id . " to " . $data["tool_id"]);
                $reservation->tool_id = $data["tool_id"];
            }
            if (isset($data["user_id"])) {
                $this->logger->info("Klusbib PUT updating user_id from " . $reservation->user_id . " to " . $data["user_id"]);
                $reservation->user_id = $data["user_id"];
            }
        }
        if (isset($data["title"])) {
            $this->logger->info("Klusbib PUT updating title from " . $reservation->title . " to " . $data["title"]);
            $reservation->title = $data["title"];
        }
        if (isset($data["type"])) {
            $this->logger->info("Klusbib PUT updating type from " . $reservation->type . " to " . $data["type"]);
            $reservation->type = $data["type"];
        }
        if (isset($data["state"])) {
            $this->logger->info("Klusbib PUT updating state from " . $reservation->state . " to " . $data["state"]);
            if ($reservation->state != ReservationState::CONFIRMED
                && $data["state"] == ReservationState::CONFIRMED) {
                $confirmation = true;
            }
            if ($reservation->state != ReservationState::CANCELLED
                && $data["state"] == ReservationState::CANCELLED) {
                $cancellation = true;
            }
            $reservation->state = $data["state"];
        }
        if (isset($data["startsAt"])) {
            $this->logger->info("Klusbib PUT updating startsAt from " . $reservation->startsAt . " to " . $data["startsAt"]);
            $reservation->startsAt = $data["startsAt"];
        }
        if (isset($data["endsAt"])) {
            $this->logger->info("Klusbib PUT updating endsAt from " . $reservation->endsAt . " to " . $data["endsAt"]);
            $reservation->endsAt = $data["endsAt"];
        }
        if (isset($data["comment"]) || isset($data["cancel_reason"])) {
            if (isset($data["comment"]) && !isset($data["cancel_reason"])) {
                $newComment = $data["comment"];
            } else if (!isset($data["comment"]) && isset($data["cancel_reason"])) {
                $newComment = "Cancel reason: " . $data["cancel_reason"];
            } else if (isset($data["comment"]) && isset($data["cancel_reason"])) {
                $newComment = $data["comment"] . "\nCancel reason: " . $data["cancel_reason"];
            }
            $this->logger->info("Klusbib PUT updating comment from " . $reservation->comment . " to " . $newComment);
            $reservation->comment = $newComment;
        }
        if ($reservation->deliveryItem()->exists()) {
            // Update delivery
            $this->updateDelivery($reservation);
        }
        $reservation->save();

        if ($confirmation) {
            // Send notification to confirm the reservation
            $this->logger->info('Sending notification for confirmation of reservation ' . json_encode($reservation));
            $tool = $this->toolManager->getById($reservation->tool_id);
            $isSendSuccessful = $this->mailManager->sendReservationConfirmation($reservation->user->email,
                $reservation->user, $tool, $reservation, RESERVATION_NOTIF_EMAIL);
            if ($isSendSuccessful) {
                $this->logger->info('confirm notification email sent successfully to ' . $reservation->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending confirm_reservation notification email: '. $message);
            }
        }
        if ($cancellation) {
            // Send notification to confirm the reservation
            $this->logger->info('Sending notification for cancel of reservation ' . json_encode($reservation));
            $tool = $this->toolManager->getById($reservation->tool_id);
            $isSendSuccessful = $this->mailManager->sendReservationCancellation($reservation->user->email,
                $reservation->user, $tool, $reservation, RESERVATION_NOTIF_EMAIL, $this->token->decoded->sub);
            if ($isSendSuccessful) {
                $this->logger->info('cancel notification email sent successfully to ' . $reservation->user->email);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending cancel_reservation notification email: '. $message);
            }
        }
        return $response->withJson(ReservationMapper::mapReservationToArray($reservation));
    }
    public function delete($request, $response, $args) {
        $this->logger->info("Klusbib DELETE '/reservations/id' route");
        Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.delete", "reservations.delete.owner"]);
        // TODO: only allow delete of own reservations if access is reservations.delete.owner
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(HttpResponseCode::NO_CONTENT);
        }
        if ($reservation->deliveryItem()->exists()) {
            $reason = "Removal of reservation for item " . \json_encode($reservation->deliveryItem);
            $this->deleteDeliveryItem($reservation->deliveryItem, $reason);
        }
        $reservation->delete();
        return $response->withStatus(HttpResponseCode::OK);
    }

    private function updateDelivery($reservation) {
        // check reservation status
        if ($reservation->isCancelled() && $reservation->deliveryItem()->exists()) {
            $reason = "Reservation is cancelled for item " . \json_encode($reservation->deliveryItem);
            $this->deleteDeliveryItem($reservation->deliveryItem, $reason);
        }

        // check reservation vs delivery start and end date
        $delivery = $reservation->deliveryItem->delivery;
        if ($reservation->startsAt > $delivery->pick_up_date) {
            $reason = "Delivery is planned before start of reservation";
            $this->logger->info("Sending delivery update notification with reason $reason");
            $this->mailManager->sendDeliveryUpdateNotification(DELIVERY_NOTIF_EMAIL, $delivery, $reason);
        }
        if ($reservation->endsAt < $delivery->drop_off_date) {
            $reason = "Delivery is planned after end of reservation";
            $this->logger->info("Sending delivery update notification with reason $reason");
            $this->mailManager->sendDeliveryUpdateNotification(DELIVERY_NOTIF_EMAIL, $delivery, $reason);
        }

    }
    private function deleteDeliveryItem($item, $reason = "unknown")
    {
        if (!isset($item)) {
            return;
        }
        $delivery = $item->delivery;
        $item->delete();
//        $delivery->items()->detach($item->inventory_item_id);
//        $delivery->save();

        // send email notification
        $this->mailManager->sendDeliveryUpdateNotification(DELIVERY_NOTIF_EMAIL, $delivery, $reason);
    }
}