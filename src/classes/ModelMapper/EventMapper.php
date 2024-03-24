<?php

namespace Api\ModelMapper;


class EventMapper
{
    static public function mapEventToArray($event) {

        $eventArray = ["event_id" => $event->event_id,
            "name" => $event->name,
            "version" => $event->version,
            "amount" => $event->amount,
            "currency" => $event->currency,
            "data" => $event->data,
            "created_at" => $event->created_at,
            "updated_at" => $event->updated_at,
        ];

        return $eventArray;
    }

    static public function mapArrayToEvent($data, $event, $logger = null) {
        if (isset($data["event_id"])) {
            $event->event_id= $data["event_id"];
        }
        if (isset($data["name"])) {
            $event->name= $data["name"];
        }
        if (isset($data["version"])) {
            $event->version= $data["version"];
        }
        if (isset($data["amount"])) {
            $event->amount= $data["amount"];
        }
        if (isset($data["currency"])) {
            $event->currency= $data["currency"];
        }
        if (isset($data["data"])) {
            $event->data= $data["data"];
        }

    }
}