<?php

namespace Api\Reservation;

use Api\Tool\ToolManager;
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

        $sortdir = $request->getQueryParam('_sortDir');
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
        $querybuilder = Capsule::table('reservations');
        $isOpen = $request->getQueryParam('isOpen');
        if (isset($isOpen) && $isOpen == 'true') {
            $querybuilder->whereIn('state', array(ReservationState::REQUESTED, ReservationState::CONFIRMED));
        }
        $reservations = $querybuilder->orderBy($sortfield, $sortdir)->get();
        $reservations_page = array_slice($reservations->all(), ($page - 1) * $perPage, $perPage);

        $data = array();
        foreach ($reservations_page as $reservation) {
            array_push($data, ReservationMapper::mapReservationToArray($reservation));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($reservations));
    }
    public function getByID($request, $response, $args) {
        $this->logger->info("Klusbib GET '/reservations/id' route");
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(404);
        }

        $data = ReservationMapper::mapReservationToArray($reservation);
        return $response->withJson($data);
    }
    public function create($request, $response, $args) {
        $this->logger->info("Klusbib POST '/reservations' route");
        Authorisation::checkAccessByToken($this->token,
            ["reservations.all", "reservations.create", "reservations.create.owner", "reservations.create.owner.donation_only"]);
        $data = $request->getParsedBody();
        if (!ReservationValidator::isValidReservationData($data, $this->logger, $this->toolManager)) {
            return $response->withStatus(400); // Bad request
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
        if (isset($data["comment"])) {
            $reservation->comment = $data["comment"];
        }
        // 	$this->logger->debug('tool =' . json_encode($reservation->tool));
        $toolOwnerId = isset($reservation->tool) ? $reservation->tool->owner_id : null;
        $access = Authorisation::checkReservationAccess($this->token, "create", $reservation, $toolOwnerId);
        $this->logger->info("Reservation access check: " . $access);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(403); // Unauthorized
        }
        // TODO: add state on reservation: REQUESTED / CONFIRMED / CANCELLED / CLOSED
        if ($access !== AccessType::FULL_ACCESS) {
            $reservation->state = ReservationState::REQUESTED;
        }
        if ($access !== AccessType::FULL_ACCESS) {
            $reservation->type = "reservation";
        }
        // TODO: if not admin, should be max startsAt + 7 days
        $reservation->save();
        if ($reservation->state === ReservationState::REQUESTED) {
            // Send notification to confirm the reservation
            $this->logger->info('Sending notification for new reservation ' . json_encode($reservation));
            $isSendSuccessful = $this->mailManager->sendReservationRequest(RESERVATION_NOTIF_EMAIL,
                $reservation->user, $reservation->tool, $reservation);
            if ($isSendSuccessful) {
                $this->logger->info('notification email sent successfully to ' . RESERVATION_NOTIF_EMAIL);
            } else {
                $message = $this->mailManager->getLastMessage();
                $this->logger->warn('Problem sending reservation notification email: '. $message);
            }
            // TODO: also send a confirmation to requester?
        }
        return $response->withJson(ReservationMapper::mapReservationToArray($reservation))
            ->withStatus(201);
    }
    public function update($request, $response, $args) {
        $this->logger->info("Klusbib PUT '/reservations/id' route");
        Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.update", "reservations.update.owner"]);
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(404);
        }
        $data = $request->getParsedBody();
        $this->logger->info("Klusbib PUT body: ". json_encode($data));
        if (!ReservationValidator::isValidReservationData($data, $this->logger, $this->toolManager)) {
            return $response->withStatus(400); // Bad request
        }
        $access = Authorisation::checkReservationAccess($this->token, "update", $reservation, $reservation->tool->owner_id);
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(403); // Unauthorized
        }
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
        if (isset($data["comment"])) {
            $this->logger->info("Klusbib PUT updating comment from " . $reservation->comment . " to " . $data["comment"]);
            $reservation->comment = $data["comment"];
        }
        $reservation->save();
        return $response->withJson(ReservationMapper::mapReservationToArray($reservation));
    }
    public function delete($request, $response, $args) {
        $this->logger->info("Klusbib DELETE '/reservations/id' route");
        Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.delete", "reservations.delete.owner"]);
        // TODO: only allow delete of own reservations if access is reservations.delete.owner
        $reservation = \Api\Model\Reservation::find($args['reservationid']);
        if (null == $reservation) {
            return $response->withStatus(204);
        }
        $reservation->delete();
        return $response->withStatus(200);
    }
}