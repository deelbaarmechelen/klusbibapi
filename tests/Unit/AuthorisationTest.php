<?php

use PHPUnit\Framework\TestCase;
use Api\Authorisation;
use Api\Token;

class AuthorisationTest extends TestCase
{
	public function testCheckUserAccess_Supporter()
	{
		$sub = "1";
// 		$key = "mytestkey";
// 		putenv("JWT_SECRET=".$key);
		$token = Token::createToken(Token::allowedScopes("supporter"), $sub);
		$resourceId = "1";
		$this->assertFalse(Authorisation::checkUserAccess($token, "list", $resourceId));
		$this->assertTrue(Authorisation::checkUserAccess($token, "read", $resourceId));
		$this->assertFalse(Authorisation::checkUserAccess($token, "read", "5"));
		$this->assertFalse(Authorisation::checkUserAccess($token, "create", $resourceId));
		$this->assertTrue(Authorisation::checkUserAccess($token, "update", $resourceId));
		$this->assertFalse(Authorisation::checkUserAccess($token, "update", "5"));
		$this->assertFalse(Authorisation::checkUserAccess($token, "delete", $resourceId));
		
	}
}
