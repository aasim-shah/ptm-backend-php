<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(){
        $this->call(\Database\Seeders\CountriesSeeder::class);
        $this->call(\Database\Seeders\StatesSeeder::class);
        $this->call(\Database\Seeders\CitiesSeeder::class);
        $this->call(\Database\Seeders\QuartersSeeder::class);
    }
}
