<?php

namespace Api\Delivery;

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

    public function __construct($logger, $token) {
        $this->logger = $logger;
        $this->token = $token;
    }

    public function getAll($request, $response, $args) {
       $this->logger->info("Klusbib GET '/deliveries' route");

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

   /*  public function getByID($request, $response, $args) {
        $this->logger->info("Klusbib GET '/reservations/id' route");
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(404);
        }

        $data = ReservationMapper::mapReservationToArray($reservation);
        return $response->withJson($data);
    } */
    public function create($request, $response, $args) {
        $this->logger->info("Klusbib POST '/deliveries' route");

        /*Authorisation::checkAccessByToken($this->token,
            ["reservations.all", "reservations.create", "reservations.create.owner", "reservations.create.owner.donation_only"]);*/

        $data = $request->getParsedBody();
        $errors = array();
        /*if (!ReservationValidator::isValidReservationData($data, $this->logger, $this->toolManager, $errors)) {
            return $response->withStatus(400)->withJson($errors); // Bad request
        }*/
        $this->logger->info("Reservation request is valid");
        $delivery = new \Api\Model\Delivery();

        if(isset($data["user_id"])){
            $delivery->user_id = $data["user_id"];
        }
        $delivery->reservation_id = $data["reservation_id"];
        if(isset($data["state"])){
            $delivery->state = $data["state"];
        }
        $delivery->pick_up_address = $data["pick_up_address"];
        $delivery->drop_off_address = $data["drop_off_address"];
        $delivery->comment = $data["comment"];
        $delivery->pick_up_date = $data["pick_up_date"];
        $delivery->drop_off_date = $data["drop_off_date"];
                
        /*$access = Authorisation::checkReservationAccess($this->token, "create", $delivery, $toolOwnerId);
        $this->logger->info("Reservation access check: " . $access);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(403); // Unauthorized
        }

        if ($access !== AccessType::FULL_ACCESS) {
            $delivery->state = ReservationState::REQUESTED;
        }
        if ($access !== AccessType::FULL_ACCESS) {
            $delivery->type = "reservation";
        }*/

        $delivery->save();
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
        /*if (!ReservationValidator::isValidReservationData($data, $this->logger, $this->toolManager, $errors)) {
            return $response->withStatus(400)->withJson($errors); // Bad request
        }*/
        $access = Authorisation::checkDeliveryAccess($this->token, "update", $delivery, $this->logger);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(403); // Unauthorized
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
            // Send notification to confirm the reservation
            $this->logger->info('Sending notification for confirmation of reservation ' . json_encode($delivery));
            $tool = $this->toolManager->getById($delivery->tool_id);
            $isSendSuccessful = $this->mailManager->sendReservationConfirmation($delivery->user->email,
                $delivery->user, $tool, $delivery, RESERVATION_NOTIF_EMAIL);
            if ($isSendSuccessful) {
                $this->logger->info('confirm notification email sent successfully to ' . $delivery->user->email);
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
        }*/
        return $response->withJson(DeliveryMapper::mapDeliveryToArray($delivery));
    }

    public function delete($request, $response, $args) {
        $this->logger->info("Klusbib DELETE '/deliveries/id' route");
        Authorisation::checkAccessByToken($this->token, ["deliveries.all", "deliveries.delete", "deliveries.delete.owner"]);
        // TODO: only allow delete of own reservations if access is reservations.delete.owner
        $delivery = \Api\Model\Delivery::find($args['deliveryid']);
        if (null == $delivery) {
            return $response->withStatus(204);
        }
        $delivery->delete();
        return $response->withStatus(200);
    } 
}