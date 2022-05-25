<?php
namespace Database\Factories;

use Api\Model\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InventoryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker = \Faker\Factory::create();
        return [
            'id' => $this->faker->numberBetween($min = 1000, $max = 9000),
            'name' => $this->faker->name,
            'item_type' => 'TOOL',
            'sku' => 'KB-000-20-001',
            'description' => null,
            'brand' => 'MyBrand',
            'is_active' => true,
            'show_on_website' => true,
            'is_reservable' => true
        ];
    }

}