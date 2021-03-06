<?php

namespace Api\Inventory;


use Api\Model\Accessory;
use Api\Model\InventoryItem;
use Api\Model\Tool;
use Api\Model\ToolCategory;
use Api\Model\ToolState;
use Api\Model\ToolType;

abstract class SnipeitToolMapper
{

    const ACCESSORY_OFFSET = 100000;

    static public function mapAssetToItem($asset) : ?InventoryItem  {
        $item = new InventoryItem();
        $item->id = $asset->id;
        $item->name = !empty($asset->name) ? html_entity_decode ($asset->name) : html_entity_decode ($asset->category->name);
        $item->item_type = ToolType::TOOL;
        $item->created_by =  null;//')->unsigned()->nullable()->default(null);
        $item->assigned_to =  null;//')->unsigned()->nullable()->default(null);
        $item->current_location_id =  null;//')->unsigned()->nullable()->default(null);
        $item->item_condition = null;//')->unsigned()->nullable()->default(null);
        $item->sku = $asset->asset_tag;
        $item->description =  null;//', 1024)->nullable()->default(null);
        $item->keywords =  self::mapAssetCategoryToToolCategory($asset);
        $item->brand =  html_entity_decode ($asset->manufacturer->name);//', 1024)->nullable()->default(null);
        $item->care_information =  null;//', 1024)->nullable()->default(null); - full description - shown online
        $item->component_information =  null;//', 1024)->nullable()->default(null);
        $item->loan_fee =  null;//', 10,2)->nullable()->default(null);
        $item->max_loan_days =  null;//')->unsigned()->nullable()->default(null);
        $item->is_active = true; // FIXME: check value based on state?
        $item->show_on_website =  self::isVisible($asset);
        $item->serial = $asset->serial;
        $item->note = $asset->notes; // short description admin
        $item->price_cost =  null;//', 10,2)->nullable()->default(null);
        $item->price_sell =  null;//', 10,2)->nullable()->default(null);
        $item->image_name =  null;//', 255)->nullable()->default(null);
        $item->short_url =  null;//', 64)->nullable()->default(null);
        $item->item_sector =  null;//')->unsigned()->nullable()->default(null);
        $item->is_reservable = true; // FIXME: check value based on state?
        $item->deposit_amount =  null;//', 10,2)->nullable()->default(null);
        $item->donated_by = null; //')->unsigned()->nullable()->default(null);
        $item->owned_by = null; //')->unsigned()->nullable()->default(null);

        // TODO: state, description, image, category
        return $item;
    }

    static public function mapAccessoryToItem($accessory) : ?InventoryItem  {
        $item = new InventoryItem();
        $item->id = $accessory->id + self::ACCESSORY_OFFSET;
        $item->name = !empty($accessory->name) ? html_entity_decode ($accessory->name) : html_entity_decode ($accessory->category->name);
        $item->item_type = ToolType::ACCESSORY;
        $item->created_by =  null;//')->unsigned()->nullable()->default(null);
        $item->assigned_to =  null;//')->unsigned()->nullable()->default(null);
        $item->current_location_id =  null;//')->unsigned()->nullable()->default(null);
        $item->item_condition = null;//')->unsigned()->nullable()->default(null);
        $item->sku = $item->name;
        $item->description = null; // full description - shown online
        $item->keywords = null; // self::mapAssetCategoryToToolCategory($accessory);
        $item->brand =  isset($accessory->manufacturer) ? html_entity_decode ($accessory->manufacturer->name) : null;//', 1024)->nullable()->default(null);
        $item->care_information =  null;//', 1024)->nullable()->default(null);
        $item->component_information =  null;//', 1024)->nullable()->default(null);
        $item->loan_fee =  null;//', 10,2)->nullable()->default(null);
        $item->max_loan_days =  null;//')->unsigned()->nullable()->default(null);
        $item->is_active = true; // FIXME: check value based on state?
        $item->show_on_website = true;
        $item->serial = null;
        $item->note = $accessory->notes; // short description admin
        $item->price_cost = null;//', 10,2)->nullable()->default(null);
        $item->price_sell = null;//', 10,2)->nullable()->default(null);
        $item->image_name = null;//', 255)->nullable()->default(null);
        $item->short_url =  null;//', 64)->nullable()->default(null);
        $item->item_sector =  null;//')->unsigned()->nullable()->default(null);
        $item->is_reservable = true; // FIXME: check value based on state?
        $item->deposit_amount = null;//', 10,2)->nullable()->default(null);
        $item->donated_by = null; //')->unsigned()->nullable()->default(null);
        $item->owned_by = null; //')->unsigned()->nullable()->default(null);
        // TODO: state, image, category
        return $item;
    }

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
            $tool->replacement_value = isset($asset->custom_fields->replacement_value) ? $asset->custom_fields->replacement_value->value : null; // FIXME: derive from depreciation rules?
            $tool->experience_level = isset($asset->custom_fields->experience_level) ? $asset->custom_fields->experience_level->value : null;
            $tool->safety_risk = isset($asset->custom_fields->safety_risk) ? $asset->custom_fields->safety_risk->value : null;
            // FIXME: not accessible without login!
            $tool->doc_url = isset($asset->custom_fields->doc_url) ? $asset->custom_fields->doc_url->value : null;
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
        $accessory->brand = !empty($snipeAccessory->manufacturer) ? html_entity_decode ($snipeAccessory->manufacturer->name) : "";
        $accessory->state = self::mapAccessoryStateToToolState($snipeAccessory);
        $accessory->img = $snipeAccessory->image;
        $accessory->visible = true;
        return $accessory;
    }

    protected static function isVisible($asset) {
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