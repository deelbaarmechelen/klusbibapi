<?php
use Tests\DbUnitArrayDataSet;


require_once __DIR__ . '/../test_env.php';

class DeliveryTest extends LocalDbWebTestCase
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
						'sku' => "KB-000-20-001",
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

	public function testGetDeliveries()
	{
		echo "test GET deliveries\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
		$body = $this->client->get('/deliveries');
		echo $body;
		$this->assertEquals(200, $this->client->response->getStatusCode());
        $deliveries = json_decode($body);
		$this->assertEquals(1, count($deliveries));
	}
	
	public function testGetDeliveriesUnauthorized()
	{
		echo "test GET deliveries unauthorized\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["deliveries.none"]);
		$body = $this->client->get('/deliveries');
		$this->assertEquals(403, $this->client->response->getStatusCode());
		$this->assertTrue(empty($body));
	}

    public function testGetDelivery()
    {
        echo "test GET delivery\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
        $body = $this->client->get('/deliveries/1');
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $delivery = json_decode($body);
        $this->assertEquals(1, $delivery->id);
        $this->assertEquals("here", $delivery->pick_up_address);
        $this->assertEquals("there", $delivery->drop_off_address);
    }

    public function testPostDelivery()
    {
        echo "test POST delivery\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
        $data = array("user_id" => 3,
            "state" => \Api\Model\DeliveryState::REQUESTED,
            "pick_up_address" => "here",
            "drop_off_address" => "there"
        );
        $body = $this->client->post('/deliveries', $data);
        print_r($body);
        $this->assertEquals(201, $this->client->response->getStatusCode());
        $delivery = json_decode($body);
        $this->assertEquals(2, $delivery->id);
        $this->assertEquals("here", $delivery->pick_up_address);
        $this->assertEquals("there", $delivery->drop_off_address);
    }

    public function testDeleteDelivery()
    {
        echo "test DELETE delivery\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
        $body = $this->client->delete('/deliveries/1');
        $this->assertEquals(200, $this->client->response->getStatusCode());

        // delete inexistent (=already deleted) item
        $body = $this->client->delete('/deliveries/99');
        $this->assertEquals(204, $this->client->response->getStatusCode());
    }

    public function testEditDelivery()
    {
        echo "test PUT delivery\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
        $data = array("user_id" => 3,
            "state" => \Api\Model\DeliveryState::CONFIRMED,
            "pick_up_address" => "not here",
            "drop_off_address" => "not there"
        );
        $body = $this->client->put('/deliveries/1', $data);
        print_r($body);
        $delivery = json_decode($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $this->assertEquals("not here", $delivery->pick_up_address);
        $this->assertEquals("not there", $delivery->drop_off_address);
        $this->assertEquals(\Api\Model\DeliveryState::CONFIRMED, $delivery->state);
    }

    public function testAddDeliveryItem()
    {
        echo "test POST delivery/{id}/items\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
        $data = array("item_id" => 2 );
        $body = $this->client->post('/deliveries/1/items', $data);
        $this->assertEquals(201, $this->client->response->getStatusCode());
    }
    public function testRemoveDeliveryItem()
    {
        echo "test DELETE delivery/{id}/items/{itemId}\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["deliveries.all"]);
        $body = $this->client->delete('/deliveries/1/items/1');
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
    }

}