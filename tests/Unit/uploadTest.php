<?php
use Api\Token;

class UploadTest extends LocalWebTestCase {
	public function testUpload()
	{
		$data = array("name" => "myname",
				"description" => "my description of tool",
				"category" => "my category"
		);
		$body = $this->client->post('/upload', $data);
		$this->assertEquals(200, $this->client->response->getStatusCode());
	}
	
}
