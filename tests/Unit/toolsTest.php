<?php
use Api\Token;
use Tests\DbUnitArrayDataSet;

class ToolsTest extends LocalDbWebTestCase
{
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		return new DbUnitArrayDataSet(array(
				'tools' => array(
						array('tool_id' => 1, 'name' => 'tool 1', 'description' => 'description 1', 'code' => 'KB-000-18-001',
								'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'wood',
								'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
								'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 1
						),
						array('tool_id' => 2, 'name' => 'tool 2', 'description' => 'description 2', 'code' => 'KB-000-17-001',
								'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'wood',
								'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 
								'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 1
						),
						array('tool_id' => 3, 'name' => 'tool 3', 'description' => 'description 3', 'code' => 'KB-000-17-003',
								'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'construction',
								'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
								'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 1
						),
						array('tool_id' => 4, 'name' => 'tool 4', 'description' => 'description 4', 'code' => 'KB-000-17-002',
                            'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'construction',
                            'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
                            'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 0
                        )
				),
		));
	}
	
	public function testGetTools()
	{
		echo "test GET tools\n";
		$body = $this->client->get('/tools');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tools = json_decode($body);
		$this->assertEquals(3, count($tools));
	}

    public function testGetToolsAll()
    {
        echo "test GET all tools\n";
        $body = $this->client->get('/tools?_all=true');
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $tools = json_decode($body);
        $this->assertEquals(4, count($tools));
    }

    public function testGetToolsFilterCategory()
    {
        echo "test GET tools filter category\n";
        $body = $this->client->get('/tools?category=wood');
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $tools = json_decode($body);
        $this->assertEquals(2, count($tools));
        $this->assertEquals('wood', $tools[0]->category);
        $this->assertEquals('wood', $tools[1]->category);
    }

    public function testGetToolsPagination()
    {
        echo "test GET tools filter category\n";
        $body = $this->client->get('/tools?_perPage=2');
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $tools = json_decode($body);
        $this->assertEquals(2, count($tools));
        $this->assertEquals('KB-000-17-001', $tools[0]->code);
        $this->assertEquals('KB-000-17-003', $tools[1]->code);
        $body = $this->client->get('/tools?_page=2&_perPage=2');
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $tools = json_decode($body);
        $this->assertEquals(1, count($tools));
        $this->assertEquals('KB-000-18-001', $tools[0]->code);
    }

	public function testGetTool()
	{
		echo "test GET tool\n";
		$body = $this->client->get('/tools/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tool = json_decode($body);
		$this->assertEquals("1", $tool->tool_id);
		$this->assertEquals("tool 1", $tool->name);
		$this->assertEquals("description 1", $tool->description);
		$this->assertEquals("Makita", $tool->brand);
		$this->assertEquals("ABC-123", $tool->type);
		$this->assertEquals("00012345", $tool->serial);
		$this->assertEquals("2017", $tool->manufacturing_year);
		$this->assertEquals("http://manufacturer.com", $tool->manufacturer_url);
		$this->assertEquals("my doc", $tool->doc_url);
		$this->assertEquals("/assets/img/tool.jpg", $tool->img);
		$this->assertEquals("25", $tool->replacement_value);
	}
	
	public function testPostTools()
	{
		echo "test POST tools\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["tools.all"]);

		$data = array("name" => "myname", 
				"description" => "my description of tool",
				"category" => "my category"
		);
		$body = $this->client->post('/tools', $data);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tool = json_decode($body);
		$this->assertNotNull($tool->tool_id);
		
		// check tool has properly been updated
		$bodyGet = $this->client->get('/tools/' . $tool->tool_id);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tool = json_decode($bodyGet);
		$this->assertEquals($data["name"], $tool->name);
		$this->assertEquals($data["description"], $tool->description);
		$this->assertEquals($data["category"], $tool->category);
		
	}
	
	public function testPutTool()
	{
		echo "test PUT tool\n";
		$data = array("name" => "my new name", 
				"description" =>"my new description",
				"category" => "my new category",
				"brand" => "my new brand",
				"type" => "my new type",
				"serial" => "654321",
				"manufacturing_year" => "2015",
				"manufacturer_url" => "http://manufacturer.com/product",
				"doc_url" => "my doc",
				"img" => "new img url",
				"replacement_value" => "20",
		);
		$header = array();
		$this->client->put('/tools/1', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());

		// check tool has properly been updated
		$bodyGet = $this->client->get('/tools/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tool = json_decode($bodyGet);
		$this->assertEquals($data["name"], $tool->name);
		$this->assertEquals($data["description"], $tool->description);
		$this->assertEquals($data["category"], $tool->category);
		$this->assertEquals($data["brand"], $tool->brand);
		$this->assertEquals($data["type"], $tool->type);
		$this->assertEquals($data["serial"], $tool->serial);
		$this->assertEquals($data["manufacturing_year"], $tool->manufacturing_year);
		$this->assertEquals($data["manufacturer_url"], $tool->manufacturer_url);
		$this->assertEquals($data["doc_url"], $tool->doc_url);
		$this->assertEquals($data["img"], $tool->img);
		$this->assertEquals($data["replacement_value"], $tool->replacement_value);
	}

	public function testDeleteTool()
	{
		echo "test DELETE tool\n";
		$this->client->delete('/tools/1');
		$body = $this->assertEquals(200, $this->client->response->getStatusCode());
		
		// check tool no longer exists
		$bodyGet = $this->client->get('/tools/1');
		$this->assertEquals(404, $this->client->response->getStatusCode());
	}
	
}