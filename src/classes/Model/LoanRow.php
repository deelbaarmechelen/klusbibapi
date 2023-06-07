<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class LoanRow extends Model
{
    protected $table = 'loan_row';
    protected $primaryKey = "id";
    public $incrementing = false;

    //protected $fillable = [];
	static protected $fieldArray = ['id', 'loan_id', 'inventory_item_id', 'product_quantity', 'due_in_at', 'due_out_at', 'checked_out_at', 'checked_in_at',
        'fee', 'site_from', 'site_to', 'deposit_id', 'item_location'
	];
    public $timestamps = false;
}
