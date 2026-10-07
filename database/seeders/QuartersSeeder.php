<?php

namespace Database\Seeders;

use App\Helpers\Utility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuartersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        DB::table('quarters')->delete();
        $states = array(
            array('id' => Utility::getUUID(),'name' => "Quarter 01"),
            array('id' => Utility::getUUID(),'name' => "Quarter 02"),
            array('id' => Utility::getUUID(),'name' => "Quarter 03"),
            array('id' => Utility::getUUID(),'name' => "Quarter 04"),

        );
        DB::table('quarters')->insert($states);
    }
}
