<?php

use PHPUnit\Framework\TestCase;
use Api\Validator\ReservationValidator;
use Api\Model\Tool;
use Tests\DbUnitArrayDataSet;

require_once __DIR__ . '/../../test_env.php';

final class ReservationValidatorTest extends LocalDbWebTestCase
{
    public function setup($dependencies = null, WebTestClient $client = NULL, $useMiddleware = false)
    {
        parent::setUp($dependencies, $client, $useMiddleware);
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        $this->startdate = new DateTime();
        $this->enddate = clone $this->startdate;
        $this->enddate->add(new DateInterval('P7D'));
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
            'tools' => array(
                array('tool_id' => 1, 'name' => 'tool 1', 'description' => 'description 1',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25',
                    'code' => 'KB-000-17-001', 'owner_id' => 0
                ),
                array('tool_id' => 2, 'name' => 'tool 2', 'description' => 'description 2',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25',
                    'code' => 'KB-002-17-001', 'owner_id' => 2
                ),
                array('tool_id' => 3, 'name' => 'tool 3', 'description' => 'description 3',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com',
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25',
                    'code' => 'KB-000-17-002', 'owner_id' => 0
                )
            ),
        ));
    }

	public function testIsValidReservationData()
	{
//		$this->markTestSkipped();
		$logger = $this->loggerDummy();
		$reservation = array("user_id" => 1, "tool_id" => 1);
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);

        // Configure the stub.
        $toolManager->method('toolExists')
            ->willReturn(true);
		$result = ReservationValidator::isValidReservationData($reservation, $logger, $toolManager);
		$this->assertTrue($result);
	}

    public function testIsValidReservationData_UnknownUser()
    {
        $logger = $this->loggerDummy();
        $reservation = array("user_id" => 99, "tool_id" => 1);
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);
        $toolManager->method('toolExists')
            ->willReturn(true);
        $result = ReservationValidator::isValidReservationData($reservation, $logger,$toolManager);
        $this->assertFalse($result);
    }

    public function testIsValidReservationData_UnknownTool()
    {
        $logger = $this->loggerDummy();
        $reservation = array("user_id" => 1, "tool_id" => 99);
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);
        $toolManager->method('toolExists')
            ->willReturn(false);
        $result = ReservationValidator::isValidReservationData($reservation, $logger,$toolManager);
        $this->assertFalse($result);
    }

    public function testIsValidReservationData_InvalidState()
    {
        $logger = $this->loggerDummy();
        $reservation = array("user_id" => 1, "tool_id" => 1, "state" => "INVALID");
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);
        $toolManager->method('toolExists')
            ->willReturn(true);
        $result = ReservationValidator::isValidReservationData($reservation, $logger,$toolManager);
        $this->assertFalse($result);
    }

    private function loggerDummy() {
		return $this->createMock('\Psr\Log\LoggerInterface');
	}
}