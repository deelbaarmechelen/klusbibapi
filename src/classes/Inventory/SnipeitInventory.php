<?php

namespace Api\Inventory;

use Api\Exception\InventoryException;
use Api\Model\Accessory;
use Api\Model\ToolState;
use Api\Inventory\SnipeitToolMapper;
use Api\Model\ToolType;
use Api\Model\UserState;
use Api\Tool\NotFoundException;
//use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Api\Model\Contact;
use Api\Model\Tool;
use Illuminate\Database\Eloquent\Collection;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Inventory implementation for Snipeit
 * @package Api\Inventory
 */
class SnipeitInventory implements Inventory
{
    const COMPANY_ID_KLUSBIB = 1;
    public static function instance($logger = null) {
        // Create default HandlerStack
        $stack = HandlerStack::create();
        $strategy = new Strategy\Delegate\DelegatingCacheStrategy($defaultStrategy = new Strategy\NullCacheStrategy());
        $strategy->registerRequestMatcher(new InventoryAssetsRequestMatcher(),
            new Strategy\GreedyCacheStrategy(
                new Psr6CacheStorage(
                    new FilesystemAdapter('', 1800,'/tmp/snipeit-cache')
                ),
//                new DoctrineCacheStorage(
//                    new FilesystemCache('/tmp/snipeit-cache')
//                ),
                1800 // the TTL in seconds -> 30min
            )
        );

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware($strategy), 'cache');

        return new SnipeitInventory(new \GuzzleHttp\Client([
              'base_uri' => INVENTORY_URL . '/api/v1/',
              'handler' => $stack
            ]),INVENTORY_API_KEY, $logger);
    }

    private $client;
    private $apiKey;
    private $logger;
    private $sslCertificateVerification;

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
        $this->sslCertificateVerification = SSL_CERTIFICATE_VERIFICATION;
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
        if (isset($asset) && isset($asset->status) && $asset->status == "error") {
            $errmsg = isset($asset->messages) ? $asset->messages : \json_encode($asset);
            $this->logger->error("Error getting tool with id $id from inventory: $errmsg");
            return null;
        }
        return SnipeitToolMapper::mapAssetToTool($asset);
    }

    public function toolExists($toolId) : bool {
        if (isset($this->logger)) {
            $this->logger->debug('check tool exists in inventory');
        }
        if (isset($toolId)) {
            $inventoryTool = $this->getToolById($toolId);
            if (isset($inventoryTool)) {
                return true;
            }
        }
        return false;
    }

    public function getAccessories($offset = 0, $limit=1000)
    {
        $accessories = new Collection();
        $snipeAccessories = $this->get('accessories?offset=' . $offset . '&limit=' . $limit . '&company_id=' . SnipeitInventory::COMPANY_ID_KLUSBIB);

        foreach ($snipeAccessories->rows as $snipeAccessory) {
            $accessory = SnipeitToolMapper::mapSnipeAccessoryToAccessory($snipeAccessory);
            $accessories->add($accessory);
        }

        return $accessories;
    }

    public function getAccessoryById($id) : ?Accessory
    {
        $accessory = $this->get('accessories/' . $id);
        return SnipeitToolMapper::mapSnipeAccessoryToAccessory($accessory);
    }
    public function accessoryExists($accessoryId) : bool {
        if (isset($this->logger)) {
            $this->logger->debug('check accessory exists in inventory');
        }
        if (isset($accessoryId)) {
            $inventoryAccessory = $this->getAccessoryById($accessoryId);
            if (isset($inventoryAccessory)) {
                return true;
            }
        }
        return false;
    }

    public function getInventoryItems($toolType, $offset = 0, $limit=1000) {
        $items = new Collection();
        if ($toolType == ToolType::TOOL) {
            $assets = $this->get('hardware?offset=' . $offset . '&limit=' . $limit . '&company_id=' . SnipeitInventory::COMPANY_ID_KLUSBIB);
            foreach ($assets->rows as $asset) {
                $item = SnipeitToolMapper::mapAssetToItem($asset);
                $items->add($item);
            }

        } else if ($toolType == ToolType::ACCESSORY) {
            $snipeAccessories = $this->get('accessories?offset=' . $offset . '&limit=' . $limit . '&company_id=' . SnipeitInventory::COMPANY_ID_KLUSBIB);

            foreach ($snipeAccessories->rows as $snipeAccessory) {
                $item = SnipeitToolMapper::mapAccessoryToItem($snipeAccessory);
                $items->add($item);
            }
        } else {
            throw new \Exception("Invalid tool type: " . $toolType);
        }

        return $items;
    }

    /**
     * Lookup all users in inventory
     * @return Collection Collection of users
     */
    public function getUsers()
    {
        $users = new Collection();
        $inventoryUsers = $this->get('users');

        foreach ($inventoryUsers->rows as $inventoryUser) {
            $user = SnipeitUserMapper::mapInventoryUserToApiUser($inventoryUser);
            $users->add($user);
        }
        return $users;
    }
    
    /**
     * Lookup user in inventory based on external id
     * @param $id the user_ext_id aka snipeIt Person.id
     * @return Contact the user if found or null
     */
    public function getUserByExtId($id) : ?Contact
    {
        try {
            $inventoryUser = $this->get('users/' . $id);
            return SnipeitUserMapper::mapInventoryUserToApiUser($inventoryUser);
        } catch (InventoryException $ex) {
            // no user or invalid user
            $this->logger->error($ex->getMessage());
            if (InventoryException::USER_NOT_FOUND == $ex->getCode()
            || InventoryException::INVALID_USER == $ex->getCode()) {
                return null;
            }
            throw $ex; // other errors: rethrow
        }
        //return SnipeitUserMapper::mapInventoryUserToApiUser($inventoryUser);
    }

    /**
     * Lookup user in inventory based on email
     * @param $email the user email aka snipeIt Person.username
     * @return Contact the user if found or null
     */
    public function getUserByEmail($email) : ?Contact
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
     * @param Contact $user the user to lookup in inventory
     * @return bool true if the user exists
     */
    public function userExists(Contact $user) : bool
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
            return array();
        }
        $tools = array();
        foreach ($response->rows as $row) {
            array_push($tools, SnipeitToolMapper::mapAssetToTool($row));

        }
        // TODO: also get accessories!
        return $tools;
    }

    public function syncUser(Contact $user) : bool {
        $this->logger->info("syncing user $user->id with inventory");
        $data = array();
        if (isset($user->first_name)) {
            $data['first_name'] = $user->first_name;
        }
        if (isset($user->last_name)) {
            $data['last_name'] = $user->last_name;
        }
        if (isset($user->email)) {
            $data['username'] = $user->email;
        }
        if (isset($user->id)) {
            $data['employee_num'] = $user->id;
        }
        if (isset($user->state)) {
            $data['state'] = $user->state;
        }
        // TODO: add notification in case of error for manual recovery?
        if ($user->state == UserState::DELETED || $user->state == UserState::DISABLED) {
            if ( isset($user->user_ext_id)) {
                $response = $this->delete('klusbib/sync/users/' . $user->user_ext_id);
                if (isset($response) && isset($response->status) && strcasecmp($response->status, "success") == 0 ) {
                    return true;
                }
                $message = "sync of user $user->id failed (delete)";
                if (isset($response)) {
                    $message .= ": response " . \json_encode($response);
                }
                throw new InventoryException($message);
            }
            return true; // user does not exist yet on inventory, nothing to remove
        } else {
            $inventoryUsersExists = false;
            if (isset($user->user_ext_id)) {
                $inventoryUser = $this->getUserByExtId($user->user_ext_id);
                if (isset($inventoryUser)) {
                    $inventoryUsersExists = true;
                }
            }
            if ($inventoryUsersExists) {
                // update existing inventory user
                $response = $this->put('klusbib/sync/users/' . $user->user_ext_id, $data);
                if (isset($response) && isset($response->status) && strcasecmp($response->status, "success") == 0 ) {
                    return true;
                }
                $message = "sync of user $user->id failed (put)";
                if (isset($response)) {
                    $message .= ": response " . \json_encode($response);
                }
                throw new InventoryException($message);
            } else {
                // newly created user or inexistent in inventory
                $inventoryUser = $this->post('klusbib/sync/users', $data);
                if (isset($inventoryUser) && isset($inventoryUser->id)) {
                    $user->user_ext_id = $inventoryUser->id;
                    $user->save();
                    return true;
                }
                $message = "sync of user $user->id failed (post)";
                if (isset($inventoryUser)) {
                    $message .= ": response " . \json_encode($inventoryUser);
                }
                throw new InventoryException($message);
            }
        }
    }

    /**
     * @param Contact $user
     * @return mixed
     * @deprecated Use syncUser instead
     */
    public function updateUser(Contact $user) {
        $data = array();
        if (isset($user->first_name)) {
            $data['first_name'] = $user->first_name;
        }
        if (isset($user->last_name)) {
            $data['last_name'] = $user->last_name;
        }
        if (isset($user->email)) {
            $data['username'] = $user->email;
        }
        if (isset($user->id)) {
            $data['employee_num'] = $user->id;
        }
        return $this->patch('users/' . $user->user_ext_id, $data);
    }

    /**
     * @param Contact $user
     * @return mixed|null
     * @deprecated Use syncUser instead
     */
    public function updateUserState(Contact $user)
    {
        return $this->updateUserAvatar($user);
    }
    private function updateUserAvatar(Contact $user) {
        $this->logger->info("Updating user avatar: $user->id / $user->user_ext_id / $user->state; " . json_encode($user));
        $data = array();
        $data['status'] = $user->state;
        if (!isset($user->user_ext_id)) {
            $this->logger->error("Unable to update avatar: no user ext id for user with id " . $user->id);
            return null;
        }
        return $this->put('klusbib/users/'. $user->user_ext_id . '/avatar', $data);
    }

    /**
     * @param $extUserId external (inventory) user id
     */
    public function deleteUser($extUserId) : bool {
        $response = $this->delete('users/' . $extUserId);
        if ($response->status == "success") {
            return true;
        }
        $this->logger->error("Unable to delete user " . $extUserId . ": " . $response->messages);
        return false;
    }

    public function postUser(Contact $user) {
        $password = "dummy12345"; // FIXME: replace by random?
        $data = ['first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->email,
            'password' => $password, // user not allowed to login
            'password_confirmation' => $password,
            'employee_num' => $user->id,
            'company_id' => SnipeitInventory::COMPANY_ID_KLUSBIB];
        $updatedUser = SnipeitUserMapper::mapInventoryUserToApiUser($this->post('users', $data));
        $this->updateUserAvatar($updatedUser);
        return $updatedUser;
    }


    // Activity
    // TODO: to be completed
    // note only desc order sorted on created_at seems to work
    public function getActivity($offset = 0, $limit=1000) {
        return $this->get('reports/activity?item_type=asset&order=desc&sort=created_at&offset=' . $offset . '&limit=' . $limit);
    }
    public function getActivityCheckout($offset = 0, $limit=1000) {
        return $this->get('reports/activity?item_type=asset&action_type=checkout&order=desc&sort=created_at&offset=' . $offset . '&limit=' . $limit);
    }
    public function getActivityCheckin($offset = 0, $limit=1000) {
        return $this->get('reports/activity?item_type=asset&action_type=checkin%20from&order=desc&sort=created_at&offset=' . $offset . '&limit=' . $limit);
    }
    public function getActivityUpdate($offset = 0, $limit=1000) {
        return $this->get('reports/activity?item_type=asset&action_type=update&order=desc&sort=created_at&offset=' . $offset . '&limit=' . $limit);
    }

    public function getLendings($offset = 0, $limit=1000) {
        $checkouts = $this->getActivityCheckout($offset, $limit);
        $checkins = $this->getActivityCheckin($offset, $limit);
        $lendings = array();
        foreach($checkouts as $checkout) {
            $lending = SnipeitActivityMapper::mapActivityToLending($checkout, $checkins);
            array_push($lendings, $lending);
        }
        return $lendings;
    }

    // helper methods
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
            RequestOptions::VERIFY => $this->sslCertificateVerification
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
                $this->logger->error('Inventory request to "' . $target . '" failed with status code ' . $statusCode);
            } else {
                $this->logger->error('Inventory request to "' . $target . '" failed with client exception (no response)');
            }
            if (isset($statusCode) && ($statusCode == 404 || $statusCode == 403)) {
                // access forbidden is considered as not found (can be an asset or user from another company)
                throw new \Api\Exception\NotFoundException();
            }
            else if (isset($statusCode) && ($statusCode >= 500)) {
                throw new \Api\Exception\InventoryException("Unable to access inventory (" . $clientException->getMessage().")", null, $clientException);
            }
            throw new \Api\Exception\InventoryException("Unexpected client exception!! (" . $clientException->getMessage().")", null, $clientException);
        } catch (ServerException $serverException) {
            $this->logger->error("Inventory unavailable (" . $serverException->getMessage().")");
            throw new \Api\Exception\InventoryException("Inventory unavailable (" . $serverException->getMessage().")", null, $serverException);
        }

        if ($res->getStatusCode() >= 400){
            if ($res->getStatusCode() == 404) {
                throw new \Api\Exception\NotFoundException();
            }
            $this->logger->error('Inventory request to "' . $target . '" failed with status code ' . $res->getStatusCode());
            throw new \RuntimeException('Inventory request to "' . $target . '" failed with status code ' . $res->getStatusCode());
        }
        $contentType = $res->getHeader('content-type')[0];
        $this->logger->debug("Response body message (first 250 bytes)=" . mb_strimwidth($res->getBody(),0,250,"..."));
        if (strpos($contentType, 'application/json') !== false ) {
            return \GuzzleHttp\json_decode($res->getBody());
        }
        return $res->getBody();
    }
}