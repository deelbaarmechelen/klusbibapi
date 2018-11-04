<?php
namespace Api\Model;

abstract class UserState
{
	const CONFIRM_EMAIL = "CONFIRM_EMAIL";
	const ACTIVE = "ACTIVE";
	const DISABLED = "DISABLED";
	const CHECK_PAYMENT = "CHECK_PAYMENT";
	const DELETED = "DELETED";
	const EXPIRED = "EXPIRED";
}
