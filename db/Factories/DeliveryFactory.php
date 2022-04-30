<?php

namespace Database\Factories;

use Api\Model\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Delivery::class;

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
            'user_id' => 1,
            'state' => 'REQUESTED',
        ];
    }

}