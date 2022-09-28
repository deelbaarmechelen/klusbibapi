<?php
use Tests\DbUnitArrayDataSet;


require_once __DIR__ . '/../test_env.php';

class LendingTest extends LocalDbWebTestCase
{
	private $createdate;
	private $updatedate;
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->createdate = new DateTime();
        $this->updatedate = clone $this->createdate;
        $this->startdate = new DateTime();
        $this->duedate = clone $this->startdate;
		$this->duedate->add(new DateInterval('P7D'));

		return new DbUnitArrayDataSet(array(
            'contact' => array(
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                ),
            ),
			'lendings' => array(
				array('lending_id' => 1, 'user_id' => 3, 'tool_id' => 1,
						'start_date' => $this->startdate->format('Y-m-d H:i:s'),
                        'due_date' => $this->duedate->format('Y-m-d H:i:s'),
						'created_at' => $this->createdate->format('Y-m-d H:i:s'),
						'updated_at' => $this->updatedate->format('Y-m-d H:i:s')
						),
			),
		));
	}
	
	public function testGetLendings()
	{
		echo "test GET lendings\n";
        $this->setUser('daniel@klusbib.be');
        $this->setToken('3', ["lendings.all"]);
		$body = $this->client->get('/lendings');
 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
        $lendings = json_decode($body);
		$this->assertEquals(1, count($lendings));
	}
	
	public function testGetLendingsUnauthorized()
	{
		echo "test GET lendings unauthorized\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["lendings.none"]);
		$body = $this->client->get('/lendings');
		$this->assertEquals(403, $this->client->response->getStatusCode());
		$this->assertTrue(empty($body));
	}

}