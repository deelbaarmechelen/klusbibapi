<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
//    protected $primaryKey = "id";
    public $incrementing = true;

    static protected $fieldArray = ['id', 'name', 'info', 'created_at', 'updated_at'
    ];

    /**
     * The projects this user participates to.
     */
    public function users()
    {
        return $this->belongsToMany('Api\Model\User', 'project_user', 'project_id','user_id')
            ->withTimestamps();
    }

    public function stroom() {
        return Project::where('name', 'STROOM')->first();
    }
}