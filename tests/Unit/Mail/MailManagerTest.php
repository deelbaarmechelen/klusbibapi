<?php
use PHPUnit\Framework\TestCase;
use Api\Mail\MailManager;
use Tests\Mock\PHPMailerMock;
use Api\Model\Contact;
use Api\Model\Tool;
use Api\Model\Reservation;
use Api\Model\Membership;
use Api\Model\Delivery;
use Api\Model\DeliveryItem;
use Tests\DbUnitArrayDataSet;

require_once __DIR__ . '/../../test_env.php';

final class MailManagerTest extends LocalDbWebTestCase
//final class MailManagerTest extends TestCase
{
    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        $this->createdate = new DateTime();
        $this->updatedate = clone $this->createdate;
        $this->pickUpDate = new DateTime();
        $this->dropOffDate = clone $this->pickUpDate;
        $this->dropOffDate->add(new DateInterval('P2D'));

        return new DbUnitArrayDataSet(array(
            'contact' => array(
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                ),
            ),
            'deliveries' => array(
                array('id' => 1, 'user_id' => 3, 'state' => 1,
                    'pick_up_date' => $this->pickUpDate->format('Y-m-d H:i:s'),
                    'drop_off_date' => $this->dropOffDate->format('Y-m-d H:i:s'),
                    'pick_up_address' => "here",
                    'drop_off_address' => "there",
                    'created_at' => $this->createdate->format('Y-m-d H:i:s'),
                    'updated_at' => $this->updatedate->format('Y-m-d H:i:s')
                ),
            ),
            'inventory_item' => array(
                array('id' => 1, 'name' => "my tool", 'item_type' => \Api\Model\ToolType::TOOL,
                    'sku' => "KB-000-20-001", 'brand' => 'Makita',
                    'created_at' => $this->createdate->format('Y-m-d H:i:s'),
                    'updated_at' => $this->updatedate->format('Y-m-d H:i:s')
                ),
                array('id' => 2, 'name' => "my second tool", 'item_type' => \Api\Model\ToolType::ACCESSORY,
                    'sku' => "KB-000-20-002",
                    'created_at' => $this->createdate->format('Y-m-d H:i:s'),
                    'updated_at' => $this->updatedate->format('Y-m-d H:i:s')
                ),
            ),
            'delivery_item' => array(
                array('delivery_id' => 1, 'inventory_item_id' => 1),
            ),
        ));
    }

    public function testSendPwdRecoveryMail()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$token = "12345678901234567890";
		$result = $mailmgr->sendPwdRecoveryMail("11", "Test", "info@klusbib.be", $token);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Paswoord Vergeten", $get_sent->subject);
        print_r($get_sent->body);
	}

	public function testSendEnrolmentNotification()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$newUser = new Contact();
		$newUser->id = 11;
		$newUser->first_Name = "firstName";
		$newUser->last_Name = "lastName";
		
		$result = $mailmgr->sendEnrolmentNotification("info@klusbib.be", $newUser);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Nieuwe inschrijving geregistreerd", $get_sent->subject);
        print_r($get_sent->body);
	}
	public function testSendEmailVerification()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$token = "12345678901234567890";
		$result = $mailmgr->sendEmailVerification("11", "testUser", "info@klusbib.be", $token);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Klusbib - Bevestig email adres", $get_sent->subject);
        print_r($get_sent->body);
	}
	public function testSendReservationRequest()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
        $user = new ContactTest();
        $user->id = 123;
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
		$tool = new ToolTest();
		$tool->name = "mytool";
		$tool->description = "mydescription";
		$tool->brand = "myBrand";
		$tool->type = "myType";
		$reservation = new ReservationTest();
		$reservationStart = new DateTime();
		$reservation->startsAt = new DateTime();
		$reservation->endsAt = clone $reservationStart;
		$reservation->endsAt->add(new DateInterval('P7D'));
		$result = $mailmgr->sendReservationRequest("info@klusbib.be", $user, $tool, $reservation);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Nieuwe reservatie", $get_sent->subject);
        print_r($get_sent->body);
	}
    public function testSendDeliveryRequest()
    {
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $user = new ContactTest();
        $user->id = 123;
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $tool = new ToolTest();
        $tool->name = "mytool";
        $tool->description = "mydescription";
        $tool->brand = "myBrand";
        $tool->type = "myType";
        $reservation = new ReservationTest();
        $reservationStart = new DateTime();
        $reservation->startsAt = new DateTime();
        $reservation->endsAt = clone $reservationStart;
        $reservation->endsAt->add(new DateInterval('P7D'));

        $delivery = Delivery::factory(Delivery::class)->create(['comment' => 'opm', 'consumers' => 'hamer+beitel']);
//        $items = factory(DeliveryItem::class, 2)->create(['delivery_id' => $delivery->id, 'inventory_item_id' => 1]);
        $items = DeliveryItem::factory()->count(2)->create(['delivery_id' => $delivery->id, 'inventory_item_id' => 1]);

        $result = $mailmgr->sendDeliveryRequestNotification("info@klusbib.be", $delivery, $user);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Nieuwe leveringsaanvraag", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendDeliveryUpdate()
    {
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);

        $user = Contact::factory()->create([
            'email' => "info@klusbib.be", 'first_name' => "tester", 'last_name' => "de mock"
        ]);
//        $user = factory(User::class)->create([
//            'email' => "info@klusbib.be", 'first_name' => "tester", 'last_name' => "de mock"
//        ]);
        $tool = Tool::factory()->create([
            'name' => "mytool", 'description' => "mydescription", 'brand' => "myBrand", 'type' => "myType"
        ]);
        $reservationStart = new DateTime();
        $reservationEnd = clone $reservationStart;
        $reservationEnd->add(new DateInterval('P7D'));
        $reservation = Reservation::factory()->create([
            'startsAt' => $reservationStart, 'endsAt' => $reservationEnd, 'tool_id' => $tool->tool_id, 'user_id' => $user->id
        ]);
        $delivery = Delivery::factory()->create(['comment' => 'opm', 'consumers' => 'hamer+beitel']);
        $items = DeliveryItem::factory()->count(2)->create([
            'delivery_id' => $delivery->id, 'inventory_item_id' => 1, 'reservation_id' => $reservation->reservation_id
        ]);

        $result = $mailmgr->sendDeliveryUpdateNotification("info@klusbib.be", $delivery, $user);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Wijziging levering/ophaling", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendEnrolmentConfirmationTransfer()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $membership = new MembershipTest();
        $membership->subscription_id = 1;
        $membership->expires_at = new DateTime();
        $user->activeMembership = $membership;
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendEnrolmentConfirmation($user, \Api\Model\PaymentMode::TRANSFER);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib inschrijving", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendEnrolmentConfirmationMollie()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->email_state = \Api\Model\EmailState::CONFIRM_EMAIL;
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendEnrolmentConfirmation($user, \Api\Model\PaymentMode::MOLLIE);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib inschrijving", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendEnrolmentConfirmationStroom()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendEnrolmentConfirmation($user, \Api\Model\PaymentMode::STROOM);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib inschrijving", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendEnrolmentPaymentConfirmationTransfer()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendEnrolmentPaymentConfirmation($user, \Api\Model\PaymentMode::TRANSFER);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib bevestiging inschrijving", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendRenewal()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $membership = new MembershipTest();
        $membership->subscription_id = 1;
        $membership->expires_at = new DateTime();
        $user->activeMembership = $membership;

        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendRenewal($user, -7, 'mytoken');
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib lidmaatschap", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendRenewalConfirmation()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "tester";
        $user->last_name = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $membership = new MembershipTest();
        $membership->expires_at = new DateTime();
        $membership->subscription_id = 1;
        $user->activeMembership = $membership;

        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendRenewalConfirmation($user, \Api\Model\PaymentMode::TRANSFER, true);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib hernieuwing lidmaatschap", $get_sent->subject);
        print_r($get_sent->body);
    }

    public function testSendNewGeneralConditionsNotification()
    {
        $this->markTestSkipped('requires webpage to be started.');
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "mijnNaam";
        $user->last_name = "mijnFamilieNaam";
        $user->membership_end_date = date('Y-m-d');
        $user->membership_end_date = new DateTime();
        $user->membership_end_date = $user->membership_end_date->setDate(2018, 12, 7)->format('Y-m-d');

        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendNewGeneralConditionsNotification($user);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Aanpassing Klusbib afspraken", $get_sent->subject);
        print_r($get_sent->body);
    }

    public function testSendEnrolmentStroomNotification()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "mijnNaam";
        $user->last_name = "mijnFamilieNaam";
        $user->state = \Api\Model\UserState::CHECK_PAYMENT;
        $user->address = "Mijnthuisstraat 123";
        $user->postal_code = "2800";
        $user->city = "Mechelen";
        $user->membership_end_date = new DateTime();
        $user->membership_end_date = $user->membership_end_date->setDate(2018, 12, 7)->format('Y-m-d');

        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendEnrolmentStroomNotification('stroom@klusbib.be', $user, false);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Inschrijving via STROOM project", $get_sent->subject);
        print_r($get_sent->body);
    }
    public function testSendEnrolmentRenewalStroomNotification()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "mijnNaam";
        $user->last_name = "mijnFamilieNaam";
        $user->state = \Api\Model\UserState::CHECK_PAYMENT;
        $user->address = "Mijnthuisstraat 123";
        $user->postal_code = "2800";
        $user->city = "Mechelen";
        $user->membership_end_date = new DateTime();
        $user->membership_end_date = $user->membership_end_date->setDate(2018, 12, 7)->format('Y-m-d');

        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendEnrolmentStroomNotification('stroom@klusbib.be', $user, true);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Verlenging via STROOM project", $get_sent->subject);
        print_r($get_sent->body);
    }

    public function testSendUsersReport()
    {
        $user = new ContactTest();
        $user->email = "info@klusbib.be";
        $user->first_name = "mijnNaam";
        $user->last_name = "mijnFamilieNaam";
        $user->membership_end_date = date('Y-m-d');
        $user->membership_end_date = new DateTime();
        $user->membership_end_date = $user->membership_end_date->setDate(2018, 12, 7)->format('Y-m-d');

        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $active_users = array($user);
        $expired_users =array($user);
        $pending_users =array($user);
        $result = $mailmgr->sendUsersReport($active_users, $expired_users, $pending_users);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Overzicht Klusbib leden", $get_sent->subject);
        print_r($get_sent->body);
    }
}

if (!class_exists('ContactTest')) {
    // FIXME: to be replaced by User::find() call? (cfr EnrolmentManagerTest)
    class ContactTest extends Contact {
        protected $table = 'users';
        public $incrementing = false;
        //['user_id', 'state', 'first_name', 'last_name', 'role', 'email',
        //'membership_start_date', 'membership_end_date', 'birth_date', 'address_line_1', 'postal_code', 'city',
        //'telephone', 'mobile', 'registration_number', 'payment_mode', 'created_at', 'updated_at'
        public $id = 999;
        public $first_name;
        public $last_name;
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
        public function resolveRouteBinding($value, $field = null)
        {
            // TODO: Implement resolveRouteBinding() method.
        }
    }
}
if (!class_exists('ToolTest')) {
    class ToolTest extends Tool
    {
        //	'tool_id', 'name', 'description', 'category', 'img', 'created_at', 'updated_at',
        //	'brand', 'type', 'serial', 'manufacturing_year', 'manufacturer_url', 'doc_url', 'code', 'owner_id', 'reception_date',
        //	'state', 'visible'
        public $tool_id = 456;
        public $name;
        public $description;
        public $category;
        public $img;
        public $brand;
        public $type;
        public $code;

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
if (!class_exists('ReservationTest')) {
    class ReservationTest extends Reservation
    {
        //'reservation_id', 'user_id', 'tool_id', 'state', 'startsAt', 'endsAt',
        //'type', 'comment', 'created_at', 'updated_at'
        public $reservation_id = 999;
        public $user_id;
        public $tool_id;
        public $state;
        public $startsAt;
        public $endsAt;
        public $type;
        public $comment;

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
if (!class_exists('MembershipTest')) {
    class MembershipTest extends Membership
    {
        //'id', 'status', 'start_at', 'expires_at', 'subscription_id', 'contact_id',
        // 'last_payment_mode', 'comment', 'created_at', 'updated_at', 'deleted_at
        public $id = 999;
        public $status;
        public $start_at;
        public $expires_at;
        public $subscription_id;
        public $contact_id;
        public $last_payment_mode;
        public $comment;

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

