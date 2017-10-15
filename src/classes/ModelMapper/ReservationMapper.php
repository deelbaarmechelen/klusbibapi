<?php
namespace Api\ModelMapper;

use \Api\Model\Reservation;

class ReservationMapper
{
	static public function mapReservationToArray($reservation) {

		$reservationArray  = array(
				"reservation_id" => $reservation->reservation_id,
				"tool_id" => $reservation->tool_id,
				"user_id" => $reservation->user_id,
				"title" => $reservation->title,
				"startsAt" => $reservation->startsAt,
				"endsAt" => $reservation->endsAt,
				"type" => $reservation->type,
				"state" => $reservation->state,
				"comment" => $reservation->comment,
		);
		
		return $reservationArray;
	}
}
