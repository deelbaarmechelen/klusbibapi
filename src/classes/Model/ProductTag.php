<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class ProductTag extends Model
{
    protected $table = 'product_tag';
    protected $primaryKey = "id";
    public $incrementing = false;

    public function items() {
        return $this->belongsToMany('Api\Model\InventoryItem', 'inventory_item_product_tag',
            'product_tag_id', 'inventory_item_id' );
    }

}