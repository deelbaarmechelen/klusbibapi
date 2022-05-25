<?php
namespace Database\Factories;

use Api\Model\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

class ToolFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tool::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker = \Faker\Factory::create();
        return [
            'name' => $this->faker->name,
            'description' => 'description of this tool',
            'doc_url' => null,
            'manufacturer_url' => null,
            'category' => 'wood',
        ];
    }

}