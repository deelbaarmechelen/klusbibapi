<?php

namespace Api\Events;

use Api\ModelMapper\EventMapper;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Model\Event;
use Api\Authorisation;

class EventsController implements EventsControllerInterface
{
    protected $logger;
    protected $token;

    public function __construct($logger, $token)
    {
        $this->logger = $logger;
        $this->token = $token;
    }

    public function getAll($request, $response, $args)
    {
        $this->logger->info("Klusbib GET '/events' route");

        $authorised = Authorisation::checkEventAccess($this->token, "list");
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $sortdir = $request->getQueryParam('_sortDir');
        if (!isset($sortdir)) {
            $sortdir = 'desc';
        }
        $sortfield = $request->getQueryParam('_sortField');
//    if (!User::canBeSortedOn($sortfield) ) {
        $sortfield = 'created_at';
//    }
        $page = $request->getQueryParam('_page');
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $request->getQueryParam('_perPage');
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $events = Capsule::table('events')->orderBy($sortfield, $sortdir)->get();
        $events_page = array_slice($events->all(), ($page - 1) * $perPage, $perPage);
        $data = array();
        foreach ($events_page as $event) {
            array_push($data, EventMapper::mapEventToArray($event));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($events));
    }

    public function create($request, $response, $args)
    {
        $this->logger->info("Klusbib POST on '/events' route");
        $data = $request->getParsedBody();
        $this->logger->info("parsedbody=" . json_encode($data));
        $errors = array();
//    if (empty($data)
//        || !UserValidator::containsMandatoryData($data, $this->logger, $errors)
//        || !UserValidator::isValidUserData($data, $this->logger, $errors)) {
//        $this->logger->info("errors=" . json_encode($errors));
//
//        return $response->withStatus(400) // Bad request
//        ->withJson($errors);
//    }

        $event = new \Api\Model\Event;
        EventMapper::mapArrayToEvent($data, $event, $this->logger);
        $event->save();
        $resourceUri = '/events/' . $event->event_id;
        return $response->withAddedHeader('Location', $resourceUri)
            ->withJson(EventMapper::mapEventToArray($event))
            ->withStatus(201);
    }
}