<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        User::factory(20)->create();
        Offer::factory(100)->create();
        Trade::factory(100)->create();
    }
}
