<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\ModelMapper\ConsumerMapper;
use Api\Authorisation;

$app->get('/consumers', function ($request, $response, $args) {
	$this->logger->info("Klusbib '/consumers' route");
	$consumers = Capsule::table('consumers')
		->orderBy('category', 'asc')
		->orderBy('brand', 'asc')
		->orderBy('reference', 'asc')
		->get();
	$data = array();
	foreach ($consumers as $consumer) {
		array_push($data, ConsumerMapper::mapConsumerToArray($consumer));
	}
	return $response->withJson($data);
});

$app->get('/consumers/{consumerid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET '/consumers/id' route");
	$consumer = \Api\Model\Consumer::find($args['consumerid']);
	if (null == $consumer) {
		return $response->withStatus(404);
	}

	$data = ConsumerMapper::mapConsumerToArray($consumer);
	return $response->withJson($data);
});

$app->post('/consumers', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST '/consumers' route");
	/* Check if token has needed scope. */
	Authorisation::checkAccessByToken($this->token, ["consumers.all", "consumers.create"]);

	$data = $request->getParsedBody();
	if (empty($data) || empty($data["brand"]) || empty($data["reference"])) {
		return $response->withStatus(400); // Bad request
	}
	$consumer = new \Api\Model\Consumer();
	ConsumerMapper::mapArrayToConsumer($data, $consumer);
	$consumer->save();
	return $response->withJson(ConsumerMapper::mapConsumerToArray($consumer));
});

$app->put('/consumers/{consumerid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT '/consumers/id' route");
	Authorisation::checkAccessByToken($this->token, ["consumers.all", "consumers.update"]);
	$consumer = \Api\Model\Consumer::find($args['consumerid']);
	if (null == $consumer) {
		return $response->withStatus(404);
	}
	$data = $request->getParsedBody();
	ConsumerMapper::mapArrayToConsumer($data, $consumer);
	$consumer->save();
	return $response->withJson(ConsumerMapper::mapConsumerToArray($consumer));
});


$app->delete('/consumers/{consumerid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib DELETE '/consumers/id' route");
	Authorisation::checkAccessByToken($this->token, ["consumers.all", "consumers.delete"]);
	$consumer = \Api\Model\Consumer::find($args['consumerid']);
	if (null == $consumer) {
		return $response->withStatus(204);
	}
	$consumer->delete();
	return $response->withStatus(200);
});
	