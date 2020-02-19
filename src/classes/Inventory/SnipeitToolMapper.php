<?php

namespace Api\Inventory;


use Api\Model\Accessory;
use Api\Model\Tool;
use Api\Model\ToolCategory;
use Api\Model\ToolState;

abstract class SnipeitToolMapper
{
    /**
     * @param $asset asset to be converted to a tool
     * @return Tool the converted tool
     */
    static public function mapAssetToTool($asset) : Tool {
        $tool = new Tool();
        $tool->tool_id = $asset->id;
        $tool->tool_ext_id = $asset->id;
        $tool->name = !empty($asset->name) ? html_entity_decode ($asset->name) : html_entity_decode ($asset->category->name);
//        $tool->description = $asset->notes;
        $tool->code = $asset->asset_tag;
//        $tool->owner_id = $data["owner_id"]; // FIXME: should match supplier??
//        $tool->reception_date = $data["reception_date"];
        $tool->category = self::mapAssetCategoryToToolCategory($asset);
        $tool->brand = html_entity_decode ($asset->manufacturer->name);
        $tool->type = $asset->model_number;
        $tool->serial = $asset->serial;
//        $tool->manufacturing_year = $data["manufacturing_year"];
//        $tool->manufacturer_url = $data["manufacturer_url"];
        $tool->img = $asset->image;
        if (isset($asset->custom_fields) && !empty($asset->custom_fields)) {
            $tool->replacement_value = $asset->custom_fields->replacement_value->value; // FIXME: derive from depreciation rules?
            $tool->experience_level = $asset->custom_fields->experience_level->value;
            $tool->safety_risk = $asset->custom_fields->safety_risk->value;
            // FIXME: not accessible without login!
            $tool->doc_url = $asset->custom_fields->doc_url->value;
        }
        $tool->state = self::mapAssetStateToToolState($asset);
        $tool->visible = self::isVisible($asset);
        //$tool->reservations ???
        return $tool;
    }
    /**
     * @param $snipeAccessory asset to be converted to a tool
     * @return Tool the converted tool
     */
    static public function mapSnipeAccessoryToAccessory($snipeAccessory) : ?Accessory {
        if ($snipeAccessory == null || $snipeAccessory->id == null) {
            return null;
        }
        $accessory = new Accessory();
        $accessory->accessory_id = $snipeAccessory->id;
        $accessory->name = !empty($snipeAccessory->name) ? html_entity_decode ($snipeAccessory->name) : html_entity_decode ($snipeAccessory->category->name);
        $accessory->description = $snipeAccessory->notes;
//        $accessory->category = self::mapAssetCategoryToToolCategory($snipeAccessory); // from response: category":{"id":3,"name":"Handgereedschap"},
        $accessory->brand = html_entity_decode ($snipeAccessory->manufacturer->name);
        $accessory->state = self::mapAccessoryStateToToolState($snipeAccessory);
        $accessory->img = $snipeAccessory->image;
        $accessory->visible = true;
        return $accessory;
    }

    protected static function isVisible($asset) {
        // FIXME: Undefined property: stdClass::$status_label in <b>/app/src/classes/Inventory/SnipeitToolMapper.php</b> on line <b>65</b><br />
        if ($asset->status_label->status_type == "archived") {
            return false;
        }
        if ($asset->status_label->status_type == "pending") {
            return true;
        }
        if ($asset->status_label->status_type == "deployable") {
            return true;
        }
        // no visibility info in asset -> tools not visible by default
        return false;
    }

    protected static function mapAssetStateToToolState($asset) {
        $status_type = $asset->status_label->status_type;
        $status_meta = $asset->status_label->status_meta;
        if ($status_type == "deployable") {
            if ($status_meta == "deployed") {
                return ToolState::IN_USE;
            }
            return ToolState::READY;
        } else if ($status_type == "archived") {
            return ToolState::DISPOSED;
        } else if ($status_type == "pending") {
            return ToolState::MAINTENANCE;
        }
        return $asset->status_label->status_type;
    }

    public static function mapToolStateToAssetState(string $toolState) : ?AssetState {
        if ($toolState == ToolState::READY) {
            return AssetState::readyToDeploy();
        } elseif ($toolState == ToolState::IN_USE) {
            return AssetState::deployed();
        } elseif ($toolState == ToolState::MAINTENANCE) {
            return AssetState::maintenanceRepair();
        } elseif ($toolState == ToolState::DISPOSED) {
            return AssetState::archived();
        } elseif ($toolState == ToolState::NEW) {
            return AssetState::undeployable(); // FIXME: what's the new state? Should we remove it?
        }
        return null;
    }

    protected static function mapAccessoryStateToToolState($accessory) {
        if ($accessory->qty > 0) {
            if ($accessory->remaining_qty == 0) {
                return ToolState::IN_USE;
            }
            return ToolState::READY;
        } else {
            return ToolState::DISPOSED;
        }
    }
        /**
     * @param $asset
     * @return mixed
     */
    protected static function mapAssetCategoryToToolCategory($asset)
    {
        if (isset($asset->custom_fields) && isset($asset->custom_fields->group)
            && isset($asset->custom_fields->group->value)) {
            if (in_array(strtolower($asset->custom_fields->group->value),
                array(ToolCategory::CONSTRUCTION,
                    ToolCategory::CAR,
                    ToolCategory::GARDEN,
                    ToolCategory::GENERAL,
                    ToolCategory::TECHNICS,
                    ToolCategory::WOOD)))
                return strtolower($asset->custom_fields->group->value);
        }
        $assetCategory = strtolower($asset->category->name);
        if ($assetCategory == 'boorhamer'
            || $assetCategory == 'klopboormachine'
            || $assetCategory == 'slijpmolen'
            || $assetCategory == 'slijpschijf'
            || $assetCategory == 'tegelsnijder'
            || $assetCategory == 'accu boormachine'
            || $assetCategory == 'boormachine') {
            return ToolCategory::CONSTRUCTION;
        }
        if ($assetCategory == 'sneeuwketting'
            || $assetCategory == 'hydrolische krik') {
            return ToolCategory::CAR;
        }
        if ($assetCategory == 'bladblazer'
            || $assetCategory == 'steekspade'
            || $assetCategory == 'kettingzaag'
            || $assetCategory == 'snoeischaar'
            || $assetCategory == 'kantenmaaier'
            || $assetCategory == 'bosmaaier'
            || $assetCategory == 'boomzaag'
            || $assetCategory == 'hogedrukreiniger'
            || $assetCategory == 'tuinslang'
            || $assetCategory == 'heggenschaar'
            || $assetCategory == 'bosmaaier+haagschaar+boomzaag'
            || $assetCategory == 'haagschaar') {
            return ToolCategory::GARDEN;
        }
        if ($assetCategory == 'soldeerbout'
            || $assetCategory == 'soldeerbout') {
            return ToolCategory::TECHNICS;
        }
        if ($assetCategory == 'cirkelzaag'
            || $assetCategory == 'decoupeerzaag'
            || $assetCategory == 'bovenfrees'
            || $assetCategory == 'wipzaag'
            || $assetCategory == 'houtschaafmachine'
            || $assetCategory == 'bandschuurmachine'
            || $assetCategory == 'vlakschuurmachine'
            || $assetCategory == 'puntschuurmachine'
            || $assetCategory == 'multischuurmachine'
            || $assetCategory == 'deltaschuurmachine'
            || $assetCategory == 'afkortzaag'
            || $assetCategory == 'krokodilzaag') {
            return ToolCategory::WOOD;
        }

        return ToolCategory::GENERAL;
    }
}