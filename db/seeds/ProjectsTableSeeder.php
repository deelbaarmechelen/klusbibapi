<?php

require_once __DIR__ . '/../AbstractCapsuleSeeder.php';
use Illuminate\Database\Capsule\Manager as Capsule;

class ProjectsTableSeeder extends AbstractCapsuleSeeder
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
        $this->initCapsule();
        $project_count = Capsule::table('projects')->where('name', '=', 'STROOM')->count();
        if ($project_count == 0) {
            Capsule::table('projects')->insert([
                'name' => 'STROOM'
            ]);
            Capsule::table('projects')->insert([
                'name' => 'Delivery'
            ]);

        }
    }
}
