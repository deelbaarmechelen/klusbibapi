<?php

namespace Api\Model;


class ToolType
{
    public const TOOL = "TOOL";
    public const ACCESSORY = "ACCESSORY";
    public const LOAN = "loan"; // tools and accessories
    public const STOCK = "stock"; // consumable
    public const KIT = "kit";
    public const SERVICE = "service";

    public static function isValid($toolType) {
        return ($toolType == self::TOOL
            || $toolType == self::ACCESSORY);
    }
}