<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class ProductTag extends Model
{
    protected $table = 'product_tag';
    protected $primaryKey = "id";
    public $incrementing = false;

    const CAR = 'Auto';
    const CONSTRUCTION = 'Bouw';
    const GARDEN = 'Tuin';
    const GENERAL = 'Algemeen';
    const TECHNICS = 'Techniek';
    const WOOD = 'Schrijnwerk';

    static public function car() {
	    return ProductTag::where('name', '=', self::CAR)->firstOrFail();
    }
    static public function construction() {
	    return ProductTag::where('name', '=', self::CONSTRUCTION)->firstOrFail();
    }
    static public function garden() {
	    return ProductTag::where('name', '=', self::GARDEN)->firstOrFail();
    }
    static public function general() {
	    return ProductTag::where('name', '=', self::GENERAL)->firstOrFail();
    }
    static public function technics() {
	    return ProductTag::where('name', '=', self::TECHNICS)->firstOrFail();
    }
    static public function wood() {
	    return ProductTag::where('name', '=', self::WOOD)->firstOrFail();
    }

    public function items() {
        return $this->belongsToMany('Api\Model\InventoryItem', 'inventory_item_product_tag',
            'product_tag_id', 'inventory_item_id' );
    }

}