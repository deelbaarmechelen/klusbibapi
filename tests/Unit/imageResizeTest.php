<?php
use Api\Middleware\ImageResize;
use Api\Middleware\ImageResize\DefaultMutator;
use Slim\App;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\Uri;

// class ImageResizeTest extends \PHPUnit_Framework_TestCase
class ImageResizeTest extends LocalDbWebTestCase
{
	public function testShouldReturnImage()
	{
		Environment::mock(array(
				/* TODO: Figure out why setting this breaks test. */
// 				"SCRIPT_NAME" => "/index.php",
				"PATH_INFO" => "/images/3-x200.jpg"
		));
		$app = new \Slim\App();
		$middleware = new \Api\Middleware\ImageResize([
			"extensions" => ["jpg", "jpeg"],
			"quality" => 90,
			"sizes" => ["800x", "x800", "400x", "x400", "400x200", "x200", "200x", "100x100"]
		], $this->mockMutator());
		$app->add($middleware);
		$app->get("/foo", function () {
			echo "Success";
		});
		$_SERVER["DOCUMENT_ROOT"] = __DIR__ . '/../../public';
		
		$uri = new Uri("http", "host", 8080, "/uploads/3-x200.jpg");
		$headers = new Headers();
		$request = new Request("GET", $uri, $headers, [], [], new RequestBody());
		$response = new Response();
		$response = $middleware->__invoke($request, $response, $app);
		$this->assertEquals(200, $response->getStatusCode());
		$contentTypeHeaders = $response->getHeader("Content-Type");
		$this->assertEquals("image/jpeg", $contentTypeHeaders[0]);
	}
	
	private function mockMutator() {
		return new MockMutator();
	}
}

class MockMutator
{
	public function parse() {
		echo "MockMutator::parse\n";
		return array('extension' => 'jpg',
				'size' => 'x200',
				'signature' => null,
				'source' => '/uploads/3-x200.jpg'
		);
	}
	
	public function execute() {
		echo "MockMutator::execute\n";
	}
	
	public function encode() {
		echo "MockMutator::encode\n";
		return new MockImage();
	}
	public function save() {
		echo "MockMutator::save\n";
		
	}
	public function mime() {
		echo "MockMutator::mime\n";
		return "image/jpeg";
	}
}

class MockImage {
	public function getEncoded(){
		
	}
}
