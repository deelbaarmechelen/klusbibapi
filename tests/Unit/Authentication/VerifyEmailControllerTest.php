<?php

namespace Tests\Unit\Authentication;

use Api\Authentication\VerifyEmailController;
use Api\Token\Token;
use Tests\DbUnitArrayDataSet;

require_once __DIR__ . '/../../test_env.php';

class VerifyEmailControllerTest extends \LocalDbWebTestCase
{
    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        $this->startdate = new \DateTime();
        $this->enddate = clone $this->startdate;
        $this->enddate->add(new \DateInterval('P365D'));

        return new DbUnitArrayDataSet(array(
            'contact' => array(
                array('id' => 1, 'first_name' => 'firstname', 'last_name' => 'lastname',
                    'role' => 'admin', 'email' => 'admin@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 2, 'first_name' => 'harry', 'last_name' => 'De Handige',
                    'role' => 'volunteer', 'email' => 'harry@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
                array('id' => 3, 'first_name' => 'daniel', 'last_name' => 'De Deler',
                    'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => 'ACTIVE',
                    'hash' => password_hash("test", PASSWORD_DEFAULT),
                    'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
                    'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
                ),
            )
        ));
    }

    public function testConfirmEmail()
    {
        echo "test Get auth/confirm/{userId}\n";
        $token = Token::generateToken(["users.all"], '3');
        $data = array(
            'token' => $token
        );
        $body = $this->client->get('/auth/confirm/3', $data);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $this->assertBodyContains($body, 'email adres is nu bevestigd');
    }
    public function testConfirmEmail_ExpiredToken()
    {
        echo "test Get auth/confirm/{userId}\n";
        $token = Token::generateToken(["users.all"], '3', new \DateTime());
        $data = array(
            'token' => $token
        );
        $body = $this->client->get('/auth/confirm/3', $data);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $this->assertBodyContains($body, 'Ongeldige token');
    }
    public function testConfirmEmail_InvalidToken()
    {
        echo "test Get auth/confirm/{userId}\n";
        $data = array(
            'token' => 'invalid'
        );
        $body = $this->client->get('/auth/confirm/3', $data);
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $this->assertBodyContains($body, 'Ongeldige token');
    }

    public function testConfirmEmail_MissingParam()
    {
        echo "test Get auth/confirm/{userId}\n";
        $body = $this->client->get('/auth/confirm/3');
        $this->assertEquals(200, $this->client->response->getStatusCode());
        $this->assertBodyContains($body, 'Ongeldige aanvraag: ontbrekende parameters');
    }

    /**
     * @param $body
     */
    private function assertBodyContains($body, $text): void
    {
        $this->assertTrue(strpos($body, $text) !== false);
    }

}
