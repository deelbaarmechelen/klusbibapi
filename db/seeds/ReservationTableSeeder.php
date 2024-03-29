<?php

require_once __DIR__ . '/../AbstractCapsuleSeeder.php';
use Illuminate\Database\Capsule\Manager as Capsule;

class ReservationTableSeeder extends AbstractCapsuleSeeder
{
    public function getDependencies()
    {
        return [
            'UsersTableSeeder',
            'ToolsTableSeeder'
        ];
    }
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

        if (!Capsule::table('kb_reservations')->where('user_id', '=', 1)->exists() ) {
            $startdate = new DateTime();
            $enddate = clone $startdate;
            $enddate->add(new DateInterval('P7D'));
            Capsule::table('kb_reservations')->insert([
                'tool_id' => 1,
                'user_id' => 1,
                'title' => 'reservation ' . str_random(10),
                'startsAt' => $startdate,
                'endsAt' => $enddate,
                'type' => 'reservation',
            ]);
            $startdatemaint = new DateTime();
            $startdatemaint->add(new DateInterval('P14D'));
            $enddatemaint = clone $startdatemaint;
            $enddatemaint->add(new DateInterval('P7D'));
            Capsule::table('kb_reservations')->insert([
                'tool_id' => 1,
                'user_id' => 1,
                'title' => 'repair ' . str_random(10),
                'startsAt' => $startdatemaint,
                'endsAt' => $enddatemaint,
                'type' => 'maintenance',
            ]);
        }
    }
}
