<?php

use Phinx\Seed\AbstractSeed;
use Illuminate\Database\Capsule\Manager as Capsule;

class ProjectsTableSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $project_count = Capsule::table('projects')->where('name', '=', 'STROOM')->count();
        if ($project_count == 0) {
            Capsule::table('projects')->insert([
                'name' => 'STROOM '
            ]);
        }
    }
}
