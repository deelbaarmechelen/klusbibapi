<?php

use PHPUnit\Framework\TestCase;
use Api\Validator\UserValidator;

final class UserValidatorTest extends TestCase
{
	public function testRegistrationNumberIsNumeric()
	{
		$errors = array();
	    $logger = $this->loggerDummy();
		$registrationNumber = "99010112390";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger, $errors);
		$this->assertTrue($result);
		
		// number not numeric
		$registrationNumber = "abcdef";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger, $errors);
		$this->assertFalse($result);
	}
	
	public function testRegistrationNumberLength()
	{
		$logger = $this->loggerDummy();
		$registrationNumber = "99010112390";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger, $errors);
		$this->assertTrue($result);
		
		// number too long
		$registrationNumber = "00010112345123";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger, $errors);
		$this->assertFalse($result);
		
		// number too short
		$registrationNumber = "000101123";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger, $errors);
		$this->assertFalse($result);
	}
	
	public function testRegistrationCheckDigit97()
	{
		$logger = $this->loggerDummy();
		
		// before year 2000
		$registrationNumber = "99010112390";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger,$errors);
		$this->assertTrue($result);
		
		// above year 2000
		$registrationNumber = "00010112377";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger,$errors);
		$this->assertTrue($result);
		
		// mod97 invalid
		$registrationNumber = "00010112399";
		$result = UserValidator::isValidRegistrationNumber($registrationNumber, $logger, $errors);
		$this->assertFalse($result);
	}
	
	private function loggerDummy() {
		return $this->createMock('\Psr\Log\LoggerInterface');
	}
}