<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Validator\UserValidator;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\UserMapper;
use \Api\Model\User;
use Api\Authorisation;

$app->get('/users', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET on '/users' route");

	$authorised = Authorisation::checkUserAccess($this->token, "list", null);
	if (!$authorised) {
		$this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()) );
		return $response->withStatus(403);
	}
	$sortdir = $request->getQueryParam('_sortDir');
	if (!isset($sortdir)) {
		$sortdir = 'asc';
	}
	$sortfield = $request->getQueryParam('_sortField');
	if (!User::canBeSortedOn($sortfield) ) {
		$sortfield = 'lastname';
	}
	$users = Capsule::table('users')->orderBy($sortfield, $sortdir)->get();
	
	$data = array();
	foreach ($users as $user) {
		array_push($data, UserMapper::mapUserToArray($user));
	}
	return $response->withJson($data);
});

$app->get('/users/{userid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET on '/users/id' route");
// 	Authorisation::checkAccessByToken($this->token, ["users.all", "users.read", "users.read.owner"]);
	$authorised = Authorisation::checkUserAccess($this->token, "read", $args['userid']);
	if (!$authorised) {
		$this->logger->warn("Access denied for user " . $args['userid']);
		return $response->withStatus(403);
	}
	// FIXME: restrict access to owner only for users.read.owner
	$user = User::find($args['userid']);
	if (null == $user) {
		return $response->withStatus(404);
	}
	return $response->withJson(UserMapper::mapUserToArray($user));
});
	
$app->post('/users', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST on '/users' route");

	$authorised = Authorisation::checkUserAccess($this->token, "create", null);
	if (!$authorised) {
		$this->logger->warn("Token not allowed to create users.");
		return $response->withStatus(403);
	}
	
	$parsedBody = $request->getParsedBody();
	$this->logger->info("parsedbody=" . json_encode($parsedBody));
	if (empty($parsedBody) || !UserValidator::isValidUserData($parsedBody, $this->logger)) {
		return $response->withStatus(400); // Bad request
	}
	$user = new \Api\Model\User;
	$user->firstname = $parsedBody["firstname"];
	$user->lastname = $parsedBody["lastname"];
	$user->role = $parsedBody["role"];
	if (isset($parsedBody["user_id"]) && !empty($parsedBody["user_id"])) {
		$user->user_id= $parsedBody["user_id"];
	} else {
		$max_user_id = Capsule::table('users')->max('user_id');
		$user->user_id = $max_user_id + 1;
		$this->logger->info("New user will be assigned id " . $user->user_id);
	}
	if (!empty($parsedBody["email"])) {
		$user->email= $parsedBody["email"];
	}
	if (!empty($parsedBody["membership_start_date"])) {
		$user->membership_start_date = $parsedBody["membership_start_date"];
		if (!empty($parsedBody["membership_end_date"])) {
			$user->membership_end_date = $parsedBody["membership_end_date"];
		} else { // default to 1 year membership
			$user->membership_end_date = strtotime("+1 year", strtotime($parsedBody["membership_start_date"]));
		}
	}
	$this->logger->debug("Before save: " . json_encode($user));
	$user->save();
	$this->logger->debug("After save: " . json_encode($user));
	return $response->withJson(UserMapper::mapUserToArray($user));
});

	
$app->put('/users/{userid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT on '/users/id' route");

	if (false === $this->token->hasScope(["users.all", "users.update", "users.update.owner"])) {
// 		throw new ForbiddenException("Token not allowed to update users.", 403);
		return $response->withStatus(403)->write("Token not allowed to update users.");
	}
	
	$usermapper = new UserMapper();
	$user = \Api\Model\User::find($args['userid']);
	if (null == $user) {
		return $response->withStatus(404);
	}

	if (false === $this->token->hasScope(["users.all", "users.update"]) && 
			$user->user_id != $this->token->decoded->sub) {
		return $response->withStatus(403)->write("Token sub doesn't match user.");
	}
	
	$parsedBody = $request->getParsedBody();
	if (isset($parsedBody["user_id"])) {
		$user->user_id= $parsedBody["user_id"];
	}
	if (isset($parsedBody["firstname"])) {
		$user->firstname = $parsedBody["firstname"];
	}
	if (isset($parsedBody["lastname"])) {
		$user->lastname = $parsedBody["lastname"];
	}
	if (isset($parsedBody["email"])) {
		$user->email = $parsedBody["email"];
	}
	if (isset($parsedBody["role"])) {
		$user->role = $parsedBody["role"];
	}
	if (isset($parsedBody["password"])) {
		$this->logger->info("Updating password for user " . $user->user_id . " - " . $user->firstname . " " . $user->lastname);
		$user->hash = password_hash($parsedBody["password"], PASSWORD_DEFAULT);
	}
	// FIXME: also allow update of membership dates for admin users
	$user->save();
	
	return $response->withJson(UserMapper::mapUserToArray($user));
});
	
$app->delete('/users/{userid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib DELETE on '/users/id' route");

	if (false === $this->token->hasScope(["users.all", "users.delete"])) {
		throw new ForbiddenException("Token not allowed to delete users.", 403);
	}
	
	$user = \Api\Model\User::find($args['userid']);
	if (null == $user) {
		return $response->withStatus(204);
	}
	$user->delete();
	return $response->withStatus(200);
});
		
	