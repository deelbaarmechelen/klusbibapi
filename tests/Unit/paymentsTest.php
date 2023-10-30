<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware\HttpBasicAuthentication;
use Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;

require_once __DIR__ . '/../test_env.php';

class PaymentsTest extends LocalDbWebTestCase
{
	private $paymentDate;
	private $createdate;
	private $updatedate;
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->paymentDate = new DateTime();
		$this->createdate = new DateTime();
		$this->updatedate = clone $this->createdate;
//		$this->enddate->add(new DateInterval('P365D'));
		
		return new DbUnitArrayDataSet(array(
            'contact' => array(
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                ),
            ),
			'kb_payments' => array(
				array('payment_id' => 1, 'user_id' => 3, 'state' => \Api\Model\PaymentState::OPEN,
                    'mode'=> \Api\Model\PaymentMode::TRANSFER, 'payment_date'=> $this->paymentDate->format('Y-m-d H:i:s'),
                    'order_id'=> '123',
                    'amount' => 20, 'currency' => 'euro',
                    'created_at' => $this->createdate->format('Y-m-d H:i:s'),
                    'updated_at' => $this->updatedate->format('Y-m-d H:i:s')
                    ),
			),
		));
	}

	public function testGetPayments()
	{
		echo "test GET payments\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["payments.all"]);
		$body = $this->client->get('/payments');
// 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
        $payments = json_decode($body);
		$this->assertEquals(1, count($payments));
	}
	
//	public function testGetPaymentsUnauthorized()
//	{
//		echo "test GET payments unauthorized\n";
//		$this->setUser('daniel@klusbib.be');
//		$this->setToken('3', ["payments.none"]);
//		$body = $this->client->get('/payments');
//		$this->assertEquals(403, $this->client->response->getStatusCode());
//		$this->assertTrue(empty($body));
//	}
	
	public function testPostPayments()
	{
		echo "test POST payments\n";
		$scopes = array("payments.create");
		$this->setToken("1", $scopes);
		$header = array('Authorization' => "bearer 123456");
		$container = $this->app->getContainer();
		$data = array("payment_id" => "5",
			"userId" => 3,
			"paymentMode" => "TRANSFER",
			"orderId" => "order123",
			"amount" => 20,
            "currency" => "euro"
		);
		$body = $this->client->post('/payments', $data, $header);
 		print_r($body);
 		// FIXME: can we change this to 201? Impact on Mollie?
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$payment = json_decode($body);
		$this->assertNotNull($payment->orderId);
	}

}