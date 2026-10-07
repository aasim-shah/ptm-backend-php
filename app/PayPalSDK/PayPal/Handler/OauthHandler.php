<?php
/**
 * API handler for OAuth Token Request REST API calls
 */

namespace App\PayPalSDK\PayPal\Handler;

use App\PayPalSDK\PayPal\Common\PayPalUserAgent;
use App\PayPalSDK\PayPal\Core\PayPalConstants;
use App\PayPalSDK\PayPal\Core\PayPalHttpConfig;
use App\PayPalSDK\PayPal\Exception\PayPalConfigurationException;
use App\PayPalSDK\PayPal\Exception\PayPalInvalidCredentialException;
use App\PayPalSDK\PayPal\Exception\PayPalMissingCredentialException;

/**
 * Class OauthHandler
 */
class OauthHandler implements IPayPalHandler
{
    /**
     * Private Variable
     *
     * @var \PayPalSDK\PayPal\Rest\ApiContext $apiContext
     */
    private $apiContext;

    /**
     * Construct
     *
     * @param \PayPalSDK\PayPal\Rest\ApiContext $apiContext
     */
    public function __construct($apiContext)
    {
        $this->apiContext = $apiContext;
    }

    /**
     * @param PayPalHttpConfig $httpConfig
     * @param string                    $request
     * @param mixed                     $options
     * @return mixed|void
     * @throws PayPalConfigurationException
     * @throws PayPalInvalidCredentialException
     * @throws PayPalMissingCredentialException
     */
    public function handle($httpConfig, $request, $options)
    {
        $config = $this->apiContext->getConfig();

        $httpConfig->setUrl(
            rtrim(trim($this->_getEndpoint($config)), '/') .
            (isset($options['path']) ? $options['path'] : '')
        );

        $headers = array(
            "User-Agent"    => PayPalUserAgent::getValue(PayPalConstants::SDK_NAME, PayPalConstants::SDK_VERSION),
            "Authorization" => "Basic " . base64_encode($options['clientId'] . ":" . $options['clientSecret']),
            "Accept"        => "*/*"
        );
        $httpConfig->setHeaders($headers);

        // Add any additional Headers that they may have provided
        $headers = $this->apiContext->getRequestHeaders();
        foreach ($headers as $key => $value) {
            $httpConfig->addHeader($key, $value);
        }
    }

    /**
     * Get HttpConfiguration object for OAuth API
     *
     * @param array $config
     *
     * @return PayPalHttpConfig
     * @throws \App\PayPalSDK\PayPal\Exception\PayPalConfigurationException
     */
    private static function _getEndpoint($config)
    {

        if(config('environment.SANDBOX')){
            $baseEndpoint = PayPalConstants::REST_SANDBOX_ENDPOINT;
        }else{
            $baseEndpoint = PayPalConstants::REST_LIVE_ENDPOINT;
        }

        return rtrim(trim($baseEndpoint), '/') . "/v1/oauth2/token";
    }
}
