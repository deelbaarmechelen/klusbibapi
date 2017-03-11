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
						array('tool_id' => 1, 'name' => 'tool 1', 'description' => 'description 1'),
						array('tool_id' => 2, 'name' => 'tool 2', 'description' => 'description 2'),
						array('tool_id' => 3, 'name' => 'tool 3', 'description' => 'description 3'),
				),
		));
	}
	
	public function testGetTools()
	{
		echo "test GET tools\n";
		$body = $this->client->get('/tools');
		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tools = json_decode($body);
		$this->assertEquals(3, count($tools));
	}

	public function testPostTools()
	{
		echo "test POST tools\n";
		// get token
		$data = ["tools.all"];
		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0",
				"PHP_AUTH_USER" => "test",
				"PHP_AUTH_PW" => "test"
		);
		$response = $this->client->post('/token', $data, $header);
		$responseData = json_decode($response);
		
		$scopes = array("tools.all");
// 		$sub = 'tester';
// 		$token = Token::generateToken($scopes, $sub);
// 		$data = array("name" => "myName",
// 				"description" =>"my description");
		
		$header = array('Authorization' => "bearer $responseData->token");
		$container = $this->app->getContainer();
// 		$decoded = json_decode('{"iat":1489196152,"exp":1489203352,"jti":"34tViX9UTPsdCiAGYThOuZ","sub":"test","scope":["tools.create","tools.read","tools.update","tools.delete","tools.list","tools.all"]}');
// 		$container["token"]->decoded = $decoded;
// 		print_r($this->app->getContainer()["token"]);

		$data = array("name" => "myname", "description" => "my description of tool");
		$this->client->post('/tools', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());
	}
	public function testGetTool()
	{
		echo "test GET tool\n";
		$body = $this->client->get('/tools/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tool = json_decode($body);
		$this->assertEquals("1", $tool->id);
		$this->assertEquals("tool 1", $tool->name);
		$this->assertEquals("description 1", $tool->description);
	}
	
	public function testPutTool()
	{
		echo "test PUT tool\n";
		$data = array("name" => "my new name", 
				"description" =>"my new description");
		$header = array();
		$this->client->put('/tools/1', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());

		// check tool has properly been updated
		$bodyGet = $this->client->get('/tools/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$tool = json_decode($bodyGet);
		$this->assertEquals($data["name"], $tool->name);
		$this->assertEquals($data["description"], $tool->description);
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