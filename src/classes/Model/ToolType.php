<?php

namespace Api\Model;


class ToolType
{
    const TOOL = "TOOL";
    const ACCESSORY = "ACCESSORY";

    public static function isValid($toolType) {
        return ($toolType == self::TOOL
            || $toolType == self::ACCESSORY);
    }
}