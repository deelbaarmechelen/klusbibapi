<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Accessory extends Model
{
    protected $table = 'kb_tool';
    protected $primaryKey = "accessory_id";
    static protected $fieldArray = ['accessory_id', 'name', 'description', 'category', 'img', 'created_at', 'updated_at',
        'brand', 'type', 'state', 'visible', 'quantity'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Accessory::$fieldArray);
    }

}