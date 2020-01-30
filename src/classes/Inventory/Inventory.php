<?php
namespace Api\Inventory;

use Api\Model\ToolState;
use Api\Model\User;
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

    /**
     * check tool exists in inventory
     * @param $toolId
     * @return bool true if the user exists in inventory
     */
    public function toolExists($toolId) : bool;

    public function postUser(User $user);

    /**
     * lookup user by user_ext_id
     * @param $id
     * @return User the user or null if not found
     */
    public function getUserByExtId($id) : ?User;

    /**
     * lookup user by email
     * @param $email
     * @return User the user or null if not found
     */
    public function getUserByEmail($email) : ?User;

    /**
     * lookup all tools assigned to this user
     * @param $userExtId external user id (id as known in external inventory system)
     * @return mixed
     */
    public function getUserTools($userExtId);

    /**
     * check user exists in inventory (check based on user_ext_id and email)
     * @param User $user
     * @return bool true if the user exists in inventory
     */
    public function userExists(User $user) : bool;

    /**
     * update the user (only provided user fields are updated, null fields are ignored)
     * @param User $user
     * @return mixed
     */
    public function updateUser(User $user);
    public function updateUserState(User $user);
    public function deleteUser($id) : bool;

//    public function getActivity($offset = 0, $limit=1000);
//    public function getActivityCheckout($offset = 0, $limit=1000);
//    public function getLendings($offset = 0, $limit=1000);

    }