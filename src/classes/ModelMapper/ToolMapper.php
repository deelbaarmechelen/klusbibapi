<?php
namespace Api\ModelMapper;

use \Api\Model\Tool;

class ToolMapper
{
	static public function mapToolToArray($tool) {

		$toolArray = array("tool_id" => $tool->tool_id,
			"name" => $tool->name,
			"description" => $tool->description,
			"link" => $tool->link,
			"category" => $tool->category,
			"reservations" => array()
		);
		return $toolArray;
	}
}
