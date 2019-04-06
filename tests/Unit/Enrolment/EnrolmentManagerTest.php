<?php

use PHPUnit\Framework\TestCase;
use Api\Enrolment\EnrolmentManager;

require_once __DIR__ . '/../../test_env.php';


final class EnrolmentManagerTest extends TestCase
{
    public function testGetMembershipEndDate()
    {
        $startDate = "2019-01-01";
        $endDate = EnrolmentManager::getMembershipEndDate($startDate);

        $this->assertEquals("2020-01-01", $endDate);
    }
    public function testGetMembershipEndDateEndOfYear()
    {
        $this->markTestSkipped("need to mock current date!");
        $startDate = "2019-12-02";
        $endDate = EnrolmentManager::getMembershipEndDate($startDate);

        $this->assertEquals("2020-12-31", $endDate);
    }
    public function testGetMembershipEndDateInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid date format (expecting 'YYY-MM-DD'): 2019/01/01");

        $startDate = "2019/01/01";
        $endDate = EnrolmentManager::getMembershipEndDate($startDate);

        $this->assertEquals("2020-01-01", $endDate);
    }
}