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
        $this->expiredStartDate = clone $this->startdate;
        $this->expiredStartDate->sub(new DateInterval('P20D'));
        $this->expiredEndDate = clone $this->enddate;
        $this->expiredEndDate->sub(new DateInterval('P20D'));
        $this->acceptTermsDate = clone $this->startdate;
        $this->acceptTermsDate->sub(new DateInterval('P1M'));

        return new \Tests\DbUnitArrayDataSet(array(
            'membership_type' => array(
                array('id' => 1, 'name' => 'Regular', 'price' => 30,
                    'duration' => 365,
                    'self_serve' => 1,
                    'is_active' => 1,
                    'max_items' => 5,
                    'next_subscription_id' => 3
                ),
                array('id' => 2, 'name' => 'Temporary', 'price' => 0,
                    'duration' => 60,
                    'self_serve' => 0,
                    'is_active' => 1,
                    'max_items' => 5,
                    'next_subscription_id' => 1
                ),
                array('id' => 3, 'name' => 'Renewal', 'price' => 20,
                    'duration' => 365,
                    'self_serve' => 0,
                    'is_active' => 1,
                    'max_items' => 5,
                    'next_subscription_id' => null
                ),
                array('id' => 4, 'name' => 'Stroom', 'price' => 0,
                    'duration' => 365,
                    'self_serve' => 0,
                    'is_active' => 1,
                    'max_items' => 5,
                    'next_subscription_id' => 3
                ),
                array('id' => 5, 'name' => 'RegularOrg', 'price' => 0,
                    'duration' => 365,
                    'self_serve' => 1,
                    'is_active' => 1,
                    'max_items' => 5,
                    'next_subscription_id' => 6
                ),
                array('id' => 6, 'name' => 'RenewalOrg', 'price' => 0,
                    'duration' => 365,
                    'self_serve' => 0,
                    'is_active' => 1,
                    'max_items' => 5,
                    'next_subscription_id' => 6
                ),
            ),
            'contact' => array(
                array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
                    'role' => 'admin', 'email' => 'admin@klusbib.be', 'state' => \Api\Model\UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
                    'active_membership' => 1
                ),
                array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
                    'role' => 'volunteer', 'email' => 'harry@klusbib.be', 'state' => \Api\Model\UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
                    'active_membership' => 2
                ),
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => \Api\Model\UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
                    'active_membership' => 6
                ),
                array('id' => 4, 'first_name' => 'nele', 'last_name' => 'HippeDame',
                    'role' => 'member', 'email' => 'nele@klusbib.be', 'state' => \Api\Model\UserState::EXPIRED,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->expiredStartDate->format('Y-m-d'),
                    'membership_end_date' => $this->expiredEndDate->format('Y-m-d'),
                    'active_membership' => 3
                ),
                array('id' => 5, 'first_name' => 'tom', 'last_name' => 'DoetMee',
                    'role' => 'member', 'email' => 'tom@klusbib.be', 'state' => \Api\Model\UserState::CHECK_PAYMENT,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->expiredStartDate->format('Y-m-d'),
                    'membership_end_date' => $this->expiredEndDate->format('Y-m-d'),
                    'active_membership' => 5
                ),
                array('id' => 6, 'first_name' => 'newt', 'last_name' => 'NewUser',
                    'role' => 'member', 'email' => 'tom@klusbib.be', 'state' => \Api\Model\UserState::CHECK_PAYMENT,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => null,
                    'membership_end_date' => null,
                    'active_membership' => null
                ),
            ),
            'membership' => array(
                array('id' => 1, 'subscription_id' => 1, 'contact_id' => 1,
                    'status' => 'ACTIVE',
                    'last_payment_mode' => \Api\Model\PaymentMode::CASH,
                    'starts_at' => $this->startdate->format('Y-m-d H:i:s'),
                    'expires_at' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 2, 'subscription_id' => 1, 'contact_id' => 2,
                    'status' => 'ACTIVE',
                    'last_payment_mode' => \Api\Model\PaymentMode::STROOM,
                    'starts_at' => $this->startdate->format('Y-m-d H:i:s'),
                    'expires_at' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 3, 'subscription_id' => 1, 'contact_id' => 4,
                    'status' => 'PENDING',
                    'last_payment_mode' => \Api\Model\PaymentMode::MOLLIE,
                    'starts_at' => $this->expiredStartDate->format('Y-m-d'),
                    'expires_at' => $this->expiredEndDate->format('Y-m-d')
                ),
                // membership renewal - not yet confirmed
                array('id' => 4, 'subscription_id' => 3, 'contact_id' => 4,
                    'status' => 'PENDING',
                    'last_payment_mode' => \Api\Model\PaymentMode::MOLLIE,
                    'starts_at' => $this->expiredStartDate->format('Y-m-d'),
                    'expires_at' => $this->expiredEndDate->format('Y-m-d')
                ),
                // membership new mollie enrolment - not yet confirmed
                array('id' => 5, 'subscription_id' => 1, 'contact_id' => 5,
                    'status' => 'PENDING',
                    'last_payment_mode' => \Api\Model\PaymentMode::MOLLIE,
                    'starts_at' => $this->expiredStartDate->format('Y-m-d'),
                    'expires_at' => $this->expiredEndDate->format('Y-m-d')
                ),
                array('id' => 6, 'subscription_id' => 1, 'contact_id' => 3,
                    'status' => 'ACTIVE',
                    'last_payment_mode' => \Api\Model\PaymentMode::STROOM,
                    'starts_at' => $this->startdate->format('Y-m-d H:i:s'),
                    'expires_at' => $this->enddate->format('Y-m-d H:i:s')
                ),
            ),
            'kb_payments' => array(
                array('payment_id' => 1, 'user_id' => 1, 'membership_id' => 1,
                    'state' => \Api\Model\PaymentState::SUCCESS,
                    'mode' => \Api\Model\PaymentMode::CASH,
                    'order_id' => 'orderId1-20200901',
                    'amount' => 30,
                    'currency' => 'EUR'
                ),
                array('payment_id' => 2, 'user_id' => 3, 'membership_id' => 6,
                    'state' => \Api\Model\PaymentState::OPEN,
                    'mode' => \Api\Model\PaymentMode::STROOM,
                    'order_id' => 'orderId3-20200901',
                    'amount' => 0,
                    'currency' => 'EUR'
                ),
                // Payment created during enrolment request with Mollie for a renewal
                array('payment_id' => 3, 'user_id' => 4, 'membership_id' => 4,
                    'state' => \Api\Model\PaymentState::OPEN,
                    'mode' => \Api\Model\PaymentMode::MOLLIE,
                    'order_id' => 'tr_123-paymentId',
                    'amount' => 20,
                    'currency' => 'EUR'
                ),
                // Payment created during enrolment request with Mollie for a first enrolment
                array('payment_id' => 4, 'user_id' => 5, 'membership_id' => 5,
                    'state' => \Api\Model\PaymentState::OPEN,
                    'mode' => \Api\Model\PaymentMode::MOLLIE,
                    'order_id' => 'tr_456-paymentId',
                    'amount' => 30,
                    'currency' => 'EUR'
                ),
            ),
            'kb_project_user' => array(
            ),
            'kb_projects' => array(
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
    public function testRenewalByTransfer() {
        // FIXME: upgrade of phpunit required (and switch to createStub?)
        $user = \Api\Model\Contact::find(3);

        echo 'user=' . \json_encode($user);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
        ->getMock();
        // should send a confirmation to user
        $mailMgr->expects($this->once())
            ->method('sendRenewalConfirmation')
            ->with($user, \Api\Model\PaymentMode::TRANSFER);

        $mollieApi = new \Tests\Mock\MollieApiClientMock();
        $user->state = \Api\Model\UserState::ACTIVE;
        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi);
        $orderId = "order123";
        $enrolmentMgr->renewalByTransfer($orderId);
//
//        $user = \Api\Model\Contact::find($user->id);
//        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE,  $user->activeMembership->status); // original membership unchanged
//
//        // lookup newly created payment
//        $payment = \Api\Model\Payment::where([
//            ['order_id', '=', $orderId],
//            ['user_id', '=', $user->id],
//            ['mode', '=', \Api\Model\PaymentMode::TRANSFER],
//        ])->first();
//        $this->assertNotEmpty($payment);
//        $renewalMembership = $payment->membership;
//
//        $this->assertEquals(\Api\Model\MembershipState::STATUS_PENDING,  $renewalMembership->status);
//        $this->assertEquals(\Api\Model\PaymentMode::TRANSFER,  $renewalMembership->last_payment_mode);
    }
    public function testRenewalByTransferDone() {
        $user = \Api\Model\Contact::find(3);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $logger->expects($this->any())->method('info');
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
            ->getMock();
        // should send a confirmation to user
        $mailMgr->expects($this->once())
            ->method('sendRenewalConfirmation')
            ->with($user, \Api\Model\PaymentMode::TRANSFER);

        $mollieApi = new \Tests\Mock\MollieApiClientMock();
        $user->state = \Api\Model\UserState::ACTIVE;
        $inventory = $this->createMock(\Api\Inventory\Inventory::class); // inventory stub
        $userMgr = new \Api\User\UserManager($inventory, $logger);
        $originalMembershipId = $user->activeMembership->id; // save id of original membership

        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi, $userMgr);
        $orderId = "order123";
        $enrolmentMgr->renewalByTransfer($orderId, true);

        $user = \Api\Model\Contact::find($user->id);
        $originalMembership = \Api\Model\Membership::find($originalMembershipId);
        $this->assertEquals(\Api\Model\MembershipState::STATUS_EXPIRED,  $originalMembership->status); // original membership expired
        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE,  $user->activeMembership->status); // current membership active

        // lookup newly created payment
        $payment = \Api\Model\Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $user->id],
            ['mode', '=', \Api\Model\PaymentMode::TRANSFER],
        ])->first();
        $this->assertNotEmpty($payment);
        $renewalMembership = $payment->membership;
        echo \json_encode($renewalMembership);
        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE, $renewalMembership->status);
        $this->assertEquals(\Api\Model\PaymentMode::TRANSFER, $renewalMembership->last_payment_mode);
    }

    public function testRenewalByMollie() {
        // FIXME: upgrade of phpunit required (and switch to createStub?)
        $user = \Api\Model\Contact::find(3);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
            ->getMock();
        // No confirmation to user for mollie payments?
//        $mailMgr->expects($this->once())
//            ->method('sendRenewalConfirmation')
//            ->with($user, \Api\Model\PaymentMode::MOLLIE);

        $mollieApi = new \Tests\Mock\MollieApiClientMock();
        $user->state = \Api\Model\UserState::ACTIVE;
        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi);
        $orderId = "order123";
        $redirectUrl = "http://localhost/redirect";
        $requestUri = new \Slim\Http\Uri(new \Slim\Psr7\Uri("http", "localhost", 8080, "redirect"));
        $enrolmentMgr->renewalByMollie($orderId, $redirectUrl, \Api\Model\PaymentMode::MOLLIE, $requestUri);
        $user = \Api\Model\Contact::find($user->id);
        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE,  $user->activeMembership->status); // unchanged status (FIXME: test with an expired account to assert there is no update?)

        // lookup newly created payment
        $payment = \Api\Model\Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $user->id],
            ['mode', '=', \Api\Model\PaymentMode::MOLLIE],
        ])->first();
        $this->assertNotEmpty($payment);
        $renewalMembership = $payment->membership;

        $this->assertEquals(\Api\Model\MembershipState::STATUS_PENDING, $renewalMembership->status);
        // check newly created renewal membership can be linked to our user
        $this->assertEquals($user->id,  $renewalMembership->contact_id);
        $this->assertEquals(\Api\Model\PaymentMode::MOLLIE,  $renewalMembership->last_payment_mode);
    }
    public function testEnrolmentByStroom() {
        $user = \Api\Model\Contact::find(6);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
        ->getMock();
        // should send a confirmation to user
        $mailMgr->expects($this->once())
            ->method('sendEnrolmentConfirmation')
            ->with($user, \Api\Model\PaymentMode::STROOM);

        $mollieApi = new \Tests\Mock\MollieApiClientMock(); // dummy, should not be called
        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi);
        $orderId = "order123";
        $enrolmentMgr->enrolmentByStroom($orderId);
        $membership = $user->memberships()->pending()->where('last_payment_mode', \Api\Model\PaymentMode::STROOM)->first();
        $user = \Api\Model\Contact::find($user->id);
        $this->assertEquals(\Api\Model\MembershipState::STATUS_PENDING,  $membership->status);

        // lookup newly created payment
        $payment = \Api\Model\Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $user->id],
            ['mode', '=', \Api\Model\PaymentMode::STROOM],
        ])->first();
        $this->assertNotEmpty($payment);
        $enrolmentMembership = $payment->membership;

        // created membership is the newly created membership
        $this->assertEquals($membership->id,  $enrolmentMembership->id);
        $this->assertEquals(\Api\Model\MembershipState::STATUS_PENDING,  $enrolmentMembership->status);
        $this->assertEquals(\Api\Model\MembershipType::stroom()->id,  $enrolmentMembership->subscription_id);
        $this->assertEquals(\Api\Model\PaymentMode::STROOM,  $enrolmentMembership->last_payment_mode);
    }

    public function testRenewalByStroom() {
        $user = \Api\Model\Contact::find(3);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
        ->getMock();
        // should send a confirmation to user
        $mailMgr->expects($this->once())
            ->method('sendRenewalConfirmation')
            ->with($user, \Api\Model\PaymentMode::STROOM);

        $mollieApi = new \Tests\Mock\MollieApiClientMock();
        $user->state = \Api\Model\UserState::ACTIVE;
        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi);
        $orderId = "order123";
        $enrolmentMgr->renewalByStroom($orderId);
        $user = \Api\Model\Contact::find($user->id);
        // current membership is still active
        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE,  $user->activeMembership->status);

        // lookup newly created payment
        $payment = \Api\Model\Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $user->id],
            ['mode', '=', \Api\Model\PaymentMode::STROOM],
        ])->first();
        $this->assertNotEmpty($payment);
        $renewalMembership = $payment->membership;

        // newly created membership is pending
        $this->assertEquals(\Api\Model\MembershipState::STATUS_PENDING,  $renewalMembership->status);
        $this->assertEquals(\Api\Model\PaymentMode::STROOM,  $renewalMembership->last_payment_mode);
    }

    public function testConfirmPayment()
    {
        // FIXME: upgrade of phpunit required (and switch to createStub?)
        $user = \Api\Model\Contact::find(3);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class) // mail mgr mock
            ->getMock();
        // should send a confirmation to user
        $mailMgr->expects($this->once())
            ->method('sendEnrolmentPaymentConfirmation')
            ->with($user, \Api\Model\PaymentMode::STROOM);
        $inventory = $this->createMock(\Api\Inventory\Inventory::class); // inventory stub
        $userMgr = new \Api\User\UserManager($inventory, $logger);
        $mollieApi = new \Tests\Mock\MollieApiClientMock();

        $user->state = \Api\Model\UserState::CHECK_PAYMENT;
        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi, $userMgr);
        $enrolmentMgr->confirmPayment(\Api\Model\PaymentMode::STROOM);

        $this->assertTrue($user->isStroomParticipant());
        // reload user to get all updates
        $user = \Api\Model\Contact::find(3);
        $membership = $user->activeMembership()->first();
        $payment = \Api\Model\Payment::find($membership->payment->payment_id);
        $this->assertEquals(\Api\Model\PaymentState::SUCCESS, $payment->state);
    }

    public function testProcessMolliePayment() {
        $userId = 5;
        $user = \Api\Model\Contact::find($userId);
        $originalMembershipId = $user->activeMembership->id;
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->createMailMgrMock();
        $inventory = $this->createMock(\Api\Inventory\Inventory::class); // inventory stub
        $userMgr = new \Api\User\UserManager($inventory, $logger);

        // Mock Mollie interactions
        $paymentId = "tr_456-paymentId"; // payment with id 4
        $mollieApi = $this->createMollieMock($paymentId, $userId, \Api\Model\Product::ENROLMENT, 30);

        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi, $userMgr);
        $enrolmentMgr->processMolliePayment($paymentId);

        // reload user to get all updates
        $user = \Api\Model\Contact::find($userId);
        $membership = $user->activeMembership()->first(); // get active membership
        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE, $membership->status);
        $this->assertEquals(\Api\Model\PaymentMode::MOLLIE, $membership->last_payment_mode);
        $newMembershipId = $membership->id;

        $this->assertEquals($originalMembershipId, $newMembershipId); // first enrolment, so membership can be reused

        $payment = \Api\Model\Payment::find($membership->payment->payment_id);
        $this->assertEquals(\Api\Model\PaymentState::SUCCESS, $payment->state);
        $this->assertEquals(\Api\Model\PaymentMode::MOLLIE, $payment->mode);
    }

    public function testProcessMolliePaymentRenewal() {
        $userId = 4;
        $user = \Api\Model\Contact::find($userId);
        echo \json_encode($user) . "\n";
        echo \json_encode($user->activeMembership)."\n";
        $originalMembershipId = $user->activeMembership->id;
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class); // logger stub
        $mailMgr = $this->createMailMgrMock(true); // mock enrolment confirmation and notification calls
        $inventory = $this->createMock(\Api\Inventory\Inventory::class); // inventory stub
        $userMgr = new \Api\User\UserManager($inventory, $logger);

        // Mock Mollie interactions
        $paymentId = "tr_123-paymentId"; // payment with id 4
        $mollieApi = $this->createMollieMock($paymentId, $userId, \Api\Model\Product::RENEWAL, 20);

        $enrolmentMgr = new EnrolmentManager($logger, $user, $mailMgr, $mollieApi, $userMgr);
        $enrolmentMgr->processMolliePayment($paymentId);

        // reload user to get all updates
        $user = \Api\Model\Contact::find($userId);

        $this->assertEquals(\Api\Model\UserState::ACTIVE, $user->state);
        $this->assertEquals(\Api\Model\PaymentMode::MOLLIE, $user->payment_mode);

        $membership = $user->activeMembership()->first(); // get active membership
        $this->assertEquals(\Api\Model\MembershipState::STATUS_ACTIVE, $membership->status);
        $this->assertEquals(\Api\Model\PaymentMode::MOLLIE, $membership->last_payment_mode);
        $newMembershipId = $membership->id;

        $this->assertEquals(4, $newMembershipId);
        $this->assertNotEquals($originalMembershipId, $newMembershipId); // membership should have been updated
        $originalMembership = \Api\Model\Membership::find($originalMembershipId);
        $this->assertEquals(\Api\Model\MembershipState::STATUS_EXPIRED, $originalMembership->status);

        $payment = \Api\Model\Payment::find($membership->payment->payment_id);
        $this->assertEquals(\Api\Model\PaymentState::SUCCESS, $payment->state);

    }
    /**
     * @param $paymentId
     * @param $userId
     * @return \Tests\Mock\MollieApiClientMock
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    private function createMollieMock($paymentId, $userId, $product, $amount): \Tests\Mock\MollieApiClientMock
    {
        $mollieApi = new \Tests\Mock\MollieApiClientMock();
        $amountClass = new stdClass();
        $amountClass->currency = 'EUR';
        $amountClass->value = $amount;
        $metadata = json_decode(json_encode(array(
            'order_id' => $paymentId,
            'user_id' => $userId,
            'product_id' => $product,
            'membership_end_date' => new DateTime('now')
        )), FALSE);
        $payment = $mollieApi->payments->create(array(
            'metadata' => $metadata,
            'amount' => $amountClass,
        ));
        // mark the payment as succesfully paid
        $payment->paidAt = new DateTime('now');
        $payment->status = \Mollie\Api\Types\PaymentStatus::STATUS_PAID;
        return $mollieApi;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createMailMgrMock($isRenewal = false): \PHPUnit\Framework\MockObject\MockObject
    {
        $mailMgr = $this->getMockBuilder(\Api\Mail\MailManager::class)// mail mgr mock
            ->getMock();
        // should send a confirmation to user
        if (!$isRenewal) {
            $mailMgr->expects($this->once())
                ->method('sendEnrolmentConfirmation')
                ->with($this->anything(), \Api\Model\PaymentMode::MOLLIE);
        } else {
            $mailMgr->expects($this->once())
                ->method('sendRenewalConfirmation')
                ->with($this->anything(), \Api\Model\PaymentMode::MOLLIE);
        }
        $mailMgr->expects($this->once())
            ->method('sendEnrolmentSuccessNotification')
            ->with(ENROLMENT_NOTIF_EMAIL, $this->anything(), $isRenewal);
        return $mailMgr;
    }
}

if (!class_exists('ContactTest')) {
    class ContactTest extends \Api\Model\Contact
    {
        protected $table = 'users';
        public $incrementing = false;

        public static function boot()
        {
            parent::boot();
        }

        //['user_id', 'state', 'first_name', 'last_name', 'role', 'email',
        //'membership_start_date', 'membership_end_date', 'address_line_1', 'address_line_4' (postal_code), 'address_line_2' (city),
        //'telephone', 'registration_number', 'payment_mode', 'created_at', 'updated_at'
        public $user_id = 999;
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
        public function resolveRouteBinding($value, $field = NULL)
        {
            // TODO: Implement resolveRouteBinding() method.
        }
    }
}