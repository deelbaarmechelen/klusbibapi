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
        $mailMgr = new MailManager(null, null, $logger);
        $userMgr = new UserManager($inventory, $logger, $mailMgr);
        $id = "123";
        $user = $userMgr->getById($id);
        $this->assertTrue(isset($user));
    }
}