<?php
use Api\Token;

class ToolsTest extends LocalWebTestCase
{
	public function testPostToken()
	{
		echo "test POST token";
		$data = ["tools.all"];
		
		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0",
				"PHP_AUTH_USER" => "test",
				"PHP_AUTH_PW" => "test"
		);
		$this->client->post('/token', $data, $header);
		$this->assertEquals(201, $this->client->response->getStatusCode());
		// 		$this->assertEquals($this->app->get('settings')['version'], $this->client->response->getBody());
	}
}