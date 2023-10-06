<?php

use PHPUnit\Framework\TestCase;
use Api\Validator\ReservationValidator;
use Api\Model\Tool;
use Tests\DbUnitArrayDataSet;

require_once __DIR__ . '/../../test_env.php';

final class ReservationValidatorTest extends LocalDbWebTestCase
{
    public function setup($dependencies = null, WebTestClient $client = NULL, $useMiddleware = false) : void
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
            'contact' => array(
                array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
                    'role' => 'admin', 'email' => 'admin@klusbib.be',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
                    'role' => 'volunteer', 'email' => 'harry@klusbib.be',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be',
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
            ),
            'kb_tools' => array(
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
        $errors = array();
		$result = ReservationValidator::isValidReservationData($reservation, $logger, $toolManager, $errors);
		$this->assertTrue($result);
		$this->assertTrue(count($errors) == 0);
	}

    public function testIsValidReservationData_UnknownUser()
    {
        $logger = $this->loggerDummy();
        $reservation = array("user_id" => 99, "tool_id" => 1);
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);
        $toolManager->method('toolExists')
            ->willReturn(true);
        $errors = array();
        $result = ReservationValidator::isValidReservationData($reservation, $logger,$toolManager,$errors);
        $this->assertFalse($result);
    }

    public function testIsValidReservationData_UnknownTool()
    {
        $logger = $this->loggerDummy();
        $reservation = array("user_id" => 1, "tool_id" => 99);
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);
        $toolManager->method('toolExists')
            ->willReturn(false);
        $errors = array();
        $result = ReservationValidator::isValidReservationData($reservation, $logger,$toolManager,$errors);
        $this->assertFalse($result);
    }

    public function testIsValidReservationData_InvalidState()
    {
        $logger = $this->loggerDummy();
        $reservation = array("user_id" => 1, "tool_id" => 1, "state" => "INVALID");
        $toolManager = $this->createMock(\Api\Tool\ToolManager::class);
        $toolManager->method('toolExists')
            ->willReturn(true);
        $errors = array();
        $result = ReservationValidator::isValidReservationData($reservation, $logger,$toolManager,$errors);
        $this->assertFalse($result);
    }

    private function loggerDummy() {
		return $this->createMock('\Psr\Log\LoggerInterface');
	}
}