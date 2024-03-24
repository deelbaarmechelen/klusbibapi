<?php
namespace Api\Model;

abstract class MembershipState
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_EXPIRED = 'EXPIRED';
}

