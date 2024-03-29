<?php

namespace Tests\Mock;

use Api\Inventory\Inventory;
use Api\Model\Accessory;
use Api\Model\Tool;
use Api\Model\ToolState;
use Api\Model\Contact;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class InventoryMock implements Inventory
{

    private $client;
    private $apiKey;
    private $logger;

    private static $users = array();
    private static $tools = array();

    /**
     * InventoryMock constructor.
     * @param $client HttpClient to call inventory
     * @param $apikey api key used for authentication at inventory
     * @param $logger where to write log messages
     */
    public function __construct($client, $apikey, $logger)
    {
        $this->client = $client;
        $this->apiKey = $apikey;
        $this->logger = $logger;
    }

    public static function addUser(Contact $user) {
        array_push(InventoryMock::$users, $user);
    }
    public static function removeUser(Contact $user) {
        $key = array_search($user, InventoryMock::$users);
        if ($key) {
            unset($key);
        }
    }

    public static function clearUsers() {
        InventoryMock::$users = array();
    }
    public function getTools($offset = 0, $limit = 1000)
    {
//        if ($showAll === true) {
//            $builder = Capsule::table('tools');
//        } else {
//            $builder = Capsule::table('tools')
//                ->where('visible', true);
//        }
//        if (isset($category)) {
//            $builder = $builder->where('category', $category);
//        }
//        $tools = $builder->orderBy($sortfield, $sortdir)->get();
//        return $tools;
        return Capsule::table('kb_tools')->get();
    }

    public function getToolById($id) : ?Tool
    {
        $tool = \Api\Model\Tool::find($id);
        return $tool;
    }

    public function postUser(Contact $user)
    {
        $newuser = new Contact();
        $newuser->user_ext_id = 1;
        array_push(InventoryMock::$users, $newuser);
        return $newuser;
    }

    /**
     * lookup user by user_ext_id
     * @param $id
     * @return Contact
     */
    public function getUserByExtId($id): ?Contact
    {
        foreach (InventoryMock::$users as $user) {
            if ($user->user_ext_id = $id) {
                return $user;
            }
        }
        return null;
    }

    /**
     * lookup user by email
     * @param $email
     * @return Contact
     */
    public function getUserByEmail($email): ?Contact
    {
        foreach (InventoryMock::$users as $user) {
            if ($user->email = $email) {
                return $user;
            }
        }
        return null;
    }

    /**
     * check user exists in inventory (check based on user_ext_id and email)
     * @param Contact $user
     * @return bool
     */
    public function userExists(Contact $user): bool
    {
        $key = array_search($user, InventoryMock::$users);
        if ($key) {
            return true;
        }
        return false;
    }

    /**
     * update the user (only provided user fields are updated, null fields are ignored)
     * @param Contact $user
     * @return mixed
     */
    public function updateUser(Contact $user)
    {
        // TODO: Implement updateUser() method.
    }

    public function deleteUser($id): bool
    {
        foreach (InventoryMock::$users as $user) {
            if ($user->user_ext_id = $id) {
                unset($user);
            }
        }
    }

    public function toolExists($toolId): bool
    {
        if (isset($toolId)) {
            $inventoryTool = $this->getToolById($toolId);
            if (isset($inventoryTool)) {
                return true;
            }
        }
        return false;
    }

    public function getToolByCode($code): ?Tool
    {
        // TODO: Implement getToolByCode() method.
    }

    /**
     * lookup all tools assigned to this user
     * @param $userId
     * @return mixed
     */
    public function getUserTools($userId)
    {
        // TODO: Implement getUserTools() method.
    }

    public function getToolsByState(string $state, $offset = 0, $limit = 1000)
    {
        // TODO: Implement getToolsByState() method.
    }

    public function getActivity($offset = 0, $limit = 1000)
    {
        // TODO: Implement getActivity() method.
    }

    public function getActivityCheckout($offset = 0, $limit = 1000)
    {
        // TODO: Implement getActivityCheckout() method.
    }

    public function getLendings($offset = 0, $limit = 1000)
    {
        // TODO: Implement getLendings() method.
    }
    public function updateUserState(Contact $user)
    {
        // TODO: Implement updateUser() method.
    }

    public function getAccessories($offset = 0, $limit = 1000)
    {
        // TODO: Implement getAccessories() method.
    }

    public function getAccessoryById($id): ?Accessory
    {
        // TODO: Implement getAccessoryById() method.
    }

    public function accessoryExists($accessoryId): bool
    {
        // TODO: Implement accessoryExists() method.
    }

    public function syncUser(Contact $user): bool
    {
        // TODO: Implement syncUser() method.
        return true;
    }

    public function getInventoryItems($toolType, $offset = 0, $limit=1000)
    {
        // TODO: Implement getInventoryItems() method
    }

}