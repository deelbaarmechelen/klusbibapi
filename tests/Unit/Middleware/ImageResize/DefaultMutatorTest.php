<?php
use Api\Middleware\ImageResize\DefaultMutator;

class DefaultMutatorTest extends LocalWebTestCase
{
	public function testParse()
	{
		echo "test mutator parse";
		$_SERVER["DOCUMENT_ROOT"] = __DIR__ . "/../../../../public";
		$mutator = new DefaultMutator(array(), $this->mockImage());
		$target = '/uploads/KB-000-17-001-x200.jpg';
		$parsed = $mutator->parse($target);
		
		$this->assertTrue(is_array($parsed));
		$this->assertEquals(__DIR__ . "/../../../../public//uploads/KB-000-17-001.jpg", $parsed["source"]);
// 		print_r($parsed);
		
		static $regexp = "/(?<original>.+)-(?<size>(?<width>\d*)x(?<height>\d*))-?(?<signature>[0-9a-z]*)/";
		$pathinfo = pathinfo($target);
// 		print_r($pathinfo);
		$result = preg_match($regexp, $pathinfo["filename"], $matches);
		$this->assertEquals(1, $result);
// 		print_r($matches);
		$this->assertEquals('KB-000-17-001', $matches['original']);
		$this->assertEquals('x200', $matches['size']);
		
// 		$this->assertEquals(201, $this->client->response->getStatusCode());
		// 		$this->assertEquals($this->app->get('settings')['version'], $this->client->response->getBody());
	}
	
	private function mockImage() {
		return null;
	}
}