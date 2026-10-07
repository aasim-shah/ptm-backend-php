<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Services\CountryService;

class CountryController extends Controller
{

    private CountryService $countryService;

    public function __construct(CountryService $countryService)
    {
        $this->countryService = $countryService;
    }

    public function statesAndCities($id){
        $statesAndCities = $this->countryService->statesAndCities($id);

        return response()->json([
            'status' => 200,
            'message' => 'State with cities',
            'token_type' => 'Bearer',
            'states_cities' => $statesAndCities,
            'token' => null
        ], 200);
    }

}