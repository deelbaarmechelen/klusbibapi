<?php

namespace Tests\Mock;

use Api\Inventory\Inventory;
use Api\Model\Tool;
use Api\Model\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class InventoryMock implements Inventory
{

    private $client;
    private $apiKey;
    private $logger;

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
        return Capsule::table('tools')->get();
    }

    public function getToolById($id) : ?Tool
    {
        $tool = \Api\Model\Tool::find($id);
        return $tool;
    }

    public function postUser(User $user)
    {
        $newuser = new User();
        $newuser->user_ext_id = 1;
        return $newuser;
    }

    /**
     * lookup user by user_ext_id
     * @param $id
     * @return User
     */
    public function getUserByExtId($id): ?User
    {
        // TODO: Implement getUserByExtId() method.
    }

    /**
     * lookup user by email
     * @param $email
     * @return User
     */
    public function getUserByEmail($email): ?User
    {
        // TODO: Implement getUserByEmail() method.
    }

    /**
     * check user exists in inventory (check based on user_ext_id and email)
     * @param User $user
     * @return bool
     */
    public function userExists(User $user): bool
    {
        // TODO: Implement userExists() method.
    }

    /**
     * update the user (only provided user fields are updated, null fields are ignored)
     * @param User $user
     * @return mixed
     */
    public function updateUser(User $user)
    {
        // TODO: Implement updateUser() method.
    }

    public function deleteUser($id): bool
    {
        // TODO: Implement deleteUser() method.
    }

    public function getToolByCode($code): ?Tool
    {
        // TODO: Implement getToolByCode() method.
    }
}