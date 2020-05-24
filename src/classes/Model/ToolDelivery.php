<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class ToolDelivery extends Model
{
    protected $primaryKey = "tool_delivery_id";
	static protected $fieldArray = ['tool_id', 'delivery_id', 'created_at', 'updated_at'];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, ToolDelivery::$fieldArray);
	}
}