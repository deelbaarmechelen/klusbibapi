<?php
namespace Database\Factories;

use Api\Model\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker = \Faker\Factory::create();
        return [
            'reservation_id' => $this->faker->numberBetween($min = 1000, $max = 9000),
            'type' => 'Reservation',
            'state' => 'REQUESTED',
            'tool_id' => 1,
            'user_id' => 1
        ];
    }

}