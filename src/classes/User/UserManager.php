<?php

namespace Api\User;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Model\UserState;
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

    // FIXME: catch Inventory exceptions to make it non blocking and avoid exposing internals to caller
    /**
     * retrieve the local user and check synchronisation with inventory
     * @param $id
     * @param $sync when true, sync user with inventory
     * @return mixed
     */
    function getById($id, $sync = true) {
        $this->logger->debug("UserManager.getById: Get user by ID $id");

        try {
            $user = User::find($id);
            if ($user == null) {
                return null;
            }
            if ($sync) {
                $this->logger->debug("UserManager.getById: Found user with ID $id and sync with inventory: " . json_encode($user));
                if (isset($user->user_ext_id)) {
                    $inventoryUser = $this->getByIdFromInventory($user->user_ext_id);
                    if ($inventoryUser == null) {
                        $this->addToInventory($user);
                    } elseif (!$this->inventoryUpToDate($user, $inventoryUser)) {
                        $this->updateInventory($user);
                    }
                } else {
                    $this->addToInventory($user);
                }
            }
        } catch (\Exception $ex) {
            $this->logger->error("UserManager.getById: Problem while syncing user with id $id: " . $ex->getMessage());
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
        $this->logger->debug("Update user: " . json_encode($user));
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
        if ( !(   ($user->state == UserState::ACTIVE && $inventoryUser->state == UserState::ACTIVE)
               || ($user->state != UserState::ACTIVE && $inventoryUser->state == "INACTIVE") )
           ) {
            return false;
        }
        return true;
    }
    protected function addToInventory($user) {
        $this->logger->debug("Add user to inventory: " . json_encode($user));
        $inventoryUser = null;
        if (!$this->inventory->userExists($user)) {
            $inventoryUser = $this->inventory->postUser($user);
            if (is_null($inventoryUser) || !($inventoryUser instanceof User)) {
                throw new \RuntimeException("Error creating inventory user (response=" . $inventoryUser . ")");
            }
        } else {
            // lookup user_ext_id
            $inventoryUser = $this->inventory->getUserByEmail($user->email);
            $user->user_ext_id = $inventoryUser->user_ext_id;
            $this->updateInventory($user); // update user_ext_id
        }

        $this->logger->debug("User added to inventory: " . json_encode($user));
        $user->user_ext_id = $inventoryUser->user_ext_id;
        $user->save();
    }

    /**
     * Update inventory based on $user
     * Make sure $user->user_ext_id is correctly set
     * @param $user
     */
    protected function updateInventory($user) {
        $this->logger->debug("Inventory update requested for user " . json_encode($user));
        $this->inventory->updateUser($user);
        $this->inventory->updateUserState($user);
    }
    protected function getByIdFromInventory($id) {
        return $this->inventory->getUserByExtId($id);
    }
}