<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;

class ConsumersTest extends LocalDbWebTestCase
{
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		return new DbUnitArrayDataSet(array(
				'consumers' => array(
					array("consumer_id" => "1","Category" => "Sanding paper","Brand" => "Metabo","Reference" => "624025",
						"Description" => "Sanding disc velcro 150mm 6g alox P240","Unit" => "piece",
						"Price" => "1.25","Stock" => "18","Low_Stock" => "10","Packed_Per" => "25","Provider" => "Lecot",
						"Comment" => "","Public" => "1"),
					array("consumer_id" => "60","Category" => "Sanding paper",
						"Brand" => "Metabo","Reference" => "624033","Description" => "Sanding disc velcro 150mm 6g alox P180 white",
						"Unit" => "piece","Price" => "1.25","Stock" => "30","Low_Stock" => "10","Packed_Per" => "25","Provider" => "Lecot",
						"Comment" => "","Public" => "1"),
				array("consumer_id" => "61","Category" => "Sanding paper",
						"Brand" => "Sein","Reference" => "1234","Description" => "Sanding disc Sein",
						"Unit" => "piece","Price" => "0.75","Stock" => "5","Low_Stock" => "5","Packed_Per" => "25","Provider" => "Lecot",
						"Comment" => "","Public" => "0")
				),
		));
	}
	
	public function testGetConsumers()
	{
		echo "test GET consumers\n";
		$body = $this->client->get('/consumers');
		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$consumers = json_decode($body);
		$this->assertEquals(3, count($consumers));
	}

	public function testGetConsumer()
	{
		echo "test GET consumer\n";
		$body = $this->client->get('/consumers/1');
		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$consumer = json_decode($body);
		$this->assertEquals("1", $consumer->consumer_id);
		$this->assertEquals("Sanding paper", $consumer->category);
		$this->assertEquals("Metabo", $consumer->brand);
		$this->assertEquals("624025", $consumer->reference);
		$this->assertEquals("Sanding disc velcro 150mm 6g alox P240", $consumer->description);
		$this->assertEquals("piece", $consumer->unit);
		$this->assertEquals("1.25", $consumer->price);
		$this->assertEquals("18", $consumer->stock);
		$this->assertEquals("10", $consumer->low_stock);
		$this->assertEquals("25", $consumer->packed_per);
		$this->assertEquals("Lecot", $consumer->provider);
		$this->assertEquals("", $consumer->comment);
		$this->assertEquals("1", $consumer->public);
	}
	
	public function testPostConsumers()
	{
		echo "test POST consumers\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["consumers.all"]);

		$data = array("brand" => "brand", 
				"reference" => "my reference",
				"description" => "my description of consumer",
				"category" => "my category"
		);
		$body = $this->client->post('/consumers', $data);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$consumer = json_decode($body);
		$this->assertNotNull($consumer->consumer_id);
		
		// check tool has properly been updated
		$bodyGet = $this->client->get('/consumers/' . $consumer->consumer_id);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$consumer = json_decode($bodyGet);
		$this->assertEquals($data["brand"], $consumer->brand);
		$this->assertEquals($data["reference"], $consumer->reference);
		$this->assertEquals($data["description"], $consumer->description);
		$this->assertEquals($data["category"], $consumer->category);
		
	}
	
	public function testPutConsumer()
	{
		echo "test PUT tool\n";
		$data = array("consumer_id" => "1","category" => "new category","brand" => "new brand","reference" => "12345",
				"description" => "new description","unit" => "new unit",
				"price" => "0.25","stock" => "5","low_stock" => "2","packed_per" => "20","provider" => "new provider",
				"comment" => "new comment","public" => "0"
		);
		$header = array();
		$this->client->put('/consumers/1', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());

		// check tool has properly been updated
		$bodyGet = $this->client->get('/consumers/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$consumer = json_decode($bodyGet);
		$this->assertEquals($data["category"], $consumer->category);
		$this->assertEquals($data["brand"], $consumer->brand);
		$this->assertEquals($data["reference"], $consumer->reference);
		$this->assertEquals($data["description"], $consumer->description);
		$this->assertEquals($data["unit"], $consumer->unit);
		$this->assertEquals($data["price"], $consumer->price);
		$this->assertEquals($data["stock"], $consumer->stock);
		$this->assertEquals($data["low_stock"], $consumer->low_stock);
		$this->assertEquals($data["packed_per"], $consumer->packed_per);
		$this->assertEquals($data["provider"], $consumer->provider);
		$this->assertEquals($data["comment"], $consumer->comment);
		$this->assertEquals($data["public"], $consumer->public);
	}

	public function testDeleteConsumer()
	{
		echo "test DELETE consumer\n";
		$this->client->delete('/consumers/1');
		$body = $this->assertEquals(200, $this->client->response->getStatusCode());
		
		// check tool no longer exists
		$bodyGet = $this->client->get('/consumers/1');
		$this->assertEquals(404, $this->client->response->getStatusCode());
	}
	
}