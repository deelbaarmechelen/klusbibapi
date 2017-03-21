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
	public function testGetImage()
	{
		echo "test GET users\n";
		$body = $this->client->get('/image/3.jpg');
		// 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
// 		$users = json_decode($body);
// 		$this->assertEquals(3, count($users));
	}
	
	public function testShouldReturnImage()
	{
		Environment::mock(array(
				/* TODO: Figure out why setting this breaks test. */
				//"SCRIPT_NAME" => "/index.php",
				"PATH_INFO" => "/images/3-200x200.jpg"
		));
		$app = new \Slim\App();
		$app->add(new Api\Middleware\ImageResize([
				"extensions" => ["jpg", "jpeg"],
				"quality" => 90,
				"sizes" => ["400x200", "x200", "200x", "100x100"]
		]));
		$app->get("/foo", function () {
			echo "Success";
		});
			$middleware = new \Api\Middleware\ImageResize(array(
			));
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
// 	public function testShouldReturnHtml()
// 	{
// 		Environment::mock(array(
// 				"SCRIPT_NAME" => "/index.php",
// 				"PATH_INFO" => "/foo"
// 		));
// 		$app = new \Slim\App();
// 		$app->add(new Api\Middleware\ImageResize([
// 				"extensions" => ["jpg", "jpeg"],
// 				"quality" => 90,
// 				"sizes" => ["400x200", "x200", "200x", "100x100"]
// 		]));
// 		$app->get("/foo", function () {
// 			echo "Success";
// 		});
// 			$middleware = new \Api\Middleware\ImageResize(array(
// 			));
// 			$middleware->__invoke();
// 			$this->assertEquals(200, $app->response()->status());
// 			$this->assertEquals("text/html", $app->response()->header("Content-Type"));
// 	}
}