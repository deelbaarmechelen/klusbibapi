<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $table = 'loan';
    protected $primaryKey = "id";
    public $incrementing = false;

    //protected $fillable = [];
	static protected $fieldArray = ['id', 'contact_id', 'created_by', 'status', 'datetime_out', 'datetime_in', 'reference', 'total_fee',
        'created_at_site', 'collect_from', 'created_at'
	];
    const UPDATED_AT = null;
    public $timestamps = true;
}