<?php

namespace Api\User;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Mail\MailManager;
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
        return new UserManager(SnipeitInventory::instance(), $logger, new MailManager(null, null, $logger));
    }
    private $inventory;
    private $logger;
    private $mailManager;
    private $lastSyncAttempt;
    private $lastSyncedUsers;

    /**
     * UserManager constructor.
     */
    public function __construct(Inventory $inventory, $logger, MailManager $mailManager = null)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
        $this->mailManager = $mailManager;
        $this->lastSyncAttempt = null;
        $this->lastSyncedUsers = array();
    }

    // FIXME: catch Inventory exceptions to make it non blocking and avoid exposing internals to caller
    /**
     * retrieve the local user and check synchronisation with inventory
     * @param $id
     * @param $sync when true, force sync of user with inventory
     * @return mixed
     */
    function getById($id, $sync = false) {
        $this->logger->debug("UserManager.getById: Get user by ID $id");

        try {
            $user = User::find($id);
            if ($user == null) {
                return null;
            }
            // TODO: use last_sync_date to check if sync is needed
            //       if last_sync_date < updated_at -> sync update (PUT request)
            //       if update_at is null && last_sync_date is null -> sync create (POST request)
            //       if status is deleted && last_sync_date < updated_at -> sync delete (DELETE request)
            if ($sync || $this->syncRequired($user)) {
                $this->logger->debug("UserManager.getById: Found user with ID $id and sync with inventory: " . json_encode($user));
                if (isset($user->user_ext_id)) {
                    $inventoryUser = $this->getByIdFromInventory($user->user_ext_id);
                    if ($inventoryUser == null) {
                        $this->addToInventory($user);
                    } elseif (!$this->inventoryUpToDate($user, $inventoryUser)) {
                        $this->logger->info("inventory not up to date for user " . \json_encode($user) .
                            " - inventory user: " . \json_encode($inventoryUser));
                        $result = $this->updateInventory($user);
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
     * Sync is skipped if none of the inventory fields need to be modified
     *
     * @param $user the user to be updated
     * @param $firstnameUpdated true if firstname has been modified
     * @param $lastnameUpdate true if lastname has been modified
     * @param $emailUpdated true if email has been modified
     * @param $stateUpdated true if user state has been modified
     */
    function update(User $user, $firstnameUpdated = true, $lastnameUpdate = true, $emailUpdated = true, $stateUpdated = true) {
        $this->logger->debug("Update user: " . json_encode($user));
        if ($firstnameUpdated || $lastnameUpdate || $emailUpdated || $stateUpdated) {
            $this->updateInventory($user);
        }
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
        try {
            if (!$this->inventory->userExists($user)) {
                $result = $this->syncUser($user);
                if (isset($result) && $result == true) {
                    $user->last_sync_date = new \DateTime();
                    $user->save();
                }
//            $inventoryUser = $this->inventory->postUser($user);
//            if (is_null($inventoryUser) || !($inventoryUser instanceof User)) {
//                throw new \RuntimeException("Error creating inventory user (response=" . $inventoryUser . ")");
//            }
            } else {
                // lookup user_ext_id
                $inventoryUser = $this->inventory->getUserByEmail($user->email);
                $user->user_ext_id = $inventoryUser->user_ext_id;
                $user->save();
                $this->updateInventory($user); // update user_ext_id
            }

            $this->logger->debug("User added to inventory: " . json_encode($user));
//        $user->user_ext_id = $inventoryUser->user_ext_id;
//        $user->save();
        } catch (\Exception $ex) {
            $this->logger->error('Error adding user ' . $user->user_id . ' to inventory: ' . $ex->getMessage());
            $context = "UserManager::addToInventory; user=" . \json_encode($user);
            $this->mailManager->sendErrorNotif($ex->getMessage(), $context);
            return false;
        }
    }

    /**
     * Update inventory based on $user
     * Make sure $user->user_ext_id is correctly set
     * @param $user
     * @return true if inventory update was successful
     */
    protected function updateInventory($user) : bool {
        $this->logger->debug("Inventory update requested for user " . json_encode($user));
        try {
            $result = $this->syncUser($user);
            if (isset($result) && $result == true) {
                $user->last_sync_date = new \DateTime();
                $user->save();
            }
            return $result;
        } catch (\Exception $ex) {
            $this->logger->error('Error updating user ' . $user->user_id . ' to inventory: ' . $ex->getMessage());
            $context = "UserManager::updateInventory; user=" . \json_encode($user);
            $this->mailManager->sendErrorNotif($ex->getMessage(), $context);
            return false;
        }
    }
    protected function getByIdFromInventory($id) {
        return $this->inventory->getUserByExtId($id);
    }

    /**
     * @param $user
     * @return bool
     */
    protected function syncUser(User $user): bool
    {
        $this->lastSyncAttempt = new \DateTime('now');
        array_push($this->lastSyncedUsers, $user->user_id);
        $result = $this->inventory->syncUser($user);
        return $result;
    }

    /**
     * Check if sync with inventory is needed
     * @param $user a local user
     * @return bool true if user needs to be synced with inventory
     */
    private function syncRequired(User $user): bool
    {
        return $this->syncAllowed($user)
          && ($user->last_sync_date == null                 // user has not been synced with inventory yet
             || $user->last_sync_date < $user->updated_at); // user has been updated since last sync
    }
    
    /**
     * Check if sync with inventory is allowed
     * Prevents reattempting sync in quick loops by forcing a wait time of 5 minutes between attempts for a given user
     * @param $user a local user
     * @return bool true if sync is allowed for user
     */
    private function syncAllowed(User $user): bool
    {
        if ($this->lastSyncAttempt != null) {
            $now = new \DateTime();
            $nextAllowedAttempt = clone $this->lastSyncAttempt;
            $nextAllowedAttempt->add(new \DateInterval('P5I')); // add 5 minutes
            if ($nextAllowedAttempt < $now) {
                $this->lastSyncedUsers = array();
            }
        }
        return $this->lastSyncAttempt == null || !in_array($user->user_id, $this->lastSyncUsers);
    }

}