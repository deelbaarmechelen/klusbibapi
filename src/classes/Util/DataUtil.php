<?php
namespace Api\Util;

class DataUtil
{
	function getSampleReservations() {
		$reservations = array();
		$startdate = new DateTime();
		$enddate = clone $startdate;
		$enddate->add(new DateInterval('P7D'));
		$startdate2 = new DateTime();
		$startdate2->add(new DateInterval('P14D'));
		$enddate2 = clone $startdate2;
		$enddate2->add(new DateInterval('P7D'));
	
		// supported colours:
		// 	"darkblue":"#00008b","darkcyan":"#008b8b","darkgoldenrod":"#b8860b","darkgray":"#a9a9a9","darkgreen":"#006400","darkkhaki":"#bdb76b","darkmagenta":"#8b008b","darkolivegreen":"#556b2f",
		// 	"darkorange":"#ff8c00","darkorchid":"#9932cc","darkred":"#8b0000","darksalmon":"#e9967a","darkseagreen":"#8fbc8f","darkslateblue":"#483d8b","darkslategray":"#2f4f4f","darkturquoise":"#00ced1",
		// 	"darkviolet":"#9400d3"
	
		$reservations = array(
				array(
						"id" => "tool1-reservation1",
						"title" => "My Reservation",
						"color" => "yellow",
						"startsAt" => $startdate->format('Y-m-d'),
						"endsAt" => $enddate->format('Y-m-d'),
						"draggable" => true,
						"resizable" => true,
						"actions" => "actions"
				),
				array(
						"id" => "tool1-reservation2",
						"title" => "My Second Reservation",
						"color" => "red",
						"startsAt" => $startdate2->format('Y-m-d'),
						"endsAt" => $enddate2->format('Y-m-d'),
						"draggable" => true,
						"resizable" => true,
						"actions" => "actions"
				)
		);
		return $reservations;
	}
	
}
