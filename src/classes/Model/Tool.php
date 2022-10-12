<?php
namespace Api\Model;

use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return ToolFactory::new();
    }

    protected $table = 'kb_tools';
    protected $primaryKey = "tool_id";
	static protected $fieldArray = ['tool_id', 'name', 'description', 'category', 'img', 'created_at', 'updated_at', 
			'brand', 'type', 'serial', 'manufacturing_year', 'manufacturer_url', 'doc_url', 'code', 'owner_id', 'reception_date',
			'state', 'visible', 'size', 'fee', 'deliverable'
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

    public function inventoryItem()
    {
        return $this->belongsTo('Api\Model\InventoryItem', 'tool_id');
    }
    // Query helpers
    public function scopeAll($query)
    {
        return $query->where('state', '<>', ToolState::DISPOSED);
    }

}