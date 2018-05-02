<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;


class Event extends Model
{
    protected $primaryKey = "event_id";

    static protected $fieldArray = ['event_id', 'name', 'version', 'amount', 'currency', 'data',
        'created_at', 'updated_at'
    ];

}