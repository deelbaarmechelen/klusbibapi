<?php
namespace Api;

abstract class AccessType
{
	public const NO_ACCESS = 0;
	public const FULL_ACCESS = 1;
	public const OWNER_ACCESS = 2; 
	public const TOOL_OWNER_ACCESS = 3; // access for tool donator
	public const GUEST_ACCESS = 4;
}