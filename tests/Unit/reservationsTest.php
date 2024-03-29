<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;
use DateTime;

require_once __DIR__ . '/../test_env.php';

class ReservationsTest extends LocalDbWebTestCase
{
    private DateTime $startdate;
    private DateTime $enddate;
	// Run for each unit test to setup our slim app environment
	public function setup($dependencies = null, WebTestClient $client = NULL, $useMiddleware = false) : void
	{
// 		if (!defined("RESERVATION_NOTIF_EMAIL")) {
// 			define("RESERVATION_NOTIF_EMAIL", "ut@klusbib.be");
// 		}
// 		if (!defined("MAIL_PORT")) {
// 			define("MAIL_PORT", "26");
// 		}
// 		if (!defined("MAIL_USERNAME")) {
// 			define("MAIL_USERNAME", "username");
// 		}
// 		if (!defined("MAIL_PASSWORD")) {
// 			define("MAIL_PASSWORD", "password");
// 		}
// 		if (!defined("MAIL_HOST")) {
// 			define("MAIL_HOST", "localhost");
// 		}
// 		if (!defined("MAILER")) {
// 			define("MAILER", "sendmail");
// 		}
// 		if (!defined("SENDER_EMAIL")) {
// 			define("SENDER_EMAIL", "ut@klusbib.be");
// 		}
// 		if (!defined("SENDER_NAME")) {
// 			define("SENDER_NAME", "Unit Tests");
// 		}
		parent::setUp($dependencies, $client);
	}
	
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->startdate = new DateTime();
		$this->enddate = clone $this->startdate;
		$this->enddate->add(new DateInterval('P7D'));
		return new DbUnitArrayDataSet(array(
				'contact' => array(
						array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
								'role' => 'admin', 'email' => 'admin@klusbib.be',
								'password' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
						array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
								'role' => 'volunteer', 'email' => 'harry@klusbib.be',
								'password' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
						array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
								'role' => 'member', 'email' => 'daniel@klusbib.be',
								'password' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
				),
				'kb_tools' => array(
						array('tool_id' => 1, 'name' => 'tool 1', 'description' => 'description 1',
								'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345',
								'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
								'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25',
								'code' => 'KB-000-17-001', 'owner_id' => 0
						),
						array('tool_id' => 2, 'name' => 'tool 2', 'description' => 'description 2',
								'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345',
								'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
								'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25',
								'code' => 'KB-002-17-001', 'owner_id' => 2
						),
						array('tool_id' => 3, 'name' => 'tool 3', 'description' => 'description 3',
								'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345',
								'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
								'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25',
								'code' => 'KB-000-17-002', 'owner_id' => 0
						)
				),
				'kb_reservations' => array(
                    array('reservation_id' => 1, 'tool_id' => 1, 'user_id' => 1,
                            'title' => 'title 1',
                            'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
                            'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
                            'type' => 'repair', 'state' => 'REQUESTED', 'comment' => 'my comment'
                    ),
                    array('reservation_id' => 2, 'tool_id' => 1, 'user_id' => 1,
                            'title' => 'title 2',
                            'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
                            'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
                            'type' => 'maintenance', 'state' => 'CONFIRMED'
                    ),
                    array('reservation_id' => 3, 'tool_id' => 3, 'user_id' => 1,
                            'title' => 'title 3',
                            'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
                            'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
                            'type' => 'repair', 'state' => 'CLOSED', 'comment' => ''
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
//		print_r($body);
		echo "\n";
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservations = json_decode($body);
		$this->assertEquals(3, count($reservations));
	}

    public function testGetReservationsPage()
    {
        echo "test GET reservations\n";
        $body = $this->client->get('/reservations', array('_perPage'=>2));
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $reservations = json_decode($body);
        $this->assertEquals(2, count($reservations));
    }
    public function testGetReservationsSecondPage()
    {
        echo "test GET reservations\n";
        $body = $this->client->get('/reservations', array('_perPage' => 2, '_page' => 2));
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $reservations = json_decode($body);
        $this->assertEquals(1, count($reservations));
    }
    public function testGetOpenReservations()
    {
        echo "test GET reservations\n";
        $body = $this->client->get('/reservations', array('isOpen'=>'true'));
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $reservations = json_decode($body);
        $this->assertEquals(2, count($reservations));
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
//        print_r($body);
        $this->assertEquals(201, $this->client->response->getStatusCode());
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
		echo "test POST reservations - Invalid User\n";
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
		echo "test POST reservations - Invalid tool\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["reservations.all"]);
	
		$data = array("tool_id" => 999, "user_id" => 2,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
		$this->assertEquals(400, $this->client->response->getStatusCode());
	}
	
	public function testPostReservationsOwnerOnly()
	{
		echo "test POST reservations - Owner only\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["reservations.create.owner"]);
	
		$data = array("tool_id" => 2, "user_id" => 3,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
//		print_r($body);
		echo "\n";
		$this->assertEquals(201, $this->client->response->getStatusCode());
	}
	
	public function testPostReservationsDifferentOwner()
	{
		echo "test POST reservations - Diffferent Owner\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["reservations.create.owner"]);
	
		$data = array("tool_id" => 2, "user_id" => 2,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
//		print_r($body);
		echo "\n";
		$this->assertEquals(403, $this->client->response->getStatusCode());
	}

	public function testPostReservationsDonationOnly()
	{
		echo "test POST reservations - Donation only\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('2', ["reservations.create.owner.donation_only"]);
	
		// reservation on own tool
		$data = array("tool_id" => 2, "user_id" => 2,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
//		print_r($body);
//		echo "\n";
		$this->assertEquals(201, $this->client->response->getStatusCode());

		// reservation on tool owned by someone else
		$data = array("tool_id" => 3, "user_id" => 2,
				"title" => "my reservation",
				"type" => "reservation"
		);
		$body = $this->client->post('/reservations', $data);
//		print_r($body);
//		echo "\n";
		$this->assertEquals(403, $this->client->response->getStatusCode());
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
				"type" => "my new type",
				"user_id" => "3",
				"tool_id" => "3"
		);
		$header = array();
		$body = $this->client->put('/reservations/1', $data, $header);
		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		
		// check reservation has properly been updated
		$bodyGet = $this->client->get('/reservations/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$reservation = json_decode($bodyGet);
		$this->assertEquals($data["title"], $reservation->title);
		$this->assertEquals($data["type"], $reservation->type);
	}

    public function testPutReservationWithDelivery()
    {
        echo "test PUT reservation with delivery\n";

        // create reservation and delivery
        $user = \Api\Model\Contact::factory()->create([
            'email' => "info@klusbib.be", 'first_name' => "tester", 'last_name' => "de mock"
        ]);
        $tool = \Api\Model\Tool::factory()->create([
            'name' => "mytool", 'description' => "mydescription", 'brand' => "myBrand", 'type' => "myType"
        ]);
        $reservationStart = new DateTime();
        $reservationEnd = clone $reservationStart;
        $reservationEnd->add(new DateInterval('P7D'));
        $reservation = \Api\Model\Reservation::factory()->create([
            'startsAt' => $reservationStart, 'endsAt' => $reservationEnd, 'tool_id' => $tool->tool_id, 'user_id' => $user->id
        ]);
        $inventoryItem = \Api\Model\InventoryItem::factory()->create([]);
        $deliveryDropOff = clone $reservationStart;
        $deliveryDropOff->add(new DateInterval('P2D'));
        $delivery = \Api\Model\Delivery::factory()->create([
            'comment' => 'opm', 'consumers' => 'hamer+beitel',
            'pick_up_date' => $reservationStart->format('Y-m-d'), 'drop_off_date' => $deliveryDropOff->format('Y-m-d')
        ]);
        $item = \Api\Model\DeliveryItem::factory()->create([
            'delivery_id' => $delivery->id, 'inventory_item_id' => $inventoryItem->id, 'reservation_id' => $reservation->reservation_id
        ]);
        $delivery = $this->lookupDelivery($delivery->id);

        // update reservation
        $newReservationStart = new DateTime();
        $newReservationStart->add(new DateInterval('P20D'));
        $newReservationEnd = clone $newReservationStart;
        $newReservationEnd->add(new DateInterval('P7D'));

        $data = array("startsAt" => $newReservationStart->format('Y-m-d'),
            "endsAt" => $newReservationEnd->format('Y-m-d'),
            "user_id" => "3",
            "tool_id" => "3"
        );
        $header = array();
        $body = $this->client->put('/reservations/' . $reservation->reservation_id, $data, $header);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());

        // check reservation has properly been updated
        $bodyGet = $this->client->get('/reservations/' . $reservation->reservation_id);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $reservation = json_decode($bodyGet);
        $this->assertEquals($data["startsAt"], $reservation->startsAt);
        $this->assertEquals($data["endsAt"], $reservation->endsAt);
    }

	public function testDeleteReservation()
	{
		echo "test DELETE reservation\n";
		$body = $this->client->delete('/reservations/1');
		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		
		// check reservation no longer exists
		$bodyGet = $this->client->get('/reservations/1');
		$this->assertEquals(404, $this->client->response->getStatusCode());
	}
    public function testDeleteReservationWithDelivery()
    {
        echo "test DELETE reservation linked to delivery\n";

        $user = \Api\Model\Contact::factory()->create([
            'email' => "info@klusbib.be", 'first_name' => "tester", 'last_name' => "de mock"
        ]);
        $tool = \Api\Model\Tool::factory()->create([
            'name' => "mytool", 'description' => "mydescription", 'brand' => "myBrand", 'type' => "myType"
        ]);
        $reservationStart = new DateTime();
        $reservationEnd = clone $reservationStart;
        $reservationEnd->add(new DateInterval('P7D'));
        $reservation = \Api\Model\Reservation::factory()->create([
            'startsAt' => $reservationStart, 'endsAt' => $reservationEnd, 'tool_id' => $tool->tool_id, 'user_id' => $user->id
        ]);
        $inventoryItem = \Api\Model\InventoryItem::factory()->create([]);
        $delivery = \Api\Model\Delivery::factory()->create(['comment' => 'opm', 'consumers' => 'hamer+beitel']);
        $item = \Api\Model\DeliveryItem::factory()->create([
            'delivery_id' => $delivery->id, 'inventory_item_id' => $inventoryItem->id, 'reservation_id' => $reservation->reservation_id
        ]);
        $delivery = $this->lookupDelivery($delivery->id);
        $this->assertEquals(1, count($delivery->items));

        // act
        $body = $this->client->delete('/reservations/' . $reservation->reservation_id);
        print_r($body);

        // assert
        $this->assertEquals(200, $this->client->response->getStatusCode());

        // check reservation no longer exists
        $bodyGet = $this->client->get('/reservations/' . $reservation->reservation_id);
        $this->assertEquals(404, $this->client->response->getStatusCode());

        // check delivery item no longer exists
        $delivery = $this->lookupDelivery($delivery->id);
        $this->assertEquals(0, count($delivery->items));
    }

    /**
     * @param $delivery
     * @return array
     */
    private function lookupDelivery($deliveryId): stdClass
    {
        $bodyGet = $this->client->get('/deliveries/' . $deliveryId);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        echo "delivery:\n";
        print_r($bodyGet);
        echo "\n\n";
        $delivery = \json_decode($bodyGet);
        return $delivery;
    }

}