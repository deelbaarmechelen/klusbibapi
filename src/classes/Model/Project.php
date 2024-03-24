<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 * @method static Builder where($arr)
 * @method static Builder where($col, $value)
 */
class Project extends Model
{
    protected $table = 'kb_projects';
//    protected $primaryKey = "id";
    public $incrementing = true;

    static protected $fieldArray = ['id', 'name', 'info', 'created_at', 'updated_at'
    ];

    /**
     * The projects this user participates to.
     */
    public function users()
    {
        return $this->belongsToMany(\Api\Model\Contact::class, 'kb_project_user', 'project_id','user_id')
            ->withTimestamps();
    }

    public function stroom() {
        return Project::where('name', 'STROOM')->first();
    }
}