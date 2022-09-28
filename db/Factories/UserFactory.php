<?php
namespace Database\Factories;

use Api\Model\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $password;
//        $this->faker->addProvider($provider);
        $this->faker = \Faker\Factory::create();
        return [
            'id' => $this->faker->numberBetween($min = 1000, $max = 9000),
            'first_name' => $this->faker->name,
            'last_name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'hash' => $password ?: $password = password_hash('secret', PASSWORD_BCRYPT)
        ];
    }

}