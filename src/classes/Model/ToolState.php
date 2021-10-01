<?php
namespace Api\Model;

abstract class ToolState
{
    const NEW = "NEW";
    const DISPOSED = "DISPOSED";
    const READY = "READY";
    const RESERVED = "RESERVED";
    const IN_USE = "IN_USE";
    const MAINTENANCE = "MAINTENANCE";
}
