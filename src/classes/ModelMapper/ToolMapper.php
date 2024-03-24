<?php
namespace Api\ModelMapper;

use Api\Model\Tool;
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
		$toolArray = [
			"tool_id" => $tool->tool_id,
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
            "fee" => isset($tool->inventoryItem) ? $tool->inventoryItem->loan_fee : null,
            "size" => isset($tool->inventoryItem) ? $tool->inventoryItem->size : null,
            "deliverable" => isset($tool->inventoryItem) ? $tool->inventoryItem->deliverable : null,
			"reservations" => []
		];
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

    static public function mapAccessoryToArray($toolAccessory)
    {
        if ($toolAccessory->visible == 1) {
            $visible = true;
        } else {
            $visible = false;
        }
        $accessoryArray = [
			"accessory_id" => $toolAccessory->accessory_id, 
			"name" => $toolAccessory->name, "visible" => $visible
		];
		return $accessoryArray;
   }
}