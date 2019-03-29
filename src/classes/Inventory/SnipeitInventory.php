<?php

namespace Api\Inventory;

use Api\ModelMapper\ToolMapper;
use GuzzleHttp\RequestOptions;
use Api\Model\User;
use Api\Model\Tool;
use Illuminate\Database\Eloquent\Collection;

/**
 * Inventory implementation for Snipeit
 * @package Api\Inventory
 */
class SnipeitInventory implements Inventory
{
    const COMPANY_ID_KLUSBIB = 1;
    public static function instance($logger = null) {
        return new SnipeitInventory(new \GuzzleHttp\Client(['base_uri' => INVENTORY_URL . '/api/v1/']),
            INVENTORY_API_KEY, $logger);
    }

    private $client;
    private $apiKey;
    private $logger;

    /**
     * InventoryImpl constructor.
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

    public function getTools($offset = 0, $limit=1000)
    {
        $tools = new Collection();
        $assets = $this->get('hardware?offset=' . $offset . '&limit=' . $limit);

        foreach ($assets->rows as $asset) {
            $tool = ToolMapper::mapAssetToTool($asset);
            $tools->add($tool);
        }

        return $tools;
    }

    public function getToolById($id) : ?Tool
    {
        $asset = $this->get('hardware/' . $id);
        return ToolMapper::mapAssetToTool($asset);
    }

    /**
     * Lookup user in inventory based on external id
     * @param $id the user_ext_id aka snipeIt Person.id
     * @return User the user if found or null
     */
    public function getUserByExtId($id) : ?User
    {
        $inventoryUser = $this->get('users/' . $id);
        return SnipeitInventory::mapInventoryUserToApiUser($inventoryUser);
    }

    /**
     * Lookup user in inventory based on email
     * @param $email the user email aka snipeIt Person.username
     * @return User the user if found or null
     */
    public function getUserByEmail($email) : ?User
    {
        if (!isset($email)) {
            return null;
        }
        $response = $this->get('users/?search=' . $email . '&limit=5');
        if ($response->total == 0){
            return null;
        }
        if ($response->total > 1) {
            // loop over the results to retrieve the user with the given email
            $users = $response->rows;
            foreach ($users as $user) {
                if ($user->username == $email) {
                    return SnipeitInventory::mapInventoryUserToApiUser($user);
                }
            }
//            throw new RuntimeException("Database inconsistency: multiple users with same email found (email=" . $email . ")");
        } else {
            return SnipeitInventory::mapInventoryUserToApiUser($response->rows[0]);
        }
        throw new \RuntimeException("Unexpected reponse received: " . $response);
    }

    /**
     * lookup user in inventory based on user_ext_id and email
     *
     * @param User $user the user to lookup in inventory
     * @return bool true if the user exists
     */
    public function userExists(User $user) : bool
    {
        // lookup user by userExtId (snipeit id)
        if (isset($user->user_ext_id)) {
            $inventoryUser = $this->getUserByExtId($user->user_ext_id);
            if (isset($inventoryUser)) {
                return true;
            }
        }

        // lookup user by email (snipeit username)
        $inventoryUser = $this->getUserByEmail($user->email);
        if (isset($inventoryUser)) {
            return true;
        }
        return false;
    }

    public function updateUser(User $user) {
        $data = array();
        if (isset($user->firstname)) {
            $data['first_name'] = $user->firstname;
        }
        if (isset($user->lastname)) {
            $data['last_name'] = $user->lastname;
        }
        if (isset($user->email)) {
            $data['username'] = $user->email;
        }
        if (isset($user->user_id)) {
            $data['employee_num'] = $user->user_id;
        }
        return $this->patch('users/' . $user->user_ext_id, $data);
    }

    public function deleteUser($id) : bool {
        $response = $this->delete('users/' . $id);
        if ($response->status == "success") {
            return true;
        }
        return false;
    }

    public function postUser(User $user) {
        $password = "dummy12345"; // FIXME: replace by random?
        $data = ['first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'username' => $user->email,
            'password' => $password, // user not allowed to login
            'employee_num' => $user->user_id,
            'company_id' => SnipeitInventory::COMPANY_ID_KLUSBIB];
        return SnipeitInventory::mapInventoryUserToApiUser($this->post('users', $data));
    }

    private function get($target) {
        return $this->request('GET', $target);
    }

    private function post($target, $data)
    {
        $response = $this->request('POST', $target, $data);
        if ($response->status == "success") {
            return $response->payload;
        } else {
            $ex = new \RuntimeException('Inventory request to create user failed: status="' . $response->status
                . '"; messages=' . json_encode($response->messages));
            throw $ex;
        }
    }

    private function put($target, $data)
    {
        return $this->request('PUT', $target, $data);
    }
    private function patch($target, $data)
    {
        return $this->request('PATCH', $target, $data);
    }
    private function delete($target) {
        return $this->request('DELETE', $target);
    }
    private function request($method, $target, $data = null) {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$this->apiKey,
            ],
        ];
        if (isset($data)) {
            $options[RequestOptions::JSON] = $data;
        }
        $this->logger->info("Inventory request: $method; $target; " . json_encode($data));
        $res = $this->client->request($method, $target, $options);
//        [
//            'json' => ['foo' => 'bar']
//        ]);
        if ($res->getStatusCode() >= 400){
            throw new \RuntimeException('Inventory request to "' . $target . '" failed with status code ' . $res->getStatusCode());
        }
        $contentType = $res->getHeader('content-type')[0];
        $this->logger->debug($res->getBody());
        if (strpos($contentType, 'application/json') !== false ) {
            return \GuzzleHttp\json_decode($res->getBody());
        }
        return $res->getBody();
    }

    /**
     * Converts a user as known in inventory to a local user
     * @param $inventoryUser original inventory user
     * @return User converted user
     */
    static public function mapInventoryUserToApiUser($inventoryUser) : User {
        $user = new User();
        $user->user_ext_id = $inventoryUser->id;
        $user->firstname = $inventoryUser->first_name;
        $user->lastname = $inventoryUser->last_name;
        $user->email = $inventoryUser->username;
        $user->user_id = $inventoryUser->employee_num;
        return $user;
    }
}