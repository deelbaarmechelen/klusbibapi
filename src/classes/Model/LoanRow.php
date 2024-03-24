<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;
use Api\Model\ItemMovement;
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
 * @property Loan $loan
 */
class LoanRow extends Model
{
    public const LOCATION_UNKNOWN = 0;
    public const LOCATION_ON_LOAN = 1;
	public const LOCATION_IN_STOCK = 2;
    public const LOCATION_REPAIR = 3;

    protected $table = 'loan_row';
    protected $primaryKey = "id";
    public $incrementing = true;

    //protected $fillable = [];
	static protected $fieldArray = ['id', 'loan_id', 'inventory_item_id', 'product_quantity', 'due_in_at', 'due_out_at', 'checked_out_at', 'checked_in_at',
        'fee', 'site_from', 'site_to', 'deposit_id', 'item_location'
	];
    public $timestamps = false;

    public function addMovement() {
        if ($this->checked_out_at == null) {
            // not checked out, no movement to be created
            return;
        }
        $location = null;
        if ($this->checked_in_at == null) {
            $location = LoanRow::LOCATION_ON_LOAN;
        } else {
            $location = LoanRow::LOCATION_IN_STOCK;
        }
        if ($this->itemMovement != null) {
            $this->itemMovement->inventory_location_id = $location;
            $this->itemMovement->save();
        } else {
            $movement = new ItemMovement();
            $movement->inventory_item_id = $this->inventory_item_id;
            $movement->assigned_to_contact_id = $this->loan->contact_id;
            $movement->quantity = 1;
            $movement->inventory_location_id = $location;
            $this->itemMovement()->save($movement);
        }
    }
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
		return $this->belongsTo(\Api\Model\Loan::class, 'loan_id');
	}
	public function inventoryItem()
	{
		return $this->belongsTo(\Api\Model\InventoryItem::class, 'inventory_item_id');
	}
    public function itemMovement() {
        return $this->hasOne(ItemMovement::class, 'loan_row_id', 'id');
    }
}
