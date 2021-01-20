<?php

require_once __DIR__ . '/../AbstractCapsuleSeeder.php';
use Illuminate\Database\Capsule\Manager as Capsule;

class ToolsTableSeeder extends AbstractCapsuleSeeder
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

    	Capsule::table('tools')->insert([
    			'name' => 'tool ' . str_random(10),
    			'description' => 'description of this tool',
    			'link' => null,
    			'category' => 'wood',
    	 ]);
    	     		 
    }
}
