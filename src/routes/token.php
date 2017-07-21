<?php

use Api\Token;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Mail\MailManager;

$app->post("/token", function ($request, $response, $arguments) use ($app) {
	$this->logger->info("Klusbib POST '/token' route");
	$valid_scopes = Token::validScopes();
		
	$container = $app->getContainer();
	$user = Capsule::table('users')->where('email', $container["user"])->first();
	if (null == $user) {
		return $response->withStatus(404);
	}
	$sub = $user->user_id;
	$requested_scopes = Token::allowedScopes($user->role);
	$scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
		return in_array($needle, $valid_scopes);
	});
	$token = Token::generateToken($scopes, $sub); 
	$this->logger->info("Token generated with scopes " . json_encode($scopes) . " and sub " .  json_encode($sub));
	
	$data["status"] = "ok";
	$data["token"] = $token;

	return $response->withStatus(201)
		->withHeader("Content-Type", "application/json")
		->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

/* This is just for debugging, not usefull in real life. */
$app->get("/dump", function ($request, $response, $arguments) {
	print_r($this->token);
});

$app->post("/auth/reset", function ($request, $response, $arguments) use ($app) {
	$this->logger->info("Klusbib POST '/auth/reset' route");
	// lookup email in users table
	$body = $request->getParsedBody();
	$this->logger->info("parsedbody=" . json_encode($data));
	$email = $body["email"];
	$this->logger->debug("email=" . $email);
	$user = Capsule::table('users')->where('email', $email)->first();
	if (null == $user) {
		return $response->withStatus(404);
	}
	
	// generate temporary token allowing password change
	$sub = $user->user_id;
	$requested_scopes = Token::allowedScopes($user->role);
	$this->logger->debug("user=" . json_encode($user));
	$scopes = array_filter($requested_scopes, function ($needle) {
		return in_array($needle, Token::resetPwdScopes());
	});
	$this->logger->debug("requested_scopes=" . json_encode($requested_scopes));
	$token = Token::generateToken($scopes, $sub);
	$this->logger->info("Token generated with scopes " . json_encode($scopes) . " and sub " .  json_encode($sub));

	// generate email
	$mailMgr = new MailManager();
	$result = $mailMgr->sendPwdRecoveryMail($user->user_id, $user->firstname, $email, $token);
	
	if (!$result) { // error in mail send
		$error["message"] = $mailMgr->getLastMessage();
		return $response->withStatus(500)
			->withHeader("Content-Type", "application/json")
			->write(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	}
	
	$data["status"] = "ok";
	$data["message"] = $mailMgr->getLastMessage();
	$data["token"] = $token;
	
	return $response->withStatus(201)
		->withHeader("Content-Type", "application/json")
		->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	
});

$app->get('/auth/reset/{userId}', function ($request, $response, $args) {
	$this->logger->info("Klusbibapi GET '/auth/reset/{userId}' route");

	// Render index view
	return $this->renderer->render($response, 'reset_pwd.phtml',  [
        'userId' => $args['userId']
    ]);
});

// $app->get("/auth/password/reset", "PasswordResetController:getResetPassword")->setName("auth.password.reset");
	
// $app->post("/auth/password/reset", "PasswordResetController:postResetPassword");
	
