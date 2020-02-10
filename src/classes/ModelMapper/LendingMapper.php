<?php

namespace Api\ModelMapper;


class LendingMapper
{
    private const ELOQUENT_DATE_FORMAT = 'Y-m-d H:i:s';

    static public function mapLendingToArray($lending) {

        $startDate = \DateTime::createFromFormat(self::ELOQUENT_DATE_FORMAT, $lending->start_date);
        $dueDate = \DateTime::createFromFormat(self::ELOQUENT_DATE_FORMAT, $lending->due_date);
        $returnedDate = \DateTime::createFromFormat(self::ELOQUENT_DATE_FORMAT, $lending->returned_date);
        $lendingArray = array("lending_id" => $lending->lending_id,
            "start_date" => $startDate ? $startDate->format('Y-m-d') : null,
            "due_date" => $dueDate ? $dueDate->format('Y-m-d'): null,
            "returned_date" => $returnedDate ? $returnedDate->format('Y-m-d'): null,
            "tool_id" => $lending->tool_id,
            "tool_type" => $lending->tool_type,
            "user_id" => $lending->user_id,
            "comments" => $lending->comments,
            "active" => $lending->active,
            "created_by" => $lending->created_by,
//            "created_at" => $lending->created_at,
//            "updated_at" => $lending->updated_at,
        );

        return $lendingArray;
    }

    static public function mapArrayToLending($data, $lending, $logger = null) {
        if (isset($data["lending_id"])) {
            $lending->lending_id= $data["lending_id"];
        }
        if (isset($data["start_date"])) {
            $lending->name= $data["start_date"];
        }
        if (isset($data["due_date"])) {
            $lending->version= $data["due_date"];
        }
        if (isset($data["returned_date"])) {
            $lending->amount= $data["returned_date"];
        }
        if (isset($data["tool_id"])) {
            $lending->currency= $data["tool_id"];
        }
        if (isset($data["user_id"])) {
            $lending->currency= $data["user_id"];
        }
        if (isset($data["comments"])) {
            $lending->currency= $data["comments"];
        }
        if (isset($data["active"])) {
            $lending->data= $data["active"];
        }

    }
}