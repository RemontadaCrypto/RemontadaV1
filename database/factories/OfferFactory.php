<?php

namespace Database\Factories;

use App\Models\Coin;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Offer::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::all()->random()['id'],
            'coin_id' => Coin::all()->random()['id'],
            'type' => $this->faker->randomElement(['naira', 'dollar']),
            'rate' => $this->faker->numberBetween(400, 500),
            'min' => $this->faker->numberBetween(100, 500),
            'max' => $this->faker->numberBetween(600, 5000),
        ];
    }
}
