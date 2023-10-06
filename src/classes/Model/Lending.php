<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lending extends Model
{
    protected $table = 'kb_lendings';
    protected $primaryKey = "lending_id";
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
//    protected $dateFormat = 'Y-m-d'; -> creates InvalidArgumentException in Carbon lib on created_at / updated_at fields
    static protected $fieldArray = ['lending_id', 'start_date', 'due_date', 'returned_date', 'tool_id', 'tool_type',
        'user_id', 'comments', 'created_by', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Lending::$fieldArray);
    }

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date', 'due_date', 'returned_date', 'created_at', 'updated_at'
    ];

    /**
     * Get the user that owns the lending.
     */
    public function user()
    {
        return $this->belongsTo('Api\Model\Contact', 'user_id');
    }

    /**
     * Get the tool that owns the lending. (not tested yet)
     */
    private function tool()
    {
        return $this->belongsTo('Api\Model\Tool', 'tool_id')->where('tool_type', '=', ToolType::TOOL);
    }

    /**
     * Get the accessory that owns the lending. (not tested yet)
     */
    private function accessory()
    {
        return $this->belongsTo('Api\Model\Accessory', 'tool_id')->where('tool_type', '=', ToolType::ACCESSORY);
    }

    public function deliveryItem()
    {
        return $this->hasOne('Api\Model\DeliveryItem', 'lending_id');
    }

    public function scopeValid($query)
    {
        return $query->whereNotNull('user_id')
            ->whereNotNull('tool_id')
            ->whereNotNull('tool_type')
            ->whereNotNull('start_date');
    }
    public function scopeActive($query)
    {
        return $query->whereNull('returned_date');
    }
    public function scopeOverdue($query)
    {
        return $query->whereNull('returned_date')
            ->where('due_date', '<', new \DateTime('now', new \DateTimeZone("UTC")));
    }
    public function scopeWithStartDate($query, $startDate)
    {
        return $query->where('start_date', '=', $startDate);
    }
    public function scopeWithUser($query, $userId)
    {
        return $query->where('user_id', '=', $userId);
    }

    public function scopeWithTool($query, $toolId, $toolType = ToolType::TOOL)
    {
        return $query->where('tool_id', '=', $toolId)
            ->where('tool_type', '=', $toolType);
    }
    public function scopeStartBefore($query, $startDate)
    {
        return $query->where('start_date', '<', $startDate);
    }
    public function scopeStartFrom($query, $startDate)
    {
        return $query->where('start_date', '>=', $startDate);
    }
    public function scopeReturnBefore($query, $startDate)
    {
        return $query->where('returned_date', '<', $startDate);
    }
    public function scopeReturnFrom($query, $startDate)
    {
        return $query->where('returned_date', '>=', $startDate);
    }
    public function scopeInMonth($query, $month)
    {
        return $query->whereMonth('start_date', '=', $month);
    }
    public function scopeInYear($query, $year)
    {
        return $query->whereYear('start_date', '=', $year);
    }
    public function scopeReturnedInMonth($query, $month)
    {
        return $query->whereMonth('returned_date', '=', $month);
    }
    public function scopeReturnedInYear($query, $year)
    {
        return $query->whereYear('returned_date', '=', $year);
    }
    public function scopeStroom($query)
    {
        return $query->whereHas('user', function (Builder $query) {
            $query->whereHas('projects', function (Builder $query) {
                $query->where('name', '=', 'STROOM');
            });
        });
    }
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }
}