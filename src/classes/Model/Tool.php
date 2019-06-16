<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $primaryKey = "tool_id";
	static protected $fieldArray = ['tool_id', 'name', 'description', 'category', 'img', 'created_at', 'updated_at', 
			'brand', 'type', 'serial', 'manufacturing_year', 'manufacturer_url', 'doc_url', 'code', 'owner_id', 'reception_date',
			'state', 'visible'
	];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, Tool::$fieldArray);
	}
	public function reservations()
	{
		return $this->hasMany('Api\Model\Reservation', 'tool_id');
	}

    // Query helpers
    public function scopeAll($query)
    {
        return $query->where('state', '<>', ToolState::DISPOSED);
    }

}