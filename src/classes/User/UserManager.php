<?php

namespace Api\User;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;
use Api\Model\User;
use Api\ModelMapper\UserMapper;

/**
 * Class UserManager
 * Keeps User model in sync with inventory
 * @package Api\User
 */
class UserManager
{
    public static function instance($logger) {
        return new UserManager(SnipeitInventory::instance(), $logger);
    }
    private $inventory;
    private $logger;
    /**
     * UserManager constructor.
     */
    public function __construct(Inventory $inventory, $logger)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
    }

    /**
     * retrieve the local user and check synchronisation with inventory
     * @param $id
     * @return mixed
     */
    function getById($id) {
        $user = User::find($id);
        if (isset($user->user_ext_id)) {
            $inventoryUser = $this->getByIdFromInventory($user->user_ext_id);
            if (!$this->inventoryUpToDate($user, $inventoryUser)) {
                $this->updateInventory($user);
            }
        } else {
            $inventoryUser = $this->inventory->postUser($user);
            $user->user_ext_id = $inventoryUser->user_ext_id;
            $user->save();
        }
        return $user;
    }

    /**
     * Creates a local user and sync to inventory
     * @param $user the user to be created
     */
    function create(User $user) {
        $this->addToInventory($user);
        $user->save();
    }

    /**
     * Updates a local user and sync to inventory
     * @param $user the user to be updated
     */
    function update(User $user) {
        $this->updateInventory($user);
        $user->save();
    }
    /**
     * Deletes a local user and sync to inventory
     * @param $user the user to be deleted
     */
    function delete(User $user) {
        if (isset($user->user_ext_id)) {
            $this->inventory->deleteUser($user->user_ext_id);
        }
        $user->delete();
    }
    /**
     * Returns true if inventory user data matches local user data
     * Relevant data to be checked: firstname, lastname, email, user_ext_id
     * @param $user
     * @param $inventoryUser
     * @return bool
     */
    protected function inventoryUpToDate($user, $inventoryUser) {
        $this->logger->debug("Comparing users (model<->inventory):" . json_encode($user) . " - " . json_encode($inventoryUser));
        if ($user->firstname != $inventoryUser->firstname
        || $user->lastname != $inventoryUser->lastname
        || $user->email != $inventoryUser->email
        || $user->user_ext_id != $inventoryUser->user_ext_id) {
            return false;
        }
        return true;
    }
    protected function addToInventory($user) {
        $inventoryUser = null;
        if (!$this->inventory->userExists($user)) {
            $inventoryUser = $this->inventory->postUser($user);
            if (is_null($inventoryUser) || !($inventoryUser instanceof User)) {
                throw new \RuntimeException("Error creating inventory user (response=" . $inventoryUser . ")");
            }
        } else {
            // lookup user_ext_id
            $userInventory = $this->inventory->getUserByEmail($user->email);
            $user->user_ext_id = $userInventory->id;
            $this->inventory->updateUser($user);
        }
        $user->user_ext_id = $inventoryUser->id;
    }
    protected function updateInventory($user) {
        $this->inventory->updateUser($user);
    }
    protected function getByIdFromInventory($id) {
        return $this->inventory->getUserByExtId($id);
    }
}