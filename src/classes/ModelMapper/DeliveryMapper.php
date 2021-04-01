<?php
namespace Api\ModelMapper;

use \Api\Model\Delivery;

class DeliveryMapper
{
    static public function mapDeliveryToArray($delivery)
    {

        $deliveryItems = array();
        foreach ($delivery->deliveryItems as $item) {
            array_push($deliveryItems, DeliveryMapper::mapDeliveryItemToArray($item));
        }
        $deliveryArray = array(
            "id" => $delivery->id,
            "reservation_id" => $delivery->reservation_id,
            "user_id" => $delivery->user_id,
            "state" => $delivery->state,
            "type" => $delivery->type,
            "pick_up_address" => $delivery->pick_up_address,
            "drop_off_address" => $delivery->drop_off_address,
            "pick_up_date" => $delivery->pick_up_date,
            "drop_off_date" => $delivery->drop_off_date,
            "consumers" => $delivery->consumers,
            "comment" => $delivery->comment,
            "price" => $delivery->price,
            "payment_id" => $delivery->payment_id,
            "items" => $deliveryItems
        );

        return $deliveryArray;
    }

    static public function mapDeliveryItemToArray($item)
    {
        return array(
            "delivery_id" => $item->delivery_id,
            "inventory_item_id" => $item->inventory_item_id,
            "reservation_id" => $item->reservation_id,
            "lending_id" => $item->lending_id,
            "comment" => $item->comment,
            "id" => (isset($item->inventoryItem)) ? $item->inventoryItem->id : null,
            "item_type" => (isset($item->inventoryItem)) ? $item->inventoryItem->item_type : null,
            "sku" => (isset($item->inventoryItem)) ? $item->inventoryItem->sku : null,
            "name" => (isset($item->inventoryItem)) ? $item->inventoryItem->name : null,
            "description" => (isset($item->inventoryItem)) ? $item->inventoryItem->description : null,
            "keywords" => (isset($item->inventoryItem)) ? $item->inventoryItem->keywords : null,
            "brand" =>(isset($item->inventoryItem)) ?  $item->inventoryItem->brand : null,
            "is_active" => (isset($item->inventoryItem)) ? $item->inventoryItem->is_active : null,
            "show_on_website" => (isset($item->inventoryItem)) ? $item->inventoryItem->show_on_website : null,
            "fee" => (isset($item->inventoryItem)) ? $item->inventoryItem->loan_fee : null,
            "size" => (isset($item->inventoryItem)) ? $item->inventoryItem->size : null,
            "deliverable" => (isset($item->inventoryItem)) ? $item->inventoryItem->deliverable : null,
            "safety_risk" => (isset($item->inventoryItem)) ? $item->inventoryItem->safety_risk : null,
            "experience_level" => (isset($item->inventoryItem)) ? $item->inventoryItem->experience_level : null

//            "delivery_id" => $item->pivot->delivery_id,
//            "inventory_item_id" => $item->pivot->inventory_item_id,
//            "reservation_id" => $item->pivot->reservation_id,
//            "fee" => $item->pivot->fee,
//            "size" => $item->pivot->size,
//            "comment" => $item->pivot->comment,
//            "id" => $item->id,
//            "item_type" => $item->item_type,
//            "sku" => $item->sku,
//            "name" => $item->name,
//            "description" => $item->description,
//            "keywords" => $item->keywords,
//            "brand" => $item->brand,
//            "is_active" => $item->is_active,
//            "show_on_website" => $item->show_on_website,
        );
    }
}