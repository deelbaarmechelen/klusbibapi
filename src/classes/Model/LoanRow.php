<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;
/**
 * @property mixed $id
 * @property mixed $loan_id
 * @property mixed $inventory_item_id
 * @property mixed $product_quantity
 * @property mixed $due_in_at
 * @property mixed $due_out_at
 * @property mixed $checked_out_at
 * @property mixed $checked_in_at
 * @property mixed $fee
 * @property mixed $site_from
 * @property mixed $site_to
 * @property mixed $deposit_id
 * @property mixed $item_location
 */
class LoanRow extends Model
{
    protected $table = 'loan_row';
    protected $primaryKey = "id";
    public $incrementing = true;

    //protected $fillable = [];
	static protected $fieldArray = ['id', 'loan_id', 'inventory_item_id', 'product_quantity', 'due_in_at', 'due_out_at', 'checked_out_at', 'checked_in_at',
        'fee', 'site_from', 'site_to', 'deposit_id', 'item_location'
	];
    public $timestamps = false;


    public function addNote($text) {
        $note = new Note();
        $note->contact_id = $this->loan->contact_id;
        $note->inventory_item_id = $this->inventory_item_id;
        $note->text = $text;
        $note->admin_only = 1;
        $this->loan->notes()->save($note);
    }
	public function loan()
	{
		return $this->belongsTo('Api\Model\Loan', 'loan_id');
	}
	public function inventoryItem()
	{
		return $this->belongsTo('Api\Model\InventoryItem', 'inventory_item_id');
	}
}
