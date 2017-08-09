<?php
namespace Api;

abstract class AccessType
{
	const NO_ACCESS = 0;
	const FULL_ACCESS = 1;
	const OWNER_ACCESS = 2; 
	const TOOL_OWNER_ACCESS = 3; // access for tool donator
	const GUEST_ACCESS = 4;
}