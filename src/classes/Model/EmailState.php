<?php
namespace Api\Model;

abstract class EmailState
{
	const CONFIRM_EMAIL = "CONFIRM_EMAIL";
	const CONFIRMED = "CONFIRMED";
	const BOUNCED = "BOUNCED";
}
