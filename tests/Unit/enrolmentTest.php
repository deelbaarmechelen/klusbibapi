<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;
use Api\Model\PaymentState;
use Api\Model\PaymentMode;
use Tests\Mock\PaymentMock;
use Api\Model\UserState;

require_once __DIR__ . '/../test_env.php';

class EnrolmentTest extends LocalDbWebTestCase
{
	private $startdate;
	private $enddate;
	private $expiredStartDate;
	private $expiredEndDate;
	private $renewalEndDate;

	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->startdate = new DateTime();
		$this->enddate = clone $this->startdate;
        $this->enddate->add(new DateInterval('P1Y'));
        $this->expiredStartDate = clone $this->startdate;
        $this->expiredStartDate->sub(new DateInterval('P20D'));
        $this->expiredEndDate = clone $this->enddate;
        $this->expiredEndDate->sub(new DateInterval('P20D'));
        $this->renewalEndDate = clone $this->expiredEndDate;
        $this->renewalEndDate->add(new DateInterval('P1Y'));
        $this->acceptTermsDate = clone $this->startdate;
        $this->acceptTermsDate->sub(new DateInterval('P1M'));


        return new DbUnitArrayDataSet(array(
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
            'membership' => array(
                array('id' => 1, 'subscription_id' => 1, 'contact_id' => 1,
                    'status' => \Api\Model\MembershipState::STATUS_ACTIVE,
                    'last_payment_mode' => \Api\Model\PaymentMode::CASH,
                    'starts_at' => $this->startdate->format('Y-m-d H:i:s'),
                    'expires_at' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 2, 'subscription_id' => 1, 'contact_id' => 2,
                    'status' => \Api\Model\MembershipState::STATUS_ACTIVE,
                    'last_payment_mode' => \Api\Model\PaymentMode::CASH,
                    'starts_at' => $this->startdate->format('Y-m-d H:i:s'),
                    'expires_at' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 3, 'subscription_id' => 1, 'contact_id' => 4,
                    'status' => \Api\Model\MembershipState::STATUS_ACTIVE,
                    'last_payment_mode' => \Api\Model\PaymentMode::CASH,
                    'starts_at' => $this->expiredStartDate->format('Y-m-d'),
                    'expires_at' => $this->expiredEndDate->format('Y-m-d')
                ),
                array('id' => 4, 'subscription_id' => 3, 'contact_id' => 4,
                    'status' => \Api\Model\MembershipState::STATUS_PENDING,
                    'last_payment_mode' => \Api\Model\PaymentMode::CASH,
                    'starts_at' => $this->expiredStartDate->format('Y-m-d'),
                    'expires_at' => $this->renewalEndDate->format('Y-m-d')
                ),
                array('id' => 5, 'subscription_id' => 1, 'contact_id' => 6,
                    'status' => \Api\Model\MembershipState::STATUS_PENDING,
                    'last_payment_mode' => \Api\Model\PaymentMode::MOLLIE,
                    'starts_at' => $this->startdate->format('Y-m-d'),
                    'expires_at' => $this->enddate->format('Y-m-d')
                ),
                array('id' => 6, 'subscription_id' => 4, 'contact_id' => 8,
                    'status' => \Api\Model\MembershipState::STATUS_ACTIVE,
                    'last_payment_mode' => \Api\Model\PaymentMode::STROOM,
                    'starts_at' => $this->startdate->format('Y-m-d'),
                    'expires_at' => $this->enddate->format('Y-m-d')
                ),
                array('id' => 7, 'subscription_id' => 1, 'contact_id' => 5,
                    'status' => \Api\Model\MembershipState::STATUS_ACTIVE,
                    'last_payment_mode' => \Api\Model\PaymentMode::CASH,
                    'starts_at' => $this->expiredStartDate->format('Y-m-d'),
                    'expires_at' => $this->expiredEndDate->format('Y-m-d')
                ),
            ),
			'contact' => array(
				array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
                    'role' => 'admin', 'email' => 'admin@klusbib.be',  'state' => 'ACTIVE',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
                    'active_membership' => 1
                ),
				array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
                    'role' => 'volunteer', 'email' => 'harry@klusbib.be', 'state' => UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
                    'active_membership' => 2
                ),
				array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => UserState::CHECK_PAYMENT,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
                    'active_membership' => null
                ),
                array('id' => 4, 'first_name' => 'nele', 'last_name' => 'HippeDame',
                    'role' => 'member', 'email' => 'nele@klusbib.be', 'state' => UserState::EXPIRED,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->expiredStartDate->format('Y-m-d'),
                    'membership_end_date' => $this->expiredEndDate->format('Y-m-d'),
                    'active_membership' => 3
                ),
                array('id' => 5, 'first_name' => 'an', 'last_name' => 'ErvarenLetser',
                    'role' => 'member', 'email' => 'an@klusbib.be', 'state' => UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d'),
                    'membership_end_date' => $this->enddate->format('Y-m-d'),
                    'active_membership' => 7
                ),
                array('id' => 6, 'first_name' => 'tom', 'last_name' => 'Techie',
                    'role' => 'member', 'email' => 'tom@klusbib.be', 'state' => UserState::CHECK_PAYMENT,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->expiredStartDate->format('Y-m-d'),
                    'membership_end_date' => $this->expiredEndDate->format('Y-m-d'),
                    'active_membership' => 5
                ),
                array('id' => 7, 'first_name' => 'wim', 'last_name' => 'Newbie',
                    'role' => 'member', 'email' => 'wim@klusbib.be', 'state' => UserState::DISABLED,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => null,
                    'membership_end_date' => null,
                    'active_membership' => null
                ),
                array('id' => 8, 'first_name' => 'Steven', 'last_name' => 'Stroom',
                    'role' => 'member', 'email' => 'steven@klusbib.be', 'state' => UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'address_line_1' => 'here', 'address_line_2' => 'Mechelen', 'address_line_4' => '2800',
                    'registration_number' => '00010112345', 'accept_terms_date' => $this->acceptTermsDate->format('Y-m-d'),
                    'membership_start_date' => $this->startdate->format('Y-m-d'),
                    'membership_end_date' => $this->enddate->format('Y-m-d'),
                    'active_membership' => 6
                ),
            ),
            'kb_payments' => array(
                array('payment_id' => 1, 'user_id' => 3, 'state' => PaymentState::OPEN, 'mode' => PaymentMode::MOLLIE,
                    'payment_date' => $this->startdate->format('Y-m-d'), 'order_id' => '3_20201018120000',
                    'amount' => 30, 'currency' => 'EUR', 'membership_id' => 2),
                array('payment_id' => 2, 'user_id' => 4, 'state' => PaymentState::OPEN, 'mode' => PaymentMode::MOLLIE,
                    'payment_date' => $this->startdate->format('Y-m-d'), 'order_id' => '4_20201018120000',
                    'amount' => 20, 'currency' => 'EUR', 'membership_id' => 4),
                array('payment_id' => 3, 'user_id' => 6, 'state' => PaymentState::OPEN, 'mode' => PaymentMode::MOLLIE,
                    'payment_date' => $this->startdate->format('Y-m-d'), 'order_id' => '6_20201018120000',
                    'amount' => 30, 'currency' => 'EUR', 'membership_id' => 5)
            ),
            'kb_project_user' => array(
                array('project_id' => 1, 'user_id' => 8, 'info' => 'test user')
            )
        ));
	}

    public function testPostEnrolmentVolunteer()
    {
        echo "test POST enrolment volunteer\n";
        $userId = "3";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::PAYCONIQ,
            "userId" => $userId,
            "orderId" => $orderId
        );
        $scopes = array("users.create");
        $sub = "1";
        $token = Token::generateToken($scopes, $sub);
        $header = array('HTTP_AUTHORIZATION' => "bearer " . $token);
        $body = $this->client->post('/enrolment', $data, $header);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::PAYCONIQ, $response_data->paymentMode);
        $this->assertEquals(PaymentState::SUCCESS, $response_data->paymentState);

        // check payment has properly been created
        $this->checkPayment($orderId, $userId);
    }
    public function testPostRenewalVolunteer()
    {
        echo "test POST enrolment volunteer (renewal)\n";
        $userId = "5";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::LETS,
            "userId" => $userId,
            "orderId" => $orderId,
            "renewal" => true
        );
        $scopes = array("users.create");
        $sub = "1";
        $token = Token::generateToken($scopes, $sub);
        $header = array('HTTP_AUTHORIZATION' => "bearer " . $token);
        $body = $this->client->post('/enrolment', $data, $header);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::LETS, $response_data->paymentMode);
        $this->assertEquals(PaymentState::SUCCESS, $response_data->paymentState);

        // check payment has properly been created
        $this->checkPayment($orderId, $userId);
    }
    public function testPostEnrolmentVolunteerNonAdmin()
    {
        echo "test POST enrolment volunteer non admin\n";
        $userId = "3";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::PAYCONIQ,
            "userId" => $userId,
            "orderId" => $orderId
        );

        // Decline if no authorization header
        $body = $this->client->post('/enrolment', $data);
        $this->assertEquals(401, $this->client->response->getStatusCode());

        // Decline if invalid authorization header
        $header = array('HTTP_AUTHORIZATION' => "bearer INVALID");
        $body = $this->client->post('/enrolment', $data, $header);
        $this->assertEquals(401, $this->client->response->getStatusCode());

        $scopes = array("users.create");
        $sub = "4"; // regular member
        $token = Token::generateToken($scopes, $sub);
        $header = array('HTTP_AUTHORIZATION' => "bearer " . $token);
        $body = $this->client->post('/enrolment', $data, $header);
        $this->assertEquals(403, $this->client->response->getStatusCode());
    }
    public function testPostEnrolmentVolunteerTemporary()
    {
        echo "test POST enrolment volunteer temporary\n";
        $userId = "7";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::PAYCONIQ,
            "userId" => $userId,
            "orderId" => $orderId,
            "membershipType" => "TEMPORARY"
        );
        $scopes = array("users.create");
        $sub = "1";
        $token = Token::generateToken($scopes, $sub);
        $header = array('HTTP_AUTHORIZATION' => "bearer " . $token);
        $body = $this->client->post('/enrolment', $data, $header);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::PAYCONIQ, $response_data->paymentMode);
        $this->assertEquals(PaymentState::SUCCESS, $response_data->paymentState);

        // check payment has properly been created
        $this->checkPayment($orderId, $userId);
    }
    public function testPostEnrolmentTransfer()
    {
        echo "test POST enrolment\n";
        $userId = "3";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::TRANSFER,
            "userId" => $userId,
            "orderId" => $orderId
        );
        $body = $this->client->post('/enrolment', $data);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::TRANSFER, $response_data->paymentMode);
        $this->assertEquals(PaymentState::OPEN, $response_data->paymentState);

        // check payment has properly been created
        $scopes = array("payments.all");
        $this->setToken(null, $scopes);
        $this->checkPayment($orderId, $userId);
    }
    public function testPostRenewalTransfer()
    {
        echo "test POST enrolment (renewal)\n";
        $userId = "4";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::TRANSFER,
            "userId" => $userId,
            "orderId" => $orderId,
            "renewal" => true
        );
        $body = $this->client->post('/enrolment', $data);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::TRANSFER, $response_data->paymentMode);
        $this->assertEquals(PaymentState::OPEN, $response_data->paymentState);

        // check payment has properly been created
        $payments = $this->checkPaymentCreated($orderId);
        $this->assertCount(1, $payments);
        $this->assertEquals($userId, $payments[0]->user_id);

        // check user remained unchanged
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::EXPIRED, $user->state);
        $this->assertEquals($this->expiredEndDate->format('Y-m-d'), $user->membership_end_date);
    }

	public function testPostEnrolmentMollie()
	{
		echo "test POST enrolment\n";
        $userId = "3";
        $orderId = $userId . "_20181202120000";
        $redirectUrl = "https://localhost/confirm";
        PaymentMock::$checkoutUrl = "https://localhost/checkout"; // set expected checkoutUrl from Mollie Payment Mock
		$data = array("paymentMode" => PaymentMode::MOLLIE,
			"userId" => $userId,
            "orderId" => $orderId,
            "redirectUrl" => $redirectUrl
		);
		$body = $this->client->post('/enrolment', $data);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$response_data = json_decode($body);
		$this->assertNotNull($response_data);
		$this->assertEquals(PaymentMock::$checkoutUrl, $response_data->checkoutUrl);
		$this->assertEquals($orderId, $response_data->orderId);


		// check payment has properly been created
		$scopes = array("payments.all");
		$this->setToken(null, $scopes);
        $this->checkPayment($orderId, $userId);
//		$bodyGet = $this->client->get('/payments?orderId=' . $orderId);
//		$this->assertEquals(200, $this->client->response->getStatusCode());
//		$payments = json_decode($bodyGet);
//
//		// For Mollie, payment only created at call webhook
//		$this->assertCount(1, $payments);
	}
    public function testPostEnrolmentStroom()
    {
        echo "test POST enrolment\n";
        $userId = "3";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::STROOM,
            "userId" => $userId,
            "orderId" => $orderId
        );
        $body = $this->client->post('/enrolment', $data);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::STROOM, $response_data->paymentMode);
        $this->assertEquals(PaymentState::OPEN, $response_data->paymentState);

        // check payment has properly been created
        $scopes = array("payments.all");
        $this->setToken(null, $scopes);
        $this->checkPayment($orderId, $userId);
    }
    public function testPostRenewalStroom()
    {
        echo "test POST enrolment (renewal)\n";
        $userId = "4";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::STROOM,
            "userId" => $userId,
            "orderId" => $orderId,
            "renewal" => true
        );
        $body = $this->client->post('/enrolment', $data);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals(PaymentMode::STROOM, $response_data->paymentMode);
        $this->assertEquals(PaymentState::OPEN, $response_data->paymentState);

        // check payment has properly been created
        $payments = $this->checkPaymentCreated($orderId);
        $this->assertCount(1, $payments);
        $this->assertEquals($userId, $payments[0]->user_id);

        // check user remained unchanged
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::EXPIRED, $user->state);
        $this->assertEquals($this->expiredEndDate->format('Y-m-d'), $user->membership_end_date);
    }
    public function testPostRenewalStroomFromStroomMember()
    {
        echo "test POST enrolment (renewal)\n";
        $userId = "8";
        $orderId = $userId . "_20181202120000";
        $data = array("paymentMode" => PaymentMode::STROOM,
            "userId" => $userId,
            "orderId" => $orderId,
            "renewal" => true
        );
        $body = $this->client->post('/enrolment', $data);
        print_r($body);
        $this->assertEquals(400, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);
        $this->assertEquals("Invalid request: Stroom membership can only be requested once", $response_data->message);

        // check user remained unchanged
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::ACTIVE, $user->state);
        $this->assertEquals($this->enddate->format('Y-m-d'), $user->membership_end_date);
    }

	public function testPostRenewalMollie()
	{
		echo "test POST enrolment (renewal)\n";
        $userId = "4";
        $orderId = $userId . "_20181202120000";
        $redirectUrl = "https://localhost/confirm";
        PaymentMock::$checkoutUrl = "https://localhost/checkout"; // set expected checkoutUrl from Mollie Payment Mock
		$data = array("paymentMode" => PaymentMode::MOLLIE,
			"userId" => $userId,
            "orderId" => $orderId,
            "redirectUrl" => $redirectUrl,
            "renewal" => true
		);
		$body = $this->client->post('/enrolment', $data);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$response_data = json_decode($body);
		$this->assertNotNull($response_data);
		$this->assertEquals(PaymentMock::$checkoutUrl, $response_data->checkoutUrl);
		$this->assertEquals($orderId, $response_data->orderId);


		// check payment has properly been created
        $payments = $this->checkPaymentCreated($orderId);
		$this->assertCount(1, $payments);
        $this->assertEquals($userId, $payments[0]->user_id);

        // check user remained unchanged
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::EXPIRED, $user->state);
        $this->assertEquals($this->expiredEndDate->format('Y-m-d'), $user->membership_end_date);

        // check expected meta data on mollie payment
        $actualPaymentData = \Tests\Mock\PaymentEndpointMock::$paymentData;
        $this->assertEquals("EUR",
            $actualPaymentData["amount"]["currency"]);
        $this->assertEquals("20.00",
            $actualPaymentData["amount"]["value"]);
        $this->assertEquals("Klusbib verlenging lidmaatschap nele HippeDame",
            $actualPaymentData["description"]);
        $this->assertEquals("$redirectUrl?orderId=$orderId",
            $actualPaymentData["redirectUrl"]);
        $this->assertEquals("https://localhost/enrolment/$orderId",
            $actualPaymentData["webhookUrl"]);
        $renewalEndDate = clone $this->expiredEndDate;
        $renewalEndDate->add(new DateInterval('P1Y'));
        $this->assertEquals($orderId,
            $actualPaymentData["metadata"]["order_id"]);
        $this->assertEquals($userId,
            $actualPaymentData["metadata"]["user_id"]);
        $this->assertEquals(\Api\Model\Product::RENEWAL,
            $actualPaymentData["metadata"]["product_id"]);
        $this->assertEquals($renewalEndDate->format('Y-m-d'),
            $actualPaymentData["metadata"]["membership_end_date"]->format('Y-m-d'));
	}

    public function testPostEnrolmentMollieWebhook()
    {
        echo "test POST enrolment (enrolment)\n";
        $userId = "6";
        $orderId = $userId . "_20201018120000";
        $paymentId = "tr_12345678";

        $paymentMollie = new PaymentMock(new \Tests\Mock\MollieApiClientMock());
        $paymentMollie->metadata = new stdClass();
        $paymentMollie->metadata->order_id = $orderId;
        $paymentMollie->metadata->user_id = $userId;
        $paymentMollie->metadata->product_id = \Api\Model\Product::ENROLMENT;
        $paymentMollie->metadata->membership_end_date = $this->enddate->format('Y-m-d');
        $paymentMollie->amount = new stdClass();
        $paymentMollie->amount->value = '30';
        $paymentMollie->amount->currency = 'EUR';
        \Tests\Mock\PaymentEndpointMock::$payment = $paymentMollie;
        $paymentMollie->paidAt = new DateTime();

        $data = array("paymentMode" => PaymentMode::MOLLIE,
            "userId" => $userId,
            "orderId" => $orderId
        );
        $_POST["id"] = $paymentId;
        $body = $this->client->post('/enrolment/' . $orderId, $data);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);

        // Check User is updated
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::ACTIVE, $user->state);
        $this->assertEquals($this->enddate->format('Y-m-d'), $user->membership_end_date);

        // Check payment is updated
        $payments = $this->checkPaymentCreated($orderId);
        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertNotNull($payment);
        $this->assertEquals(PaymentState::SUCCESS, $payment->state);
        $this->assertEquals(PaymentMode::MOLLIE, $payment->mode);
        $this->assertEquals($orderId, $payment->order_id);
        $this->assertEquals($userId, $payment->user_id);
        $this->assertEquals('30.00', $payment->amount);
        $this->assertEquals('EUR', $payment->currency);
    }

    public function testPostRenewalMollieWebhook()
    {
        echo "test POST enrolment (renewal)\n";
        $userId = "4";
        $orderId = $userId . "_20201018120000";
        $paymentId = "tr_12345678";

        $paymentMollie = new PaymentMock(new \Tests\Mock\MollieApiClientMock());
        $paymentMollie->metadata = new stdClass();
        $paymentMollie->metadata->order_id = $orderId;
        $paymentMollie->metadata->user_id = $userId;
        $paymentMollie->metadata->product_id = \Api\Model\Product::RENEWAL;
        $paymentMollie->metadata->membership_end_date = $this->renewalEndDate->format('Y-m-d');
        $paymentMollie->amount = new stdClass();
        $paymentMollie->amount->value = '20';
        $paymentMollie->amount->currency = 'EUR';
        \Tests\Mock\PaymentEndpointMock::$payment = $paymentMollie;
        $paymentMollie->paidAt = new DateTime();

        $data = array("paymentMode" => PaymentMode::MOLLIE,
            "userId" => $userId,
            "orderId" => $orderId,
            "renewal" => true
        );
        $_POST["id"] = $paymentId;
        $body = $this->client->post('/enrolment/' . $orderId, $data);
        echo $body;
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);

        // Check User is updated
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::ACTIVE, $user->state);
        $this->assertEquals($this->renewalEndDate->format('Y-m-d'), $user->membership_end_date);
        $this->assertEquals(4, $user->active_membership->id);

        // Check payment is updated
        $payments = $this->checkPaymentCreated($orderId);
        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertNotNull($payment);
        $this->assertEquals(PaymentState::SUCCESS, $payment->state);
        $this->assertEquals(PaymentMode::MOLLIE, $payment->mode);
        $this->assertEquals($orderId, $payment->order_id);
        $this->assertEquals($userId, $payment->user_id);
        $this->assertEquals('20.00', $payment->amount);
        $this->assertEquals('EUR', $payment->currency);
    }

    /**
     * Lookup Payment by Order id and check response status
     * @param $orderId
     */
    protected function checkPaymentCreated($orderId)
    {
        $scopes = array("payments.all");
        $this->setToken(null, $scopes);
        $bodyGet = $this->client->get('/payments', array('orderId' => $orderId));
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $payments = json_decode($bodyGet);
        return $payments;
    }

    /**
     * Lookup User by user id and check response status
     * @param $userId
     */
    protected function lookupUser($userId)
    {
        $scopes = array("users.all");
        $this->setToken(null, $scopes);
        $bodyGetUser = $this->client->get('/users/' . $userId);
        print_r($bodyGetUser);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $user = json_decode($bodyGetUser);
        return $user;
    }

    /**
     * @param $orderId
     * @param $userId
     */
    protected function checkPayment($orderId, $userId): void
    {
        $bodyGet = $this->client->get('/payments', array('orderId' => $orderId));
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $payments = json_decode($bodyGet);
        $this->assertCount(1, $payments);
        $this->assertEquals($userId, $payments[0]->user_id);
    }

}