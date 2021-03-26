<?php
namespace Api\ModelMapper;

use \Api\Model\Delivery;

class DeliveryMapper
{
    static public function mapDeliveryToArray($delivery)
    {

        $deliveryItems = array();
        foreach ($delivery->items as $item) {
            array_push($deliveryItems, DeliveryMapper::mapDeliveryItemToArray($item));
        }
        $deliveryArray = array(
            "id" => $delivery->id,
            "reservation_id" => $delivery->reservation_id,
            "user_id" => $delivery->user_id,
            "state" => $delivery->state,
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
            "delivery_id" => $item->pivot->delivery_id,
            "inventory_item_id" => $item->pivot->inventory_item_id,
            "reservation_id" => $item->pivot->reservation_id,
            "fee" => $item->pivot->fee,
            "size" => $item->pivot->size,
            "comment" => $item->pivot->comment,
            "id" => $item->id,
            "item_type" => $item->item_type,
            "sku" => $item->sku,
            "name" => $item->name,
            "description" => $item->description,
            "keywords" => $item->keywords,
            "brand" => $item->brand,
            "is_active" => $item->is_active,
            "show_on_website" => $item->show_on_website,
        );
    }
}