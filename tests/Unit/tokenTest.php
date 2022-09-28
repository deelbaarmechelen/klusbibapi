<?php
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;

class TokenTest extends LocalDbWebTestCase
{
	private $startdate;
	private $enddate;

	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->startdate = new DateTime();
		$this->enddate = clone $this->startdate;
		$this->enddate->add(new DateInterval('P365D'));
	
		return new DbUnitArrayDataSet(array(
				'contact' => array(
						array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
								'role' => 'admin', 'email' => 'admin@klusbib.be',
								'password' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
								'state' => 'ACTIVE'
						),
						array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
								'role' => 'volunteer', 'email' => 'harry@klusbib.be',
								'password' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
								'state' => 'ACTIVE'
						),
						array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
								'role' => 'member', 'email' => 'daniel@klusbib.be',
								'password' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s'),
								'state' => 'DISABLED'
						),
				),
		));
	}

    public function testPostToken()
	{
		echo "test POST /token\n";
		
		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0"	);
		$this->setUser('admin@klusbib.be');
		$body = $this->client->post('/token', null, $header);
// 		print_r($body);
		$this->assertEquals(201, $this->client->response->getStatusCode());
	}
    public function testPostTokenGuest()
	{
		echo "test POST /token/guest\n";

		$body = $this->client->post('/token/guest');
// 		print_r($body);
		$this->assertEquals(201, $this->client->response->getStatusCode());
	}

	public function testPostToken_inactiveUser()
	{
		echo "test POST /token (inactive user)\n";
	
		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0"	);
		$this->setUser('daniel@klusbib.be');
		$body = $this->client->post('/token', null, $header);
		$this->assertEquals(403, $this->client->response->getStatusCode());
	}
	
}
