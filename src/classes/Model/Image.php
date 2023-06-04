<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'image';
    protected $primaryKey = "id";
    public $incrementing = false;

    protected $fillable = ['image_name'];
    public $timestamps = false;
}