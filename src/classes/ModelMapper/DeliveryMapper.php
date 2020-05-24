<?php
namespace Api\ModelMapper;

use \Api\Model\Delivery;

class DeliveryMapper
{
	static public function mapDeliveryToArray($delivery) {

		$deliveryArray  = array(
				"delivery_id" => $delivery->delivery_id,
				"reservation_id" => $delivery->reservation_id,
				"user_id" => $delivery->user_id,
				"state_id" => $delivery->state_id,
				"pick_up_address" => $delivery->pick_up_address,
				"drop_off_address" => $delivery->drop_off_address,
				"comment" => $delivery->comment,
				"date" => $delivery->date,
		);
		
		return $deliveryArray;
	}
}
