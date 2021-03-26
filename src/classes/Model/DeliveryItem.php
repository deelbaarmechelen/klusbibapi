<?php

namespace Api\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DeliveryItem extends Pivot
{
    public function delivery()
    {
        return $this->belongsTo('Api\Model\Delivery');
    }
}