<?php
namespace Api\Model;

abstract class UserState
{
    const CHECK_PAYMENT = "CHECK_PAYMENT";
	const ACTIVE = "ACTIVE";
	const DISABLED = "DISABLED";
	const DELETED = "DELETED";
	const EXPIRED = "EXPIRED";
}
