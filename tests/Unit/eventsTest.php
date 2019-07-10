<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware\HttpBasicAuthentication;
use Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;

require_once __DIR__ . '/../test_env.php';

class EventsTest extends LocalDbWebTestCase
{
	private $createdate;
	private $updatedate;
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->createdate = new DateTime();
		$this->updatedate = clone $this->createdate;
//		$this->enddate->add(new DateInterval('P365D'));
		
		return new DbUnitArrayDataSet(array(
            'users' => array(
                array('user_id' => 3, 'firstname' => 'daniel', 'lastname' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                ),
            ),
			'events' => array(
				array('event_id' => 1, 'name' => 'START', 'version' => 1,
						'amount' => 20, 'currency' => 'euro',
						'data' => '{}',
						'created_at' => $this->createdate->format('Y-m-d H:i:s'),
						'updated_at' => $this->updatedate->format('Y-m-d H:i:s')
						),
			),
		));
	}
	
	public function testGetEvents()
	{
		echo "test GET events\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["events.all"]);
		$body = $this->client->get('/events');
// 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
        $events = json_decode($body);
		$this->assertEquals(1, count($events));
	}
	
	public function testGetEventsUnauthorized()
	{
		echo "test GET events unauthorized\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["events.none"]);
		$body = $this->client->get('/events');
		$this->assertEquals(403, $this->client->response->getStatusCode());
		$this->assertTrue(empty($body));
	}
	
	public function testPostEvents()
	{
		echo "test POST events\n";
		$scopes = array("events.create");
		$this->setToken("1", $scopes);
		$header = array('Authorization' => "bearer 123456");
		$container = $this->app->getContainer();
		$data = array("event_id" => "5", "name" => "START",
			"version" => 1,
			"amount" => 20,
            "currency" => "euro",
            "data" => "{}"
		);
		$body = $this->client->post('/events', $data, $header);
// 		print_r($body);
		$this->assertEquals(201, $this->client->response->getStatusCode());
		$event = json_decode($body);
		$this->assertNotNull($event->event_id);
	}

}