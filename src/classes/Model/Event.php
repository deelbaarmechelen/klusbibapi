<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;


class Event extends Model
{
    protected $primaryKey = "event_id";

    static protected $fieldArray = ['event_id', 'name', 'version', 'amount', 'currency', 'data',
        'created_at', 'updated_at'
    ];

    // public methods
    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Event::$fieldArray);
    }

}