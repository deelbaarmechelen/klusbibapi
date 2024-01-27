<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 * @property mixed $created_by
 * @property mixed $contact_id
 * @property mixed $loan_id
 * @property mixed $inventory_item_id
 * @property mixed $created_at
 * @property mixed $text
 * @property mixed $admin_only
 */
class Note extends Model
{
    protected $table = 'note';
    protected $primaryKey = "id";
    public $incrementing = true;

    //protected $fillable = [];
	static protected $fieldArray = ['id', 'created_by', 'contact_id', 'loan_id', 'inventory_item_id',
        'created_at', 'text', 'admin_only'
	];
	const UPDATED_AT = null;
    public $timestamps = true;

	public function loan()
	{
		return $this->belongsTo('Api\Model\Loan', 'loan_id');
	}
	public function contact()
	{
		return $this->belongsTo('Api\Model\Contact', 'contact_id');
	}

}
