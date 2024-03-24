<?php

namespace Api\Exception;


class InventoryException extends \RuntimeException
{
    public const USER_NOT_FOUND = 1;
    public const INVALID_USER = 2;

}