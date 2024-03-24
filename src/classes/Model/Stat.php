<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder where(...$params)
 * @method static Model|\Illuminate\Database\Eloquent\Collection|null find($id, $columns = ['*'])
 * @method static Model|\Illuminate\Database\Eloquent\Collection findOrFail($id, $columns = ['*'])
 * @method static Model firstOrCreate(array $attributes = [], array $values = [])
 */
class Stat extends Model
{
    protected $table = 'kb_stats';
    protected $primaryKey = "id";
    protected $fillable = ['name', 'version'];

    public $timestamps = true;
}