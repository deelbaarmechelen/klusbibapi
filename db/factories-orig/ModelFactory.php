<?php

$faker = Faker\Factory::create();
$factory = new \Illuminate\Database\Eloquent\Factory($faker);

/*
 |--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Api\Model\User::class, function (Faker\Generator $faker) {
	static $password;

	return [
		'user_id' => $faker->numberBetween($min = 1000, $max = 9000),
	    'firstname' => $faker->name,
        'lastname' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'hash' => $password ?: $password = password_hash('secret', PASSWORD_BCRYPT)
	];
});

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Api\Model\Tool::class, function (Faker\Generator $faker) {
	static $password;

	return [
        'name' => $faker->name,
        'description' => 'description of this tool',
        'doc_url' => null,
        'manufacturer_url' => null,
        'category' => 'wood',
	];
});
$factory->define(Api\Model\InventoryItem::class, function (Faker\Generator $faker) {
    return [
        'id' => $faker->numberBetween($min = 1000, $max = 9000),
        'name' => $faker->name,
        'item_type' => 'TOOL',
        'sku' => 'KB-000-20-001',
        'description' => null,
        'brand' => 'MyBrand',
        'is_active' => true,
        'show_on_website' => true,
        'is_reservable' => true
    ];
});
$factory->define(Api\Model\Reservation::class, function (Faker\Generator $faker) {
    return [
        'reservation_id' => $faker->numberBetween($min = 1000, $max = 9000),
        'type' => 'Reservation',
        'state' => 'REQUESTED',
        'tool_id' => 1,
        'user_id' => 1
    ];
});

$factory->define(Api\Model\Delivery::class, function (Faker\Generator $faker) {
    return [
        'id' => $faker->numberBetween($min = 1000, $max = 9000),
        'user_id' => 1,
        'state' => 'REQUESTED',
    ];
});

$factory->define(Api\Model\DeliveryItem::class, function (Faker\Generator $faker) {
    return [
        'delivery_id' => 1,
        'inventory_item_id' => 1
    ];
});