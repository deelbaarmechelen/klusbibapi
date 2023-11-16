<?php

require_once __DIR__ . '/../AbstractCapsuleSeeder.php';
use Illuminate\Database\Capsule\Manager as Capsule;

class UsersTableSeeder extends AbstractCapsuleSeeder
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

        $startdate = new DateTime();
        $enddate = clone $startdate;
        $enddate->add(new DateInterval('P7D'));
        if (!Capsule::table('contact')->where('id', '=', 1)->exists() ) {
            Capsule::table('contact')->insert([
                'id' => 1,
                'first_name' => 'admin',
                'last_name' => 'admin',
                'role' => 'admin',
                'email' => 'admin@klusbib.be',
                'state' => 'ACTIVE',
                'email_state' => 'CONFIRMED',
                'password' => password_hash("test", PASSWORD_DEFAULT),
                'membership_start_date' => $startdate,
                'membership_end_date' => $enddate,
                'created_at' => $startdate,
                'updated_at' => $startdate
            ]);
        }
        if (!Capsule::table('contact')->where('id', '=', 2)->exists() ) {
            Capsule::table('contact')->insert([
                'id' => 2,
                'first_name' => 'Jef',
                'last_name' => 'De Bouwer',
                'role' => 'member',
                'email' => 'jef@test.klusbib.be',
                'state' => 'ACTIVE',
                'email_state' => 'CONFIRMED',
                'password' => password_hash("test", PASSWORD_DEFAULT),
                'membership_start_date' => $startdate,
                'membership_end_date' => $enddate,
                'created_at' => $startdate,
                'updated_at' => $startdate
            ]);
        }
    }

//    public function run()
//    {
//        $data = [
//            [
//                'body'    => 'foo',
//                'created' => date('Y-m-d H:i:s'),
//            ],
//            [
//                'body'    => 'bar',
//                'created' => date('Y-m-d H:i:s'),
//            ]
//        ];
//
//        $posts = $this->table('posts');
//        $posts->insert($data)
//            ->saveData();
//
//        // empty the table
//        $posts->truncate();
//    }
}
