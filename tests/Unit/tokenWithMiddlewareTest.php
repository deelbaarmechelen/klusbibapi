<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;

class TokenTestWithMiddleware extends LocalWebTestCase
{ // LocalWebTestCase runs with middleware enable by default

    public function testOptionToken()
    {
        echo "test Option token\n";

    //        require_once (__DIR__ . '/../../src/middleware.php');
    // should run with middelware to work -> use WebTestClientWithMiddleware? -> add require middleware.php in that class?
        $body = $this->client->options('/token', null);
        print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        print_r($this->client->response->getHeaders());
        $this->assertTrue($this->client->response->hasHeader('Access-Control-Allow-Origin'));
    }
}