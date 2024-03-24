<?php
namespace Api\Model;

abstract class DeliveryState
{
	public const REQUESTED = 'REQUESTED';
    public const CONFIRMED = 'CONFIRMED';
	public const DELIVERED = 'DELIVERED';
	public const CANCELLED = 'CANCELLED';
}
