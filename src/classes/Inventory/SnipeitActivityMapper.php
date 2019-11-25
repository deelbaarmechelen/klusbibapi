<?php

namespace Api\Inventory;


use Api\Model\Lending;

abstract class SnipeitActivityMapper
{
    /**
     * @param $activity activity to be converted to a lending
     * @return Lending the converted lending
     */
    static public function mapActivityToLending($activity, $checkins): Lending
    {
        $lending = new Lending();
        $lending->lending_id = $activity->id;
        $lending->start_date = $activity->created_at->datetime; //checkout_date;
//        $lending->due_date = expected_checkin_date ??;
//        $lending->returned_date = checkin_date ??;
        $lending->product = $activity->item;
        $lending->user = $activity->target;
//        $lending->extra_info;
        $lending->comments = $activity->note;
//        $lending->active;
        $lending->created_at = $activity->created_at->datetime;
        $lending->updated_at = $activity->updated_at->datetime;

        return $lending;
    }
}
