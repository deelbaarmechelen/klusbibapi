<?php
namespace Api\Model;

abstract class ReservationState
{
	public const REQUESTED = "REQUESTED";
	public const CONFIRMED = "CONFIRMED";
	public const CANCELLED = "CANCELLED";
	public const CLOSED = "CLOSED";
    public const DELETED = "DELETED";
}
