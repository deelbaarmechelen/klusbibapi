<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/06/19
 * Time: 10:28
 */

namespace Api\Inventory;


use Api\Exception\InventoryException;
use Api\Model\User;

abstract class SnipeitUserMapper
{
    /**
     * Converts a user as known in inventory to a local user
     * @param $inventoryUser original inventory user
     * @return User converted user
     */
    static public function mapInventoryUserToApiUser($inventoryUser) : User {
        if (!isset($inventoryUser->id) ||
            !isset($inventoryUser->username) ) {
            throw new InventoryException("Invalid user, id and/or username not set!", InventoryException::INVALID_USER);
        }
        $user = new User();
        $user->user_ext_id = $inventoryUser->id;
        $user->email = $inventoryUser->username;
        $user->firstname = (isset($inventoryUser->first_name) ? $inventoryUser->first_name : "");
        $user->lastname = (isset($inventoryUser->last_name) ? $inventoryUser->last_name  : "");
        $user->user_id = (isset($inventoryUser->employee_num) ? $inventoryUser->employee_num : "");
        return $user;
    }
}