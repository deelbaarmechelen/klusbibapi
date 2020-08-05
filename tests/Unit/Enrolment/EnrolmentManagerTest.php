<?php

use PHPUnit\Framework\TestCase;
use Api\Enrolment\EnrolmentManager;

require_once __DIR__ . '/../../test_env.php';


final class EnrolmentManagerTest extends LocalDbWebTestCase
{
    public function getDataSet()
    {
        $this->startdate = new DateTime();
        $this->enddate = clone $this->startdate;
        $this->enddate->add(new DateInterval('P365D'));

        return new \Tests\DbUnitArrayDataSet(array(
            'users' => array(
                array('user_id' => 1, 'firstname' => 'firstname', 'lastname' => 'lastname',
                    'role' => 'admin', 'email' => 'admin@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('user_id' => 2, 'firstname' => 'harry', 'lastname' => 'De Handige',
                    'role' => 'volunteer', 'email' => 'harry@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('user_id' => 3, 'firstname' => 'daniel', 'lastname' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
            ),
            'project_user' => array(
            ),
            'projects' => array(
                array('id' => 1, 'name' => 'STROOM')
            ),
        ));
    }
    public function testGetMembershipEndDate()
    {
        $startDate = "2019-01-01";
        $endDate = EnrolmentManager::getMembershipEndDate($startDate);

        $this->assertEquals("2020-01-01", $endDate);
    }
    public function testGetMembershipEndDateEndOfYear()
    {
        $this->markTestSkipped("need to mock current date!");
        $startDate = "2019-12-02";
        $endDate = EnrolmentManager::getMembershipEndDate($startDate);

        $this->assertEquals("2020-12-31", $endDate);
    }
    public function testGetMembershipEndDateInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid date format (expecting 'YYYY-MM-DD'): 2019/01/01");

        $startDate = "2019/01/01";
        $endDate = EnrolmentManager::getMembershipEndDate($startDate);

        $this->assertEquals("2020-01-01", $endDate);
    }
    public function testConfirmPayment()
    {
        // FIXME: upgrade of phpunit required (and switch to createStub?)
        $user = \Api\Model\User::find(3);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
            ->getMock();
        // should send a confirmation to user
        $mailMgr->expects($this->once())
            ->method('sendEnrolmentPaymentConfirmation')
            ->with($user, \Api\Model\PaymentMode::STROOM);

        $mollieApi = new \Tests\Mock\MollieApiClientMock();
        $user->state = \Api\Model\UserState::CHECK_PAYMENT;
        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi);
        $enrolmentMgr->confirmPayment(\Api\Model\PaymentMode::STROOM, $user);
        $this->assertTrue($user->isStroomParticipant());
    }
}
if (!class_exists('UserTest')) {
    class UserTest extends \Api\Model\User
    {
        protected $table = 'users';
        public $incrementing = false;

        public static function boot()
        {
            parent::boot();
        }

        //['user_id', 'state', 'firstname', 'lastname', 'role', 'email',
        //'membership_start_date', 'membership_end_date', 'birth_date', 'address', 'postal_code', 'city',
        //'phone', 'mobile', 'registration_number', 'payment_mode', 'created_at', 'updated_at'
        public $user_id = 999;
        public $firstname;
        public $lastname;
        public $role;
        public $email;
        public $membership_start_date;
        public $membership_end_date;

        /**
         * Get the connection of the entity.
         *
         * @return string|null
         */
        public function getQueueableConnection()
        {
            // TODO: Implement getQueueableConnection() method.
        }

        /**
         * Retrieve the model for a bound value.
         *
         * @param  mixed $value
         * @return \Illuminate\Database\Eloquent\Model|null
         */
        public function resolveRouteBinding($value, $field = NULL)
        {
            // TODO: Implement resolveRouteBinding() method.
        }
    }
}