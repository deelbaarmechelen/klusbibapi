<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Validator\UserValidator;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\UserMapper;
use Api\Model\User;
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
	
	$data = $request->getParsedBody();
	$this->logger->info("parsedbody=" . json_encode($data));
	if (empty($data) || !UserValidator::isValidUserData($data, $this->logger)) {
		return $response->withStatus(400); // Bad request
	}
	$user = new \Api\Model\User;
	if (!isset($data["user_id"]) || empty($data["user_id"])) {
		$max_user_id = Capsule::table('users')->max('user_id');
		$data["user_id"] = $max_user_id + 1;
		$this->logger->info("New user will be assigned id " . $data["user_id"]);
	}
	if (!empty($data["membership_start_date"])) {
		$user->membership_start_date = $data["membership_start_date"];
		if (!empty($data["membership_end_date"])) {
			$user->membership_end_date = $data["membership_end_date"];
		} else { // default to 1 year membership
			$user->membership_end_date = strtotime("+1 year", strtotime($data["membership_start_date"]));
		}
	}
	$isAdmin = False; // FIXME: check current user role
	UserMapper::mapArrayToUser($data, $user, $isAdmin, $this->logger);
	$user->save();
	return $response->withJson(UserMapper::mapUserToArray($user));
});

	
$app->put('/users/{userid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT on '/users/id' route");

	if (false === $this->token->hasScope(["users.all", "users.update", "users.update.owner"])) {
// 		throw new ForbiddenException("Token not allowed to update users.", 403);
		return $response->withStatus(403)->write("Token not allowed to update users.");
	}
	
	$currentUser = \Api\Model\User::find($this->token->getSub());
	$isAdmin = false;
	if ($currentUser->role == 'admin') {
		$isAdmin = true;
	}
	
	$user = \Api\Model\User::find($args['userid']);
	if (null == $user) {
		return $response->withStatus(404);
	}

	if (false === $this->token->hasScope(["users.all", "users.update"]) && 
			$user->user_id != $this->token->decoded->sub) {
		return $response->withStatus(403)->write("Token sub doesn't match user.");
	}
	$data = $request->getParsedBody();
	UserMapper::mapArrayToUser($data, $user, $isAdmin, $this->logger);
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
		
	