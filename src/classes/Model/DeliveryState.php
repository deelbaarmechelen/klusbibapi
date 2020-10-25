<?php
namespace Api\Model;

abstract class DeliveryState
{
	const REQUESTED = 'REQUESTED';
    const CONFIRMED = 'CONFIRMED';
	const DELIVERED = 'DELIVERED';
	const CANCELLED = 'CANCELLED';
}
