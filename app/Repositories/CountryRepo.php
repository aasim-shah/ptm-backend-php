<?php

namespace App\Repositories;

use App\Models\State;

class CountryRepo
{

    public function statesAndCities($id){
        return State::where('country_id',$id)->with('cities');
    }

}