<?php

use PHPUnit\Framework\TestCase;
use Api\User\UserManager;
require_once __DIR__ . '/../../test_env.php';


final class UserManagerTest extends TestCase
{
    public function testGet()
    {
        $this->markTestSkipped("not yet implemented");
        // FIXME: complete test
        $inventory = null;
        $logger = null;
        $userMgr = new UserManager($inventory, $logger);
        $id = "123";
        $user = $userMgr->getById($id);
        $this->assertTrue(isset($user));
    }
}