<?php

namespace Api\Inventory;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Api\Model\User;

class InventoryTest extends TestCase
{
    public function testRealGetAssets() {
        $this->markTestSkipped("manual test with real inventory");
        $logger = $this->createMock(Logger::class);
        SnipeitInventory::instance($logger)->getTools();
    }
    public function testGetAssets() {
        // Create a mock and queue responses.
        $body = '{"total":1,"rows":[
        {"id":2,"name":"","asset_tag":"KB-000-17-002","serial":"","model":{"id":1,"name":"Boorhamer"},
        "model_number":"GBH 11 DE","eol":null,"status_label":{"id":2,"name":"Beschikbaar",
        "status_type":"deployable","status_meta":"deployable"},"category":{"id":2,"name":"Bouw"},
        "manufacturer":{"id":1,"name":"Bosch"},"supplier":null,"notes":null,"order_number":null,
        "company":{"id":1,"name":"Klusbib"},"location":{"id":1,"name":"Klusbib"},"rtd_location":{"id":1,"name":"Klusbib"},
        "image":null,"assigned_to":null,"warranty_months":null,"warranty_expires":null,
        "created_at":{"datetime":"2019-02-06 10:52:54","formatted":"2019-02-06 10:52 AM"},
        "updated_at":{"datetime":"2019-02-06 10:52:54","formatted":"2019-02-06 10:52 AM"},
        "last_audit_date":null,"next_audit_date":null,"deleted_at":null,"purchase_date":null,
        "last_checkout":null,"expected_checkin":null,"purchase_cost":null,"checkin_counter":0,
        "checkout_counter":0,"requests_counter":0,"user_can_checkout":true,"custom_fields":[],
        "available_actions":{"checkout":false,"checkin":false,"clone":false,"restore":false,"update":false,"delete":false}}
        ]}';

        $client = $this->mockHttpClient($body);

        $logger = $this->createMock(Logger::class);
        $inventory = new SnipeitInventory($client,"DUMMY_KEY", $logger);
        $assets = $inventory->getTools();
        $this->assertNotNull($assets);
//        var_dump($assets);
        $this->assertEquals(1, count($assets->rows));
        $this->assertEquals('KB-000-17-002', $assets->rows[0]->asset_tag);
    }

    public function testGetUserByEmail() {
        $email = 'email@klusbib.be';
        $body = '{"total":1,"rows":['
            . $this->createUserRow(1, 'myname', 'myfirstname', 'mylastname', $email, '1')
            .']}';

        $client = $this->mockHttpClient($body);
        $logger = $this->createMock(Logger::class);
        $inventory = new SnipeitInventory($client,"DUMMY_KEY", $logger);
        $user = $inventory->getUserByEmail($email);
        $this->assertNotNull($user);
        $this->assertEquals($email, $user->email);
    }

    public function testGetUserByExtId() {
        $extId = '10';
        $body = $this->createUserRow(10, 'myname', 'myfirstname', 'mylastname',
            'myusername', '1');
        $client = $this->mockHttpClient($body);
        $logger = $this->createMock(Logger::class);
        $inventory = new SnipeitInventory($client,"DUMMY_KEY", $logger);

        $user = $inventory->getUserByExtId($extId);
        $this->assertNotNull($user);
        $this->assertEquals($extId, $user->user_ext_id);
    }
    public function testPostUser() {
        $user = new User();
        $user->id = 1;
        $user->firstname = "myfirstname";
        $user->lastname = "mylastname";
        $user->email = "myemail";
        $body = '{
            "status": "success",
            "messages": "Gebruiker succesvol aangemaakt.",
            "payload": '
            . $this->createUserRow(10, $user->firstname . ' ' . $user->lastname, $user->firstname, $user->lastname,
            $user->email, $user->id)
            . '}';
        $client = $this->mockHttpClient($body);
        $logger = $this->createMock(Logger::class);
        $inventory = new SnipeitInventory($client,"DUMMY_KEY", $logger);

        $createdUser = $inventory->postUser($user);
        $this->assertNotNull($user);
        $this->assertEquals(10, $createdUser->user_ext_id);
    }

    public function testDeleteUser() {
        $body = '{
            "status": "success",
            "messages": "Gebruiker succesvol verwijderd.",
            "payload": null
        }';
        $client = $this->mockHttpClient($body);
        $logger = $this->createMock(Logger::class);
        $inventory = new SnipeitInventory($client,"DUMMY_KEY", $logger);

        $result = $inventory->deleteUser(999);
        $this->assertTrue($result);
    }

    protected function createUserRow($id = 1, $name = "", $first_name = "", $last_name = "", $username = "", $employee_num = "") {
        return '{
             "id": ' . $id . ',
            "avatar": "https://inventory.deelbaarmechelen.be/img/default-sm.png",
            "name": "' . $name . '",
            "first_name": "' . $first_name . '",
            "last_name": "' . $last_name . '",
            "username": "' . $username . '",
            "employee_num": "' . $employee_num . '",
            "manager": null,
            "jobtitle": null,
            "phone": null,
            "address": null,
            "city": null,
            "state": null,
            "country": null,
            "zip": null,
            "email": "",
            "department": null,
            "location": null,
            "notes": "",
            "permissions": null,
            "activated": false,
            "two_factor_activated": false,
            "assets_count": 0,
            "licenses_count": 0,
            "accessories_count": 0,
            "consumables_count": 0,
            "company": {
            "id": 1,
                "name": "Klusbib"
            },
            "created_at": {
            "datetime": "2019-03-20 13:50:59",
                "formatted": "2019-03-20 01:50 PM"
            },
            "updated_at": {
            "datetime": "2019-03-20 13:50:59",
                "formatted": "2019-03-20 01:50 PM"
            },
            "last_login": null,
            "available_actions": {
            "update": true,
                "delete": true,
                "clone": true,
                "restore": false
            },
            "groups": null
        }';
    }
    /**
     * @param $body response to be sent by the mock upon http request
     * @param $statusCode response statusCode to be sent by the mock upon http request
     * @return Client
     */
    protected function mockHttpClient($body, $statusCode = 200): Client
    {
        $mock = new MockHandler([
            new Response($statusCode, ['Content-Type' => 'application/json'], $body)
//            new Response(202, ['Content-Length' => 0]),
//            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        return $client;
    }

    private function createHttpClientMock() {

        // Create a mock and queue two responses.
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar']),
            new Response(202, ['Content-Length' => 0]),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        // The first request is intercepted with the first response.
        echo $client->request('GET', '/')->getStatusCode();
        //> 200
        // The second request is intercepted with the second response.
        echo $client->request('GET', '/')->getStatusCode();
        //> 202
        return $mock;
    }
}
