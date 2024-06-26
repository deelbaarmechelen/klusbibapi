<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder where(...$params)
 */
class ProductTag extends Model
{
    protected $table = 'product_tag';
    protected $primaryKey = "id";
    public $incrementing = false;

    public const CONSTRUCTION = 'Bouw';
    public const GARDEN = 'Tuin';
    public const GENERAL = 'Algemeen';
    public const TECHNICS = 'Techniek';
    public const WOOD = 'Schrijnwerk';

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
        return $this->belongsToMany(\Api\Model\InventoryItem::class, 'inventory_item_product_tag',
            'product_tag_id', 'inventory_item_id' );
    }

}