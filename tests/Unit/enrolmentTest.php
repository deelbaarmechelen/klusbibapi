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


        return new DbUnitArrayDataSet(array(
			'users' => array(
				array('user_id' => 1, 'firstname' => 'firstname', 'lastname' => 'lastname',
						'role' => 'admin', 'email' => 'admin@klusbib.be',  'state' => 'ACTIVE',
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
						'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => 'CHECK_PAYMENT',
						'hash' => password_hash("test", PASSWORD_DEFAULT),
						'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
						'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
                array('user_id' => 4, 'firstname' => 'nele', 'lastname' => 'HippeDame',
                    'role' => 'member', 'email' => 'nele@klusbib.be', 'state' => 'EXPIRED',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->expiredStartDate->format('Y-m-d'),
                    'membership_end_date' => $this->expiredEndDate->format('Y-m-d')
                ),
                array('user_id' => 5, 'firstname' => 'an', 'lastname' => 'ErvarenLetser',
                    'role' => 'member', 'email' => 'an@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->expiredStartDate->format('Y-m-d'),
                    'membership_end_date' => $this->expiredEndDate->format('Y-m-d')
                ),
            ),
            'payments' => array(
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
		$bodyGet = $this->client->get('/payments?orderId=' . $orderId);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$payments = json_decode($bodyGet);

		// For Mollie, payment only created at call webhook
		$this->assertCount(0, $payments);
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

        // For Mollie, payment only created at call webhook
		$this->assertCount(0, $payments);

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
            $actualPaymentData["metadata"]["membership_end_date"]);
	}

    public function testPostEnrolmentMollieWebhook()
    {
        echo "test POST enrolment (enrolment)\n";
        $userId = "3";
        $orderId = $userId . "_20181202120000";
        $paymentId = "tr_12345678";

        $paymentMollie = new PaymentMock(new \Tests\Mock\MollieApiClientMock());
        $paymentMollie->metadata = new stdClass();
        $paymentMollie->metadata->order_id = $orderId;
        $paymentMollie->metadata->user_id = $userId;
        $paymentMollie->metadata->product_id = \Api\Model\Product::ENROLMENT;
        $paymentMollie->metadata->membership_end_date = $this->enddate->format('Y-m-d');
        $paymentMollie->amount = new stdClass();
        $paymentMollie->amount->value = '20';
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
        $this->assertEquals('20.00', $payment->amount);
        $this->assertEquals('EUR', $payment->currency);
    }

    public function testPostRenewalMollieWebhook()
    {
        echo "test POST enrolment (renewal)\n";
        $userId = "4";
        $orderId = $userId . "_20181202120000";
        $paymentId = "tr_12345678";
        $renewalEndDate = clone $this->expiredEndDate;
        $renewalEndDate->add(new DateInterval('P1Y'));

        $paymentMollie = new PaymentMock(new \Tests\Mock\MollieApiClientMock());
        $paymentMollie->metadata = new stdClass();
        $paymentMollie->metadata->order_id = $orderId;
        $paymentMollie->metadata->user_id = $userId;
        $paymentMollie->metadata->product_id = \Api\Model\Product::RENEWAL;
        $paymentMollie->metadata->membership_end_date = $renewalEndDate->format('Y-m-d');
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
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $response_data = json_decode($body);
        $this->assertNotNull($response_data);

        // Check User is updated
        $user = $this->lookupUser($userId);
        $this->assertEquals(UserState::ACTIVE, $user->state);
        $this->assertEquals($renewalEndDate->format('Y-m-d'), $user->membership_end_date);

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
        $bodyGet = $this->client->get('/payments?orderId=' . $orderId);
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
        $bodyGet = $this->client->get('/payments?orderId=' . $orderId);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $payments = json_decode($bodyGet);
        $this->assertCount(1, $payments);
        $this->assertEquals($userId, $payments[0]->user_id);
    }

}