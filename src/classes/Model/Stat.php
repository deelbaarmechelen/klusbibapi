<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    protected $table = 'kb_stats';
    protected $primaryKey = "id";
    protected $fillable = ['name', 'version'];

    public $timestamps = true;
}