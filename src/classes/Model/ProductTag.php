<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class ProductTag extends Model
{
    protected $table = 'product_tag';
    protected $primaryKey = "id";
    public $incrementing = false;

    public function items() {
//        return $this->belongsToMany(Role::class, 'role_user');
        return $this->belongsToMany('Api\Model\InventoryItem', 'inventory_item_product_tag',
            'inventory_item_id','product_tag_id' );
    }

}