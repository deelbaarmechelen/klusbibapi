<?php

use Tests\DbUnitArrayDataSet;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware\HttpBasicAuthentication;
use Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;
use Api\Model\UserState;
use Api\Model\EmailState;
use Api\Model\ToolState;

require_once __DIR__ . '/../test_env.php';

class StatsTest extends LocalDbWebTestCase
{
    public function getDataSet()
    {
        $this->startdate = new DateTime();
        $this->enddate = clone $this->startdate;
        $this->enddate->add(new DateInterval('P365D'));

        return new DbUnitArrayDataSet(array(
            'contact' => array(
                array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
                    'role' => 'admin', 'email' => 'admin@klusbib.be', 'state' => UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
                    'role' => 'volunteer', 'email' => 'harry@klusbib.be', 'state' => UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => UserState::ACTIVE,
                    'password' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
            ),
            'kb_tools' => array(
                array('tool_id' => 1, 'name' => 'tool 1', 'description' => 'description 1', 'code' => 'KB-000-18-001',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'wood',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 'state' => ToolState::NEW,
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 1
                ),
                array('tool_id' => 2, 'name' => 'tool 2', 'description' => 'description 2', 'code' => 'KB-000-17-001',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'wood',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 'state' => ToolState::READY,
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 1
                ),
                array('tool_id' => 3, 'name' => 'tool 3', 'description' => 'description 3', 'code' => 'KB-000-17-003',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'construction',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 'state' => ToolState::IN_USE,
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 1
                ),
                array('tool_id' => 4, 'name' => 'tool 4', 'description' => 'description 4', 'code' => 'KB-000-17-002',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'construction',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 'state' => ToolState::MAINTENANCE,
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 0
                ),
                array('tool_id' => 5, 'name' => 'tool 4', 'description' => 'description 4', 'code' => 'KB-000-17-004',
                    'brand' => 'Makita', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'construction',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 'state' => ToolState::MAINTENANCE,
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 0
                ),
                array('tool_id' => 6, 'name' => 'tool 4', 'description' => 'description 4', 'code' => 'KB-000-17-005',
                    'brand' => 'Brol', 'type' => 'ABC-123', 'serial' => '00012345', 'category' => 'construction',
                    'manufacturing_year' => '2017', 'manufacturer_url' => 'http://manufacturer.com', 'state' => ToolState::DISPOSED,
                    'doc_url' => 'my doc', 'img' => '/assets/img/tool.jpg', 'replacement_value' => '25', 'visible' => 0
                )
            ),
        ));
    }

    public function testGetMonthly()
    {
        echo "test GET monthly stats\n";
        $body = $this->client->get('/stats/monthly');
 		print_r($body);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $stats = json_decode($body);
//        $this->assertEquals("user-statistics", $stats);
    }


}