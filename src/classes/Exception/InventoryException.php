<?php

namespace Api\Exception;


class InventoryException extends \RuntimeException
{
    const USER_NOT_FOUND = 1;
    const INVALID_USER = 2;

}