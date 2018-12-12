<?php
use Api\Token;
use Tests\DbUnitArrayDataSet;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware\HttpBasicAuthentication;
use Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;
use Api\Model\UserState;
use Api\Model\EmailState;

require_once __DIR__ . '/../test_env.php';

class UsersTest extends LocalDbWebTestCase
{
	private $startdate;
	private $enddate;
	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		$this->startdate = new DateTime();
		$this->enddate = clone $this->startdate;
		$this->enddate->add(new DateInterval('P365D'));
		
		return new DbUnitArrayDataSet(array(
			'users' => array(
				array('user_id' => 1, 'firstname' => 'firstname', 'lastname' => 'lastname',
						'role' => 'admin', 'email' => 'admin@klusbib.be', 'state' => 'ACTIVE',
						'hash' => password_hash("test", PASSWORD_DEFAULT),
						'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
						'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
				array('user_id' => 2, 'firstname' => 'harry', 'lastname' => 'De Handige',
						'role' => 'volunteer', 'email' => 'harry@klusbib.be', 'state' => 'ACTIVE',
						'hash' => password_hash("test", PASSWORD_DEFAULT),
						'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
						'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
				array('user_id' => 3, 'firstname' => 'daniel', 'lastname' => 'De Deler',
						'role' => 'member', 'email' => 'daniel@klusbib.be', 'state' => 'ACTIVE',
						'hash' => password_hash("test", PASSWORD_DEFAULT),
						'membership_start_date' => $this->startdate->format('Y-m-d H:i:s'),
						'membership_end_date' => $this->enddate->format('Y-m-d H:i:s')
						),
			),
			'reservations' => array(
					array('reservation_id' => 1, 'tool_id' => 1, 'user_id' => 1,
							'title' => 'title 1',
							'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
							'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
							'type' => 'repair'
					),
					array('reservation_id' => 2, 'tool_id' => 1, 'user_id' => 1,
							'title' => 'title 2',
							'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
							'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
							'type' => 'maintenance'
					),
					array('reservation_id' => 3, 'tool_id' => 3, 'user_id' => 1,
							'title' => 'title 3',
							'startsAt' => $this->startdate->format('Y-m-d H:i:s'),
							'endsAt' => $this->enddate->format('Y-m-d H:i:s'),
							'type' => 'repair'
					),
			),
		));
	}
	
	public function testGetUsers()
	{
		echo "test GET users\n";
		$body = $this->client->get('/users');
// 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$users = json_decode($body);
		$this->assertEquals(3, count($users));
	}
	public function testGetUsersByEmail()
	{
		echo "test GET users by email\n";
        $data = array(
            "email" => "daniel@klusbib.be"
        );
        $this->setToken('guest', ["users.read.state"]);
        $body = $this->client->get('/users', $data);
// 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
        $user = json_decode($body);
		$this->assertEquals(3, $user->user_id);
		$this->assertEquals(UserState::ACTIVE, $user->state);
		$this->assertEquals($this->enddate->format('Y-m-d'), $user->membership_end_date);
	}

	public function testGetUsersUnauthorized()
	{
		echo "test GET users unauthorized\n";
		$this->setUser('daniel@klusbib.be');
		$this->setToken('3', ["users.none"]);
		$body = $this->client->get('/users');
		$this->assertEquals(403, $this->client->response->getStatusCode());
		$this->assertTrue(empty($body));
	}
	
	public function testPostUsers()
	{
		echo "test POST users\n";
		$scopes = array("users.create");
		$this->setToken("1", $scopes);
		$header = array('Authorization' => "bearer 123456");
		$container = $this->app->getContainer();
		$data = array("user_id" => "5", "firstname" => "myname", 
				"lastname" => "my lastname",
				"membership_start_date" => "2018-12-12",
				"email" => "myname.lastname@klusbib.be",
				"role" => "member"
		);
		$body = $this->client->post('/users', $data, $header);
// 		print_r($body);
		$this->assertEquals(201, $this->client->response->getStatusCode());
		$user = json_decode($body);
		$this->assertNotNull($user->user_id);
		
		// check user has properly been created
		$scopes = array("users.all");
		$this->setToken(null, $scopes);
		$bodyGet = $this->client->get('/users/' . $user->user_id);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$user = json_decode($bodyGet);
		$this->assertEquals($data["firstname"], $user->firstname);
		$this->assertEquals($data["lastname"], $user->lastname);
		$this->assertEquals($data["email"], $user->email);
		$this->assertEquals($data["role"], $user->role);
		
	}
	public function testPostUsersEnrolment()
	{
		echo "test POST users (enrolment)\n";
		// scope users.all and users.create missing
		$scopes = array("users.list", "users.update", "users.read");
		$this->setToken("1", $scopes);
		
		$data = array("firstname" => "myname",
				"lastname" => "my lastname",
				"email" => "myname.lastname@klusbib.be",
				"role" => "admin",
				"accept_terms" => true
		);
		$body = $this->client->post('/users', $data);
		$this->assertEquals(201, $this->client->response->getStatusCode());
		$user = json_decode($body);
		$this->assertNotNull($user->user_id);

		// check user has properly been created
		$scopes = array("users.all");
		$this->setToken(null, $scopes);
		$bodyGet = $this->client->get('/users/' . $user->user_id);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$user = json_decode($bodyGet);
		$this->assertEquals($data["firstname"], $user->firstname);
		$this->assertEquals($data["lastname"], $user->lastname);
		$this->assertEquals($data["email"], $user->email);
		$this->assertEquals("member", $user->role); // role should be forced to member
		$this->assertEquals(UserState::CHECK_PAYMENT, $user->state); // state should be forced to check payment
		$this->assertEquals(EmailState::CONFIRM_EMAIL, $user->email_state); // email state should be forced to confirm email
		$this->assertNotNull($user->accept_terms_date);
	}
	
	public function testPostUsersAlreadyExists()
	{
		echo "test POST users (already exists)\n";
// 		$scopes = array("users.create");
		// scope users.all and users.create missing
		$scopes = array("users.list", "users.update", "users.read");
		$this->setToken("1", $scopes);
		
		$data = array("firstname" => "myname",
				"lastname" => "my lastname",
				"email" => "daniel@klusbib.be",
				"role" => "admin",
				"accept_terms" => true
		);
		$body = $this->client->post('/users', $data);
		$this->assertEquals(409, $this->client->response->getStatusCode());
		
	}
	public function testGetUser()
	{
		echo "test GET users\n";
		$body = $this->client->get('/users/1');
// 		print_r($body);
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$user = json_decode($body);
		$this->assertEquals("1", $user->user_id);
		$this->assertEquals("firstname", $user->firstname);
		$this->assertEquals("lastname", $user->lastname);
		$this->assertEquals("admin@klusbib.be", $user->email);
		$this->assertEquals("admin", $user->role);
		$this->assertEquals($this->startdate->format('Y-m-d'), $user->membership_start_date);
		$this->assertEquals($this->enddate->format('Y-m-d'), $user->membership_end_date);
		$this->assertEquals(3, count($user->reservations));
		
	}
	
	public function testPutUser()
	{
		echo "test PUT users\n";
		$data = array("firstname" => "new firstname", 
				"lastname" =>"my new lastname",
				"email" =>"newemail@klusbib.be",
				"role" => "new admin");
		$header = array();
		$this->setToken("1", null);
		$this->client->put('/users/1', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());

		// check user has properly been updated
		$bodyGet = $this->client->get('/users/1');
		$this->assertEquals(200, $this->client->response->getStatusCode());
		$user = json_decode($bodyGet);
		$this->assertEquals($data["firstname"], $user->firstname);
		$this->assertEquals($data["lastname"], $user->lastname);
		$this->assertEquals($data["email"], $user->email);
		$this->assertEquals($data["role"], $user->role);
		
	}
	public function testPutUserPassword()
	{
		echo "test PUT users (password)\n";
		$data = array("password" => "new pwd");
		$header = array();
// 		$newHash = password_hash("new pwd", PASSWORD_DEFAULT);
// 		echo "expected new hash = $newHash \n";
		$this->setToken("1", null);
		$responsePut = $this->client->put('/users/1', $data, $header);
		$this->assertEquals(200, $this->client->response->getStatusCode());
// 		print_r($responsePut);
		
		// check get token ok with new pwd and nok with another pwd
		echo "Check get token no longer possible\n";
// 		$data = ["users.all"];
// 		$header = array('Authorization' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0");
		// FIXME: need to call middleware to test new password!
		$this->callBasicAuthMw('admin@klusbib.be',"new pwd", 200);
		$this->callBasicAuthMw('admin@klusbib.be',"other pwd", 401);
	}
	
	private function callBasicAuthMw($user, $pwd, $expectedStatusCode = 200) {
		$query = array();
		$env = \Slim\Http\Environment::mock([
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI' => '/token',
				'AUTH_TYPE' => 'Basic',
				'PHP_AUTH_USER' => $user,
				'PHP_AUTH_PW' => $pwd,
				'SERVER_NAME' => 'example.com',
				'CONTENT_TYPE' => 'application/json;charset=utf8',
				'CONTENT_LENGTH' => 15
		]);
		$request = Request::createFromEnvironment($env);
		
		$response = new Response;
		$auth = new HttpBasicAuthentication([
			"path" => "/token",
			"secure" => false,
			"relaxed" => ["admin", "klusbib.deeleco"],
			"authenticator" => new PdoAuthenticator([
					"pdo" => $this->getPdo(),
					"table" => "users",
					"user" => "email",
					"hash" => "hash"
			]),
		]);
		
		$next = function (Request $request, Response $response) {
			$response->getBody()->write("Success");
			return $response;
		};
		
		$response = $auth($request, $response, $next);
		
		$this->assertEquals($expectedStatusCode, $response->getStatusCode());
// 		$this->assertEquals("Success", $response->getBody());
	}
	
	public function testPutUserNotFound()
	{
		echo "test PUT users (not found)\n";
		$data = array("firstname" => "new firstname",
				"lastname" =>"my new lastname",
				"email" =>"newemail@klusbib.be",
				"role" => "new admin");
		$header = array();
		$this->setToken("1", null);
		$this->client->put('/users/999', $data, $header);
		$this->assertEquals(404, $this->client->response->getStatusCode());
	}
	
	public function testDeleteUser()
	{
		echo "test DELETE user\n";
		$this->client->delete('/users/1');
		$body = $this->assertEquals(200, $this->client->response->getStatusCode());
		
		// delete inexistant user
		$this->client->delete('/users/1');
		$body = $this->assertEquals(204, $this->client->response->getStatusCode());
	}
	
}