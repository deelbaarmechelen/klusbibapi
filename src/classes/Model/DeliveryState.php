<?php
namespace Api\Model;

abstract class DeliveryState
{
	const REQUESTED = 'REQUESTED';
	const DELIVERED = 'DELIVERED';
	const CANCELLED = 'CANCELLED';
}
