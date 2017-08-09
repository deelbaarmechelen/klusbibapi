<?php
namespace Api\Model;

abstract class ReservationState
{
	const REQUESTED = "REQUESTED";
	const CONFIRMED = "CONFIRMED";
	const CANCELLED = "CANCELLED";
}
