<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Lending extends Model
{
    protected $primaryKey = "lending_id";
    static protected $fieldArray = ['lending_id', 'start_date', 'due_date', 'returned_date', 'tool_id',
        'user_id', 'comments', 'active', 'created_by', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Tool::$fieldArray);
    }

}