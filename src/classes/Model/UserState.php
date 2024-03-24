<?php
namespace Api\Model;

abstract class UserState
{
    public const CHECK_PAYMENT = "CHECK_PAYMENT";
	public const ACTIVE = "ACTIVE";
	public const DISABLED = "DISABLED";
	public const DELETED = "DELETED";
	public const EXPIRED = "EXPIRED";
}
