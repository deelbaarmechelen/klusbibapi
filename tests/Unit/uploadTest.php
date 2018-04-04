<?php

use Slim\Http\UploadedFile;

class UploadTest extends LocalWebTestCase {
	public function testUpload()
	{
		$uploadedFiles = array('newfile' => $this->mockUploadedFile());
		$data = array("name" => "myname",
				"description" => "my description of tool",
				"category" => "my category"
		);
		print_r($uploadedFiles);
		$body = $this->client->post('/upload', $data, array(), $uploadedFiles);
		$this->assertEquals(200, $this->client->response->getStatusCode());
	}
	
	private function mockUploadedFile() {
		$uploadedFile = $this->getMockBuilder(UploadedFile::class)
			->setMethods(['moveTo'])
			// $file, $name = null, $type = null, $size = null, $error = UPLOAD_ERR_OK, $sapi = false
			->setConstructorArgs(array('/tmp/newfile', 'newfile', 'jpg','1024', UPLOAD_ERR_OK))
			->getMock();
		
		return $uploadedFile;
	}
}
