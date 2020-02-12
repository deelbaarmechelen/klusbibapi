<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Lending extends Model
{
    protected $primaryKey = "lending_id";
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
//    protected $dateFormat = 'Y-m-d'; -> creates InvalidArgumentException in Carbon lib on created_at / updated_at fields
    static protected $fieldArray = ['lending_id', 'start_date', 'due_date', 'returned_date', 'tool_id',
        'user_id', 'comments', 'created_by', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Tool::$fieldArray);
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
}