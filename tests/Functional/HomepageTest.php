<?php

namespace Tests\Functional;

use DateTime;
use DateInterval;
use Tests\DbUnitArrayDataSet;

class HomepageTest extends BaseDBTestCase
{
	public function getDataSet()
	{
		$this->startdate = new DateTime();
		$this->enddate = clone $this->startdate;
		$this->enddate->add(new DateInterval('P365D'));
		
		return new DbUnitArrayDataSet(array(
				'users' => array(
						array('user_id' => 1, 'firstname' => 'firstname', 'lastname' => 'lastname',
								'role' => 'admin', 'email' => 'admin@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
								'state' => 'ACTIVE'
						),
				)
		));
	}
	
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
    }

    /**
     * Test that the index route won't accept a post request
     */
    public function testPostHomepageNotAuthorized()
    {
        $response = $this->withTokenFor("unknown@klusbib.be", "invalid")
            ->runApp('POST', '/');
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test that the index route won't accept a post request
     */
    public function testPostHomepageNotAllowed()
    {
    	$response = $this->withTokenFor("admin@klusbib.be", "test")
    		->runApp('POST', '/');
        $this->assertEquals(404, $response->getStatusCode());
    }
}