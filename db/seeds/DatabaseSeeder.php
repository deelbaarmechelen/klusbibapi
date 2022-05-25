<?php

use Phinx\Seed\AbstractSeed;
use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';

class DatabaseSeeder extends AbstractSeed
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
    	echo "Running DatabaseSeeder";
        $this->call(ToolsTableSeeder::class);
    	$this->call(ReservationTableSeeder::class);
    }
}
