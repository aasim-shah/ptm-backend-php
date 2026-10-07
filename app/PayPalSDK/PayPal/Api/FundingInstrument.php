<?php

namespace App\PayPalSDK\PayPal\Api;

use App\PayPalSDK\PayPal\Common\PayPalModel;

/**
 * Class FundingInstrument
 *
 * A resource representing a Payer's funding instrument. An instance of this schema is valid if and only if it is valid against exactly one of these supported properties
 *
 * @package PayPal\Api
 *
 * @property \App\PayPalSDK\PayPal\Api\CreditCard credit_card
 * @property \App\PayPalSDK\PayPal\Api\CreditCardToken credit_card_token
 * @property \App\PayPalSDK\PayPal\Api\Billing billing
 */
class FundingInstrument extends PayPalModel
{
    /**
     * Credit Card instrument.
     *
     * @param \App\PayPalSDK\PayPal\Api\CreditCard $credit_card
     *
     * @return $this
     */
    public function setCreditCard($credit_card)
    {
        $this->credit_card = $credit_card;
        return $this;
    }

    /**
     * Credit Card instrument.
     *
     * @return \App\PayPalSDK\PayPal\Api\CreditCard
     */
    public function getCreditCard()
    {
        return $this->credit_card;
    }

    /**
     * PayPal vaulted credit Card instrument.
     *
     * @param \App\PayPalSDK\PayPal\Api\CreditCardToken $credit_card_token
     *
     * @return $this
     */
    public function setCreditCardToken($credit_card_token)
    {
        $this->credit_card_token = $credit_card_token;
        return $this;
    }

    /**
     * PayPal vaulted credit Card instrument.
     *
     * @return \App\PayPalSDK\PayPal\Api\CreditCardToken
     */
    public function getCreditCardToken()
    {
        return $this->credit_card_token;
    }

    /**
     * Payment Card information.
     *
     * @param \App\PayPalSDK\PayPal\Api\PaymentCard $payment_card
     *
     * @return $this
     */
    public function setPaymentCard($payment_card)
    {
        $this->payment_card = $payment_card;
        return $this;
    }

    /**
     * Payment Card information.
     *
     * @return \App\PayPalSDK\PayPal\Api\PaymentCard
     */
    public function getPaymentCard()
    {
        return $this->payment_card;
    }

    /**
     * Bank Account information.
     * @param \App\PayPalSDK\PayPal\Api\ExtendedBankAccount $bank_account
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setBankAccount($bank_account)
    {
        $this->bank_account = $bank_account;
        return $this;
    }

    /**
     * Bank Account information.
     * @return \App\PayPalSDK\PayPal\Api\ExtendedBankAccount
     * @deprecated Not publicly available
     */
    public function getBankAccount()
    {
        return $this->bank_account;
    }

    /**
     * Vaulted bank account instrument.
     * @param \App\PayPalSDK\PayPal\Api\BankToken $bank_account_token
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setBankAccountToken($bank_account_token)
    {
        $this->bank_account_token = $bank_account_token;
        return $this;
    }

    /**
     * Vaulted bank account instrument.
     * @return \App\PayPalSDK\PayPal\Api\BankToken
     *@deprecated Not publicly available
     */
    public function getBankAccountToken()
    {
        return $this->bank_account_token;
    }

    /**
     * PayPal credit funding instrument.
     * @param \App\PayPalSDK\PayPal\Api\Credit $credit
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setCredit($credit)
    {
        $this->credit = $credit;
        return $this;
    }

    /**
     * PayPal credit funding instrument.
     * @return \App\PayPalSDK\PayPal\Api\Credit
     *@deprecated Not publicly available
     */
    public function getCredit()
    {
        return $this->credit;
    }

    /**
     * Incentive funding instrument.
     * @param \App\PayPalSDK\PayPal\Api\Incentive $incentive
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setIncentive($incentive)
    {
        $this->incentive = $incentive;
        return $this;
    }

    /**
     * Incentive funding instrument.
     * @return \App\PayPalSDK\PayPal\Api\Incentive
     *@deprecated Not publicly available
     */
    public function getIncentive()
    {
        return $this->incentive;
    }

    /**
     * External funding instrument.
     * @param \App\PayPalSDK\PayPal\Api\ExternalFunding $external_funding
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setExternalFunding($external_funding)
    {
        $this->external_funding = $external_funding;
        return $this;
    }

    /**
     * External funding instrument.
     * @return \App\PayPalSDK\PayPal\Api\ExternalFunding
     *@deprecated Not publicly available
     */
    public function getExternalFunding()
    {
        return $this->external_funding;
    }

    /**
     * Carrier account token instrument.
     * @param \App\PayPalSDK\PayPal\Api\CarrierAccountToken $carrier_account_token
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setCarrierAccountToken($carrier_account_token)
    {
        $this->carrier_account_token = $carrier_account_token;
        return $this;
    }

    /**
     * Carrier account token instrument.
     * @return \App\PayPalSDK\PayPal\Api\CarrierAccountToken
     *@deprecated Not publicly available
     */
    public function getCarrierAccountToken()
    {
        return $this->carrier_account_token;
    }

    /**
     * Carrier account instrument
     * @param \App\PayPalSDK\PayPal\Api\CarrierAccount $carrier_account
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setCarrierAccount($carrier_account)
    {
        $this->carrier_account = $carrier_account;
        return $this;
    }

    /**
     * Carrier account instrument
     * @return \App\PayPalSDK\PayPal\Api\CarrierAccount
     *@deprecated Not publicly available
     */
    public function getCarrierAccount()
    {
        return $this->carrier_account;
    }

    /**
     * Private Label Card funding instrument. These are store cards provided by merchants to drive business with value to customer with convenience and rewards.
     * @param \App\PayPalSDK\PayPal\Api\PrivateLabelCard $private_label_card
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setPrivateLabelCard($private_label_card)
    {
        $this->private_label_card = $private_label_card;
        return $this;
    }

    /**
     * Private Label Card funding instrument. These are store cards provided by merchants to drive business with value to customer with convenience and rewards.
     * @return \App\PayPalSDK\PayPal\Api\PrivateLabelCard
     *@deprecated Not publicly available
     */
    public function getPrivateLabelCard()
    {
        return $this->private_label_card;
    }

    /**
     * Billing instrument that references pre-approval information for the payment
     *
     * @param \App\PayPalSDK\PayPal\Api\Billing $billing
     *
     * @return $this
     */
    public function setBilling($billing)
    {
        $this->billing = $billing;
        return $this;
    }

    /**
     * Billing instrument that references pre-approval information for the payment
     *
     * @return \App\PayPalSDK\PayPal\Api\Billing
     */
    public function getBilling()
    {
        return $this->billing;
    }

    /**
     * Alternate Payment  information - Mostly regional payment providers. For e.g iDEAL in Netherlands
     *
     * @param \App\PayPalSDK\PayPal\Api\AlternatePayment $alternate_payment
     *
     * @return $this
     *@deprecated Not publicly available
     */
    public function setAlternatePayment($alternate_payment)
    {
        $this->alternate_payment = $alternate_payment;
        return $this;
    }

    /**
     * Alternate Payment  information - Mostly regional payment providers. For e.g iDEAL in Netherlands
     *
     * @return \App\PayPalSDK\PayPal\Api\AlternatePayment
     *@deprecated Not publicly available
     */
    public function getAlternatePayment()
    {
        return $this->alternate_payment;
    }

}
