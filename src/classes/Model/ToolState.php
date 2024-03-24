<?php
namespace Api\Model;

abstract class ToolState
{
    public const NEW = "NEW";
    public const DISPOSED = "DISPOSED";
    public const READY = "READY";
    public const RESERVED = "RESERVED";
    public const IN_USE = "IN_USE";
    public const MAINTENANCE = "MAINTENANCE";
}
