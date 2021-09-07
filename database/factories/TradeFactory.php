<?php

namespace Database\Factories;

use App\Models\Coin;
use App\Models\Offer;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Trade::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'ref' => $this->faker->randomNumber(8),
            'coin_id' => Coin::all()->random()['id'],
            'offer_id' => Offer::all()->random()['id'],
            'buyer_id' => User::all()->random()['id'],
            'seller_id' => User::all()->random()['id'],
            'amount_in_coin' => $this->faker->numberBetween(0.1, 10),
            'amount_in_ngn' => $this->faker->randomNumber(8),
            'amount_in_usd' => $this->faker->randomNumber(5),
            'fee_in_coin' => $this->faker->numberBetween(0.1, 10),
            'fee_in_ngn' => $this->faker->randomNumber(8),
            'fee_in_usd' => $this->faker->randomNumber(5),
            'status' => $this->faker->randomElement(['pending','cancelled','successful'])
        ];
    }
}
