<?php
use Api\Token;
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
				'users' => array(
						array('user_id' => 1, 'firstname' => 'firstname', 'lastname' => 'lastname',
								'role' => 'admin', 'email' => 'admin@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
						array('user_id' => 2, 'firstname' => 'harry', 'lastname' => 'De Handige',
								'role' => 'volunteer', 'email' => 'harry@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
						array('user_id' => 3, 'firstname' => 'daniel', 'lastname' => 'De Deler',
								'role' => 'member', 'email' => 'daniel@klusbib.be',
								'hash' => password_hash("test", PASSWORD_DEFAULT),
								'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
								'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
				),
		));
	}
	
	public function testPostToken()
	{
		echo "test POST token";
		
		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0"	);
		$this->client->post('/token', null, $header);
		$this->assertEquals(201, $this->client->response->getStatusCode());
		// 		$this->assertEquals($this->app->get('settings')['version'], $this->client->response->getBody());
	}
}