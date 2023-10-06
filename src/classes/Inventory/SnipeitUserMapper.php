<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/06/19
 * Time: 10:28
 */

namespace Api\Inventory;


use Api\Exception\InventoryException;
use Api\Model\Contact;
use Api\Model\UserState;

abstract class SnipeitUserMapper
{
    /**
     * Converts a user as known in inventory to a local user
     * @param $inventoryUser original inventory user
     * @return Contact converted user
     */
    static public function mapInventoryUserToApiUser($inventoryUser) : Contact {
        if (!isset($inventoryUser->id) ||
            !isset($inventoryUser->username) ) {
            throw new InventoryException("Invalid user, id and/or username not set!", InventoryException::INVALID_USER);
        }
        $user = new Contact();
        $user->user_ext_id = $inventoryUser->id;
        $user->email = $inventoryUser->username;
        $user->first_name = (isset($inventoryUser->first_name) ? $inventoryUser->first_name : "");
        $user->last_name = (isset($inventoryUser->last_name) ? $inventoryUser->last_name  : "");
        $user->id = (isset($inventoryUser->employee_num) ? $inventoryUser->employee_num : "");
        // Snipe IT has no inventory but we use avatar to reflect ok/nok state
        if ($inventoryUser->avatar == "\/uploads\/avatars\/DBM_avatar_ok.png") {
            $user->state = UserState::ACTIVE;
        }
        if ($inventoryUser->avatar == "\/uploads\/avatars\/DBM_avatar_nok.png") {
            $user->state = "INACTIVE"; // fake state regrouping all non ACTIVE states
        }
        return $user;
    }
}