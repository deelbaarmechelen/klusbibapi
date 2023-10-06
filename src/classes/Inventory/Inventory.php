<?php
namespace Api\Inventory;

use Api\Model\Accessory;
use Api\Model\ToolState;
use Api\Model\Contact;
use Api\Model\Tool;

/**
 * Defines methods to lookup and synchronize tools and user in the inventory
 *
 * @package Api\Inventory
 */
interface Inventory {
    public function getTools($offset = 0, $limit=1000);
    public function getToolsByState(string $state, $offset = 0, $limit=1000); // available, in use, maintenance, ...
    public function getToolByCode($code) : ?Tool;
    public function getToolById($id) : ?Tool;
    public function getAccessories($offset = 0, $limit=1000);
    public function getAccessoryById($id) : ?Accessory;
    public function getInventoryItems($toolType, $offset = 0, $limit=1000);
    /**
     * check tool exists in inventory
     * @param $toolId
     * @return bool true if the user exists in inventory
     */
    public function toolExists($toolId) : bool;
    public function accessoryExists($accessoryId) : bool;

    public function syncUser(Contact $user) : bool;
    public function postUser(Contact $user);

    /**
     * lookup user by user_ext_id
     * @param $id
     * @return Contact the user or null if not found
     */
    public function getUserByExtId($id) : ?Contact;

    /**
     * lookup user by email
     * @param $email
     * @return Contact the user or null if not found
     */
    public function getUserByEmail($email) : ?Contact;

    /**
     * lookup all tools assigned to this user
     * @param $userExtId external user id (id as known in external inventory system)
     * @return mixed
     */
    public function getUserTools($userExtId);

    /**
     * check user exists in inventory (check based on user_ext_id and email)
     * @param Contact $user
     * @return bool true if the user exists in inventory
     */
    public function userExists(Contact $user) : bool;

    /**
     * update the user (only provided user fields are updated, null fields are ignored)
     * @param Contact $user
     * @return mixed
     * @deprecated Use syncUser instead
     */
    public function updateUser(Contact $user);

    /**
     * @param Contact $user
     * @return mixed
     * @deprecated Use syncUser instead
     */
    public function updateUserState(Contact $user);
    public function deleteUser($id) : bool;

    public function getActivity($offset = 0, $limit=1000);
    public function getActivityCheckout($offset = 0, $limit=1000);
    public function getActivityCheckin($offset = 0, $limit=1000);
    public function getActivityUpdate($offset = 0, $limit=1000);
//    public function getLendings($offset = 0, $limit=1000);

    }