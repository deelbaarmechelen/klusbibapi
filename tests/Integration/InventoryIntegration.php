<?php
use PHPUnit\Framework\TestCase;
use Api\Mail\MailManager;
use Tests\Mock\PHPMailerMock;
use Api\Model\User;
use Api\Model\Tool;
use Api\Model\Reservation;
use Api\Inventory\SnipeitInventory;

require_once __DIR__ . '/../test_env.php';

/**
 * Class InventoryIntegration
 * Integration tests for Inventory (snipe IT)
 * Performs real calls to a test inventory, so make sure inventory application is up and running
 */
final class InventoryIntegration extends TestCase
{
    private $inventory;
    private $logger;

    const TEST_INVENTORY_USER_ID = 11;

    protected function setUp()
    {
        $this->logger = $this->createMock('\Psr\Log\LoggerInterface');
        $this->inventory = new SnipeitInventory(new \GuzzleHttp\Client(['base_uri' => TEST_INVENTORY_URL . '/api/v1/']),
            TEST_INVENTORY_API_KEY, $this->logger);
    }

    public function testGetUserByExtId(){
        $user = $this->inventory->getUserByExtId(1);
        $this->assertNotNull($user);
        $user = $this->inventory->getUserByExtId(-1);
        $this->assertNull($user);
    }

    /**
     * lookup user by email
     * @param $email
     * @return User the user or null if not found
     */
    public function testGetUserByEmail(){
        $email = 'me@klusbib.be';
        $user = $this->inventory->getUserByEmail($email);
        $this->assertNotNull($user);
        $email = 'unknown@space.nowhere';
        $user = $this->inventory->getUserByEmail($email);
        $this->assertNull($user);
    }

    /**
     * lookup all tools assigned to this user
     * @param $userId
     * @param int $offset paging offset
     * @param int $limit max number of tools to return
     * @return mixed
     */
    public function testGetUserTools() {
        $tools = $this->inventory->getUserTools(self::TEST_INVENTORY_USER_ID);
        $this->assertNotNull($tools);
        $this->assertEquals(1, count($tools));
    }

    public function testGetToolsByState()
    {
        $tools = $this->inventory->getToolsByState(\Api\Model\ToolState::IN_USE);
        $this->assertNotNull($tools);
        $this->assertEquals(1, count($tools));
    }
    public function testGetToolsByStateWithOffset()
    {
        $offset = 1;
        $limit = 2;
        $tools = $this->inventory->getToolsByState(\Api\Model\ToolState::READY, $offset, $limit);
        $this->assertNotNull($tools);
        $this->assertEquals(2, count($tools));
    }
}