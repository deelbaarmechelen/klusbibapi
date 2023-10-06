<?php

namespace Api\Model;


class ToolType
{
    const TOOL = "TOOL";
    const ACCESSORY = "ACCESSORY";
    const LOAN = "loan"; // tools and accessories
    const STOCK = "stock"; // consumable
    const KIT = "kit";
    const SERVICE = "service";

    public static function isValid($toolType) {
        return ($toolType == self::TOOL
            || $toolType == self::ACCESSORY);
    }
}