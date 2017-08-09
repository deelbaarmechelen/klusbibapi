<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Validator\UserValidator;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\UserMapper;
use Api\Model\User;
use Api\Authorisation;
use Api\ModelMapper\ReservationMapper;
use Api\Mail\MailManager;
use Api\Token;
use Api\Model\UserState;

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
	$page = $request->getQueryParam('_page');
	if (!isset($page)) {
		$page = '1';
	}
	$perPage = $request->getQueryParam('_perPage');
	if (!isset($perPage)) {
		$perPage = '50';
	}
	$users = Capsule::table('users')->orderBy($sortfield, $sortdir)->get();
	$users_page = array_slice($users, ($page - 1) * $perPage, $perPage);
	$data = array();
	foreach ($users_page as $user) {
		array_push($data, UserMapper::mapUserToArray($user));
	}
	return $response->withJson($data)
					->withHeader('X-Total-Count', count($users));
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
	$userArray = UserMapper::mapUserToArray($user);
	
	// Add user reservations
	$reservations = $user->reservations;
	$reservationsArray = array();
	foreach ($reservations as $reservation) {
		array_push($reservationsArray, ReservationMapper::mapReservationToArray($reservation));
	}
	$userArray["reservations"] = $reservationsArray;
	return $response->withJson($userArray);
});
	
$app->post('/users', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST on '/users' route");
	$data = $request->getParsedBody();
	$this->logger->info("parsedbody=" . json_encode($data));
	if (empty($data) 
			|| !UserValidator::containsMandatoryData($data, $this->logger)
			|| !UserValidator::isValidUserData($data, $this->logger)) {
		return $response->withStatus(400); // Bad request
	}

	$isAdmin = false;
	$user = new \Api\Model\User;
	$authorised = Authorisation::checkUserAccess($this->token, "create", null);
	if (!$authorised) {
		// Web enrolment
		$user->state = UserState::CONFIRM_EMAIL;
		$user->role = 'member'; // only members can be created through web enrolment
		$data["membership_start_date"] = strftime('%Y-%m-%d',time());
		$mailmgr = new MailManager();
		$sendNotification = TRUE;
		$sendEmailVerification = TRUE;
	} else {
		$currentUser = \Api\Model\User::find($this->token->getSub());
		if (!isset($currentUser)) {
			$this->logger->warn("No user found for token " + $this->token->getSub());
			return $response->withStatus(403);
		}
		if ($currentUser->role == 'admin') {
			$isAdmin = true;
		}
		$sendNotification = FALSE;
		$sendEmailVerification = FALSE;
	}
	
	if (!isset($data["user_id"]) || empty($data["user_id"])) {
		$max_user_id = Capsule::table('users')->max('user_id');
		$user->user_id = $max_user_id + 1;
		$this->logger->info("New user will be assigned id " . $user->user_id);
	}
	if (!empty($data["membership_start_date"])) {
		$user->membership_start_date = $data["membership_start_date"];
		if (!empty($data["membership_end_date"])) {
			$user->membership_end_date = $data["membership_end_date"];
		} else { // default to 1 year membership
			$user->membership_end_date = strftime('%Y-%m-%d',strtotime("+1 year", strtotime($data["membership_start_date"])));
		}
	}
	UserMapper::mapArrayToUser($data, $user, $isAdmin, $this->logger);
	$user->save();
	if ($sendEmailVerification) {
		$sub = $user->user_id;
		$scopes = ["auth.confirm"];
		$result = $mailmgr->sendEmailVerification($user->user_id, $user->firstname, $user->email,
				Token::generateToken($scopes, $sub));
		$this->logger->info('Sending email verification result: ' . $mailmgr->getLastMessage());
	}
	if ($sendNotification) {
		$result = $mailmgr->sendEnrolmentNotification(ENROLMENT_NOTIF_EMAIL, $user);
		$this->logger->info('Sending enrolment notification result: ' . $mailmgr->getLastMessage());
	}
	
	return $response->withJson(UserMapper::mapUserToArray($user))
					->withStatus(201);
});

	
$app->put('/users/{userid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT on '/users/id' route");

	if (false === $this->token->hasScope(["users.all", "users.update", "users.update.owner", "users.update.password"])) {
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
	if (empty($data) || !UserValidator::isValidUserData($data, $this->logger)) {
		return $response->withStatus(400)->write("Invalid value for registration number"); // Bad request
	}
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
		
	