<?php
use Tests\DbUnitArrayDataSet;

// class VersionTest extends LocalWebTestCase
class VersionTest extends LocalDbWebTestCase
{
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		return new DbUnitArrayDataSet(array(
				));
	}
	
	public function testVersion()
    {
        $this->client->get('/tools');
        $this->assertEquals(200, $this->client->response->getStatusCode());
    }
}