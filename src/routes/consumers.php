<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$app->get('/consumers', function ($request, $response, $args) {
	$this->logger->info("Klusbib '/consumers' route");
	//$reservations = Capsule::table('consumers')->orderBy('category', 'desc')->get();
	$data = array();
	// 	foreach ($reservations as $reservation) {
	// 		$item  = array(
	// 				"reservation_id" => $reservation->reservation_id,
	// 				"tool_id" => $reservation->tool_id,
	// 				"user_id" => $reservation->user_id,
	// 				"title" => $reservation->title,
	// 				"startsAt" => $reservation->startsAt,
	// 				"endsAt" => $reservation->endsAt,
	// 				"type" => $reservation->type,
	// 		);
	// 		array_push($data, $item);
	// 	}
	$item1 = array("ID" => "59","Category" => "Sanding paper","Brand" => "Metabo","Reference" => "624025",
			"Description" => "Sanding disc velcro 150mm 6g alox P240","Unit" => "piece",
			"Price" => "1.25","Stock" => "18","LowStock" => "10","PackedPer" => "25","Provider" => "Lecot",
			"TID" => "TC029","Location" => "A12","Comment" => "","Public" => "1");
	$item2 = array("ID" => "60","Category" => "Sanding paper",
			"Brand" => "Metabo","Reference" => "624033","Description" => "Sanding disc velcro 150mm 6g alox P180 white",
			"Unit" => "piece","Price" => "1.25","Stock" => "30","LowStock" => "10","PackedPer" => "25","Provider" => "Lecot",
			"TID" => "TC030","Location" => "A12","Comment" => "","Public" => "1");
	array_push($data, $item1);
	array_push($data, $item2);

	return $response->withJson($data);
});
