<?php
use Api\Token;
use Tests\DbUnitArrayDataSet;

class ReservationsTest extends LocalDbWebTestCase
{
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->startdate = new DateTime();
		$this->enddate = clone $this->startdate;
		$this->enddate->add(new DateInterval('P7D'));
		return new DbUnitArrayDataSet(array(
				'users' => array(
						array('user_id' => 1, 'firstname' => 'firstname', 'lastname' => 'lastname',
								'role' => 'admin', 'email' => 'admin@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
						array('user_id' => 2, 'firstname' => 'harry', 'lastname' => 'De Handige',
								'role' => 'volunteer', 'email' => 'harry@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
						array('user_id' => 3, 'firstname' => 'daniel', 'lastname' => 'De Deler',
								'role' => 'member', 'email' => 'daniel@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
				),
				'reservations' => array(
						array('reservation_id' => 1, 'tool_id' => 1, 'user_id' => 1,
								'title' => 'title 1',
								'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
								'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
								'type' => 'repair'
						),
						array('reservation_id' => 2, 'tool_id' => 1, 'user_id' => 1,
								'title' => 'title 2',
								'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
								'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
								'type' => 'maintenance'
						),
						array('reservation_id' => 3, 'tool_id' => 3, 'user_id' => 1,
								'title' => 'title 3',
								'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
								'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
								'type' => 'repair'
						),
				),
		));
	}
	
	private function getToken() {
		// get token
		$data = ["reservations.all"];
		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0",
				"PHP_AUTH_USER" => "test",
				"PHP_AUTH_PW" => "test"
		);
		$response = $this->client->post('/token', $data, $header);
		$responseData = json_decode($response);
		return $responseData->token;
	}
	
	public function testGetReservations()
	{
		echo "test GET reservations\n";
		$body = $this->client->get('/reservations');
		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservations = json_decode($body);
		$this->assertEquals(3, count($reservations));
	}

	public function testPostReservations()
	{
		echo "test POST reservations\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["reservations.all"]);
		// get token
// 		$token = $this->getToken();
		
// 		$scopes = array("reservations.all");
// 		$header = array('Authorization' => "bearer $token");
		
		$data = array("tool_id" => 2, "user_id" => 2, 
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservation = json_decode($body);
		$this->assertNotNull($reservation->reservation_id);
		
		// check reservation has properly been updated
		$bodyGet = $this->client->get('/reservations/' . $reservation->reservation_id);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservation = json_decode($bodyGet);
		$this->assertEquals($data["tool_id"], $reservation->tool_id);
		$this->assertEquals($data["user_id"], $reservation->user_id);
		$this->assertEquals($data["title"], $reservation->title);
		$this->assertEquals($data["type"], $reservation->type);
		
	}
	
	public function testPostReservationsInvalidUser()
	{
		echo "test POST reservations\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["reservations.all"]);
		// get token
// 		$token = $this->getToken();
		
// 		$scopes = array("reservations.all");
// 		$header = array('Authorization' => "bearer $token");
		
		$data = array("tool_id" => 2, "user_id" => 999,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
		$this->assertEquals(400, $this->client->response->getStatusCode());
	}
	
	public function testPostReservationsInvalidTool()
	{
		echo "test POST reservations\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["reservations.all"]);
	
		$data = array("tool_id" => 999, "user_id" => 2,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
		$this->assertEquals(400, $this->client->response->getStatusCode());
	}
	
	public function testGetReservation()
	{
		echo "test GET reservation\n";
		$body = $this->client->get('/reservations/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservation = json_decode($body);
		$this->assertEquals("1", $reservation->reservation_id);
		$this->assertEquals("1", $reservation->tool_id);
		$this->assertEquals("1", $reservation->user_id);
		$this->assertEquals("title 1", $reservation->title);
		$this->assertEquals("repair", $reservation->type);
	}
	
	public function testPutReservation()
	{
		echo "test PUT reservation\n";
		$data = array("title" => "my new reservation",
				"type" => "my new type"
		);
		$header = array();
		$body = $this->client->put('/reservations/1', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		print_r($body);
		
		// check reservation has properly been updated
		$bodyGet = $this->client->get('/reservations/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservation = json_decode($bodyGet);
		$this->assertEquals($data["title"], $reservation->title);
		$this->assertEquals($data["type"], $reservation->type);
	}

	public function testDeleteReservation()
	{
		echo "test DELETE reservation\n";
		$this->client->delete('/reservations/1');
		$body = $this->assertEquals(200, $this->client->response->getStatusCode());
		
		// check reservation no longer exists
		$bodyGet = $this->client->get('/reservations/1');
		$this->assertEquals(404, $this->client->response->getStatusCode());
	}
	
}