<?php
namespace Api\Model;

abstract class EmailState
{
	public const CONFIRM_EMAIL = "CONFIRM_EMAIL";
	public const CONFIRMED = "CONFIRMED";
	public const BOUNCED = "BOUNCED";
}
