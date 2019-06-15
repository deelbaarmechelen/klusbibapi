<?php

namespace Api\Inventory;

use Api\Exception\InventoryException;
use Api\Model\ToolState;
use Api\Inventory\SnipeitToolMapper;
use Api\Tool\NotFoundException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
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
        $assets = $this->get('hardware?offset=' . $offset . '&limit=' . $limit . '&company_id=' . SnipeitInventory::COMPANY_ID_KLUSBIB);

        foreach ($assets->rows as $asset) {
            $tool = SnipeitToolMapper::mapAssetToTool($asset);
            $tools->add($tool);
        }

        return $tools;
    }


    public function getToolsByState(string $state, $offset = 0, $limit = 1000)
    {
        $tools = new Collection();
        $assetState = SnipeitToolMapper::mapToolStateToAssetState($state);
        $assets = $this->get('hardware?offset=' . $offset . 'status_id=' . $assetState->id
            . '&limit=' . $limit . '&company_id=' . SnipeitInventory::COMPANY_ID_KLUSBIB);

        foreach ($assets->rows as $asset) {
            $tool = SnipeitToolMapper::mapAssetToTool($asset);
            $tools->add($tool);
        }

        return $tools;
    }

    public function getToolByCode($code) : ?Tool
    {
        $assets = $this->get('hardware/?search=' . $code . '&limit=5&company_id=' . SnipeitInventory::COMPANY_ID_KLUSBIB);
        if ($assets->total == 1) {
            $asset = $assets->rows[0];
            return SnipeitToolMapper::mapAssetToTool($asset);
        } else if ($assets->total == 0) {
          throw new \Api\Exception\NotFoundException();
        }
        $this->logger->error("Multiple tools found in inventory with same code (asset tag): $code");
        return null; // multiple values found...
    }

    public function getToolById($id) : ?Tool
    {
        $asset = $this->get('hardware/' . $id);
        return SnipeitToolMapper::mapAssetToTool($asset);
    }

    /**
     * Lookup user in inventory based on external id
     * @param $id the user_ext_id aka snipeIt Person.id
     * @return User the user if found or null
     */
    public function getUserByExtId($id) : ?User
    {
        try {
            $inventoryUser = $this->get('users/' . $id);
            SnipeitUserMapper::mapInventoryUserToApiUser($inventoryUser);
        } catch (InventoryException $ex) {
            // no user or invalid user
            $this->logger->error($ex->getMessage());
            if (InventoryException::USER_NOT_FOUND == $ex->getCode()
            || InventoryException::INVALID_USER == $ex->getCode()) {
                return null;
            }
            throw $ex; // other errors: rethrow
        }
        return SnipeitUserMapper::mapInventoryUserToApiUser($inventoryUser);
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
        $response = $this->get('users?search=' . $email . '&limit=5');
        if ($response->total == 0){
            return null;
        }
        if ($response->total > 1) {
            // loop over the results to retrieve the user with the given email
            $users = $response->rows;
            foreach ($users as $user) {
                if ($user->username == $email) {
                    return SnipeitUserMapper::mapInventoryUserToApiUser($user);
                }
            }
//            throw new RuntimeException("Database inconsistency: multiple users with same email found (email=" . $email . ")");
        } else {
            return SnipeitUserMapper::mapInventoryUserToApiUser($response->rows[0]);
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

    /**
     * lookup all tools assigned to this user
     * @param $userId
     * @param int $offset paging offset
     * @param int $limit max number of tools to return
     * @return mixed
     */
    public function getUserTools($userExtId)
    {
        if (!isset($userExtId)) {
            return null;
        }
        $response = $this->get('users/' . $userExtId . '/assets');
        if ($response->total == 0){
            return null;
        }
        $tools = array();
        foreach ($response->rows as $row) {
            array_push($tools, SnipeitToolMapper::mapAssetToTool($row));

        }
        return $tools;
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
            'password_confirmation' => $password,
            'employee_num' => $user->user_id,
            'company_id' => SnipeitInventory::COMPANY_ID_KLUSBIB];
        return SnipeitUserMapper::mapInventoryUserToApiUser($this->post('users', $data));
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
            $this->logger->error('HTTP response ok, but declined by inventory. Do you have a syntax error? '
                . '(status=' . $response->status . ';messages=' . json_encode($response->messages) . ')');
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
        try {
            $time_start = microtime(true);

            $res = $this->client->request($method, $target, $options);

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            $this->logger->info("Inventory request duration: $execution_time secs");

        } catch (ClientException $clientException) {
            if ($clientException->hasResponse()) {
                $response = $clientException->getResponse();
                $statusCode = $response->getStatusCode();
            }
            if (isset($statusCode) && ($statusCode == 404 || $statusCode == 403)) {
                // access forbidden is considered as not found (can be an asset or user from another company)
                throw new \Api\Exception\NotFoundException();
            }
            else if (isset($statusCode) && ($statusCode >= 500)) {
                throw new \Api\Exception\InventoryException("Unable to access inventory", null, $clientException);
            }

        } catch (ServerException $serverException) {
            throw new \Api\Exception\InventoryException("Inventory unavailable", null, $serverException);
        }

        if ($res->getStatusCode() >= 400){
            if ($res->getStatusCode() == 404) {
                throw new \Api\Exception\NotFoundException();
            }
            $this->logger->error('Inventory request to "' . $target . '" failed with status code ' . $res->getStatusCode());
            throw new \RuntimeException('Inventory request to "' . $target . '" failed with status code ' . $res->getStatusCode());
        }
        $contentType = $res->getHeader('content-type')[0];
        $this->logger->debug("Response body message=" . $res->getBody());
        if (strpos($contentType, 'application/json') !== false ) {
            return \GuzzleHttp\json_decode($res->getBody());
        }
        return $res->getBody();
    }
}