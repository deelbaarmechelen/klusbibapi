<?php
use Api\Token;
use Api\Model\UserState;
use Api\Mail\MailManager;
use Illuminate\Database\Capsule\Manager as Capsule;

$app->post("/auth/reset", function ($request, $response, $arguments) use ($app) {
	$this->logger->info("Klusbib POST '/auth/reset' route");
	// lookup email in users table
	$body = $request->getParsedBody();
	$this->logger->info("parsedbody=" . json_encode($body));
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

$app->post('/auth/verifyemail', function ($request, $response, $args) {
	// TODO: check who is allowed to request email verification
	// lookup email in users table
	$body = $request->getParsedBody();
	$this->logger->info("parsedbody=" . json_encode($body));
	$email = $body["email"];
	$this->logger->debug("email=" . $email);
	$user = Capsule::table('users')->where('email', $email)->first();
	if (null == $user) {
		return $response->withStatus(404);
	}
	if ($user->state != UserState::CONFIRM_EMAIL) {
		$data["status"] = "error";
		$data["message"] = "No email confirmation required for user";
		return $response->withStatus(412)
		->withHeader("Content-Type", "application/json")
		->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	}
	$mailmgr = new MailManager();
	$sub = $user->user_id;
	$scopes = ["auth.confirm"];
	$result = $mailmgr->sendEmailVerification($user->user_id, $user->firstname, $user->email,
			Token::generateToken($scopes, $sub));
	$message = $mailmgr->getLastMessage();
	$this->logger->info('Sending email verification result: ' . $message);

	$data["status"] = "ok";
	$data["message"] = $message;
	
	return $response->withStatus(200)
		->withHeader("Content-Type", "application/json")
		->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->get('/auth/confirm/{userId}', function ($request, $response, $args) {
	$token = $request->getQueryParam("token", $default = null);
	if (is_null($token)) {
		$this->logger->warn("Missing token or user id in email confirmation");
		return $response->withStatus(400);
	}
	
	// Check token
	$decoded = $this->JwtAuthentication->decodeToken($token);
	$this->logger->debug("decoded token=" . json_encode($decoded));
	if (false === $decoded) {
		return $response->withStatus(401);
	}
	$token = new Token();
	$token->hydrate($decoded);
	if ($args["userId"] != $token->getSub()) {
		// not allowed to verify address for another user
		return $response->withStatus(403);
	}
	$user = \Api\Model\User::find($token->getSub());
	if ($user->state === "CONFIRM_EMAIL") {
		$user->state = "CHECK_PAYMENT";
		$user->save();
	}
	// Render index view
	return $this->renderer->render($response, 'confirm_email.phtml',  [
			'userId' => $args['userId']
	]);
});


	
// $app->get("/auth/password/reset", "PasswordResetController:getResetPassword")->setName("auth.password.reset");

// $app->post("/auth/password/reset", "PasswordResetController:postResetPassword");

