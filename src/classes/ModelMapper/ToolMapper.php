<?php
namespace Api\ModelMapper;

use \Api\Model\Tool;
use Api\Model\ToolCategory;
use Api\Model\ToolState;

class ToolMapper
{
	static public function mapToolToArray($tool) {
		if ($tool->visible == 1) {
			$visible = true;
		} else {
			$visible = false;
		}
		$toolArray = array("tool_id" => $tool->tool_id,
			"name" => $tool->name,
			"description" => $tool->description,
			"code" => $tool->code,
			"owner_id" => $tool->owner_id,
			"reception_date" => $tool->reception_date,
			"category" => $tool->category,
			"brand" => $tool->brand,
			"type" => $tool->type,
			"serial" => $tool->serial,
			"manufacturing_year" => $tool->manufacturing_year,
			"manufacturer_url" => $tool->manufacturer_url,
			"doc_url" => $tool->doc_url,
			"img" => $tool->img,
			"replacement_value" => $tool->replacement_value,
			"experience_level" => $tool->experience_level,
			"safety_risk" => $tool->safety_risk,
			"state" => $tool->state,
			"visible" => $visible,
			"reservations" => array()
		);
		return $toolArray;
	}
	static public function mapArrayToTool($data, $tool) {
		if (isset($data["name"])) {
			$tool->name = $data["name"];
		}
		if (isset($data["description"])) {
			$tool->description = $data["description"];
		}
		if (isset($data["code"])) {
			$tool->code = $data["code"];
		}
		if (isset($data["owner_id"])) {
			$tool->owner_id = $data["owner_id"];
		}
		if (isset($data["reception_date"])) {
			$tool->reception_date = $data["reception_date"];
		}
		if (isset($data["category"])) {
			$tool->category = $data["category"];
		}
		if (isset($data["brand"])) {
			$tool->brand = $data["brand"];
		}
		if (isset($data["type"])) {
			$tool->type = $data["type"];
		}
		if (isset($data["serial"])) {
			$tool->serial = $data["serial"];
		}
		if (isset($data["manufacturing_year"])) {
			$tool->manufacturing_year = $data["manufacturing_year"];
		}
		if (isset($data["manufacturer_url"])) {
			$tool->manufacturer_url = $data["manufacturer_url"];
		}
		if (isset($data["img"])) {
			$tool->img = $data["img"];
		}
		if (isset($data["doc_url"])) {
			$tool->doc_url = $data["doc_url"];
		}
		if (isset($data["replacement_value"])) {
			$tool->replacement_value = $data["replacement_value"];
		}
		if (isset($data["experience_level"])) {
			$tool->experience_level = $data["experience_level"];
		}
		if (isset($data["safety_risk"])) {
			$tool->safety_risk = $data["safety_risk"];
		}
		if (isset($data["state"])) {
			$tool->state = $data["state"];
		}
		if (isset($data["visible"])) {
			if ($data["visible"] == true) {
				$tool->visible = 1;
			} else {
				$tool->visible = 0;
			}
		}
		
	}

    static public function mapAssetToTool($asset) {
	    $tool = new Tool();
        $tool->tool_id = $asset->id;
        $tool->tool_ext_id = $asset->id;
        $tool->name = !empty($asset->name) ? html_entity_decode ($asset->name) : html_entity_decode ($asset->category->name);
        $tool->description = $asset->notes;
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
        $tool->state = self::mapAssertStateToToolState($asset);
        $tool->visible = self::isVisible($asset);
        //$tool->reservations ???
	    return $tool;
    }

    protected static function isVisible($asset) {
        if ($asset->status_label->status_type == "archived") {
            return false;
        }
	    // no visibility info in asset -> all tools visible
	    return true;
    }

    protected static function mapAssertStateToToolState($asset) {
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
//    {
//        "tool_id": null,
//        "name": "",
//        "description": null,
//        "code": "KB-000-17-002",
//        "owner_id": null,
//        "reception_date": null,
//        "category": "Bouw",
//        "brand": null,
//        "type": null,
//        "serial": null,
//        "manufacturing_year": null,
//        "manufacturer_url": null,
//        "doc_url": null,
//        "img": null,
//        "replacement_value": null,
//        "experience_level": null,
//        "safety_risk": null,
//        "state": null,
//        "visible": false,
//        "reservations": []
//    },