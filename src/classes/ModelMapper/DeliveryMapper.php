<?php
namespace Api\ModelMapper;

use \Api\Model\Delivery;

class DeliveryMapper
{
	static public function mapDeliveryToArray($delivery) {

		$deliveryArray  = array(
            "id" => $delivery->id,
            "reservation_id" => $delivery->reservation_id,
            "user_id" => $delivery->user_id,
            "state" => $delivery->state,
            "pick_up_address" => $delivery->pick_up_address,
            "drop_off_address" => $delivery->drop_off_address,
            "pick_up_date" => $delivery->pick_up_date,
            "drop_off_date" => $delivery->drop_off_date,
            "comment" => $delivery->comment,
		);
		
		return $deliveryArray;
	}
}
