<?php
namespace Database\Factories;

use Api\Model\DeliveryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DeliveryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker = \Faker\Factory::create();
        return [
            'delivery_id' => 1,
            'inventory_item_id' => 1
        ];
    }

}