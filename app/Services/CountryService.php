<?php

namespace App\Services;

use App\Repositories\CountryRepo;

class CountryService
{

    private CountryRepo $countryRepo;

    public function __construct(CountryRepo $countryRepo)
    {
        $this->countryRepo = $countryRepo;
    }

    public function statesAndCities($id){
        return $this->countryRepo->statesAndCities($id);
    }


}