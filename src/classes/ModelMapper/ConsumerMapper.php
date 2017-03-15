<?php
namespace Api\ModelMapper;

use \Api\Model\Consumer;

class ConsumerMapper
{
	static public function mapConsumerToArray($consumer) {

		$consumerArray = array("consumer_id" => $consumer->consumer_id,
			"category" => $consumer->category,
			"brand" => $consumer->brand,
			"reference" => $consumer->reference,
			"description" => $consumer->description,
			"unit" => $consumer->unit,
			"price" => $consumer->price,
			"stock" => $consumer->stock,
			"low_stock" => $consumer->low_stock,
			"packed_per" => $consumer->packed_per,
			"provider" => $consumer->provider,
			"comment" => $consumer->comment,
			"public" => $consumer->public
		);
		return $consumerArray;
	}
	static public function mapArrayToConsumer($data, $consumer) {
		if (isset($data["category"])) {
			$consumer->category = $data["category"];
		}
		if (isset($data["brand"])) {
			$consumer->brand = $data["brand"];
		}
		if (isset($data["reference"])) {
			$consumer->reference = $data["reference"];
		}
		if (isset($data["description"])) {
			$consumer->description = $data["description"];
		}
		if (isset($data["unit"])) {
			$consumer->unit = $data["unit"];
		}
		if (isset($data["price"])) {
			$consumer->price = $data["price"];
		}
		if (isset($data["stock"])) {
			$consumer->stock = $data["stock"];
		}
		if (isset($data["low_stock"])) {
			$consumer->low_stock = $data["low_stock"];
		}
		if (isset($data["packed_per"])) {
			$consumer->packed_per = $data["packed_per"];
		}
		if (isset($data["provider"])) {
			$consumer->provider = $data["provider"];
		}
		if (isset($data["comment"])) {
			$consumer->comment = $data["comment"];
		}
		if (isset($data["public"])) {
			$consumer->public = $data["public"];
		}
		
	}
}
