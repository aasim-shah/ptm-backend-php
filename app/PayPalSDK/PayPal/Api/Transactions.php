<?php

namespace App\PayPalSDK\PayPal\Api;

use App\PayPalSDK\PayPal\Common\PayPalModel;

/**
 * Class Transactions
 *
 * 
 *
 * @package PayPal\Api
 *
 * @property \App\PayPalSDK\PayPal\Api\Amount amount
 */
class Transactions extends PayPalModel
{
    /**
     * Amount being collected.
     * 
     *
     * @param \App\PayPalSDK\PayPal\Api\Amount $amount
     * 
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Amount being collected.
     *
     * @return \App\PayPalSDK\PayPal\Api\Amount
     */
    public function getAmount()
    {
        return $this->amount;
    }

}
