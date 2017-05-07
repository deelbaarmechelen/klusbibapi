<?php

namespace Tests\Functional;

class HomepageTest extends BaseTestCase
{
    /**
     * Test that the welcome route returns a rendered response containing the text 'Klusbib API' and no longer 'SlimFramework'
     */
    public function testGetWelcome()
    {
        $response = $this->runApp('GET', '/welcome');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Klusbib API', (string)$response->getBody());
        $this->assertNotContains('SlimFramework', (string)$response->getBody());
    }

    /**
     * Test that the tools route returns a rendered list of tools
     */
    public function testGetHomepageWithGreeting()
    {
        $response = $this->runApp('GET', '/tools');

        $this->assertEquals(200, $response->getStatusCode());
//         $this->assertContains('Hello name!', (string)$response->getBody());
    }

    /**
     * Test that the index route won't accept a post request
     */
    public function testPostHomepageNotAllowed()
    {
        $response = $this->runApp('POST', '/', ['test']);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertContains('Method not allowed', (string)$response->getBody());
    }
}