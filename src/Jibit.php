<?php

namespace Vahidkaargar\Jibit;


use Exception;

class Jibit
{
    const BASE_URL = 'https://napi.jibit.ir/ppg/v3';
    public $accessToken;
    private $apiKey;
    private $secretKey;
    private $cache;

    public function __construct($apiKey, $secretKey)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->cache = new Cache();
    }

    /**
     * @param int $amount
     * @param string $referenceNumber
     * @param string $userIdentifier
     * @param string $callbackUrl
     * @param string $currency
     * @param null $description
     * @param $additionalData
     * @return bool|mixed|string
     * @throws Exception
     */
    public function paymentRequest($amount, $referenceNumber, $userIdentifier, $callbackUrl, $currency = 'IRR', $description = null, $additionalData = null)
    {
        $this->generateToken();
        $data = [
            'additionalData' => $additionalData,
            'amount' => $amount,
            'callbackUrl' => $callbackUrl,
            'clientReferenceNumber' => $referenceNumber,
            'currency' => $currency,
            'userIdentifier' => $userIdentifier,
            'description' => $description,
        ];
        return $this->callCurl('/purchases', $data, true);
    }

    /**
     * @param $id
     * @return bool|mixed|string
     * @throws Exception
     */
    public function getOrderById($id)
    {
        return $this->callCurl('/purchases?purchaseId=' . $id, [], true, 0, 'GET');

    }

    /**
     * @param bool $isForce
     * @return string
     * @throws Exception
     */
    private function generateToken($isForce = false)
    {
        $this->cache->eraseExpired();

        if ($isForce === false && $this->cache->isCached('accessToken')) {
            $this->setAccessToken($this->cache->retrieve('accessToken'));
        } else if ($this->cache->isCached('refreshToken')) {
            $refreshToken = $this->refreshTokens();
            if ($refreshToken !== 'ok') {
                $this->generateNewToken();
            }
        } else {
            $this->generateNewToken();
        }
        return 'unExcepted Err in generateToken.';
    }

    private function refreshTokens()
    {
        echo 'refreshing';
        $data = [
            'accessToken' => str_replace('Bearer ', '', $this->cache->retrieve('accessToken')),
            'refreshToken' => $this->cache->retrieve('refreshToken'),
        ];
        $result = $this->callCurl('/tokens/refresh', $data, false);
        if (empty($result['accessToken'])) {
            return 'Err in refresh token.';
        }
        if (!empty($result['accessToken'])) {
            $this->cache->store('accessToken', 'Bearer ' . $result['accessToken'], 24 * 60 * 60 - 60);
            $this->cache->store('refreshToken', $result['refreshToken'], 48 * 60 * 60 - 60);
            $this->setAccessToken('Bearer ' . $result['accessToken']);
            $this->setRefreshToken($result['refreshToken']);
            return 'ok';
        }

        return 'unExcepted Err in refreshToken.';
    }

    /**
     * @param $url
     * @param $arrayData
     * @param bool $haveAuth
     * @param int $try
     * @param string $method
     * @return bool|mixed|string
     * @throws Exception
     */
    private function callCurl($url, $arrayData, $haveAuth = false, $try = 0, $method = 'POST')
    {
        $data = $arrayData;
        $jsonData = json_encode($data);
        $accessToken = '';
        if ($haveAuth) {
            $accessToken = $this->getAccessToken();
        }
        $ch = curl_init(self::BASE_URL . $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Jibit.class Rest Api');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . $accessToken,
            'Content-Length: ' . strlen($jsonData)
        ));
        $result = curl_exec($ch);
        $err = curl_error($ch);
        $result = json_decode($result, true);
        curl_close($ch);

        if ($err) {
            return 'cURL Error #:' . $err;
        }
        if (empty($result['errors'])) {
            return $result;
        }
        if ($haveAuth === true && $result['errors'][0]['code'] === 'security.auth_required') {
            $this->generateToken(true);
            if ($try === 0) {
                return $this->callCurl($url, $arrayData, $haveAuth, 1, $method);
            }

            return 'Err in auth.';
        }

        return $result;

    }

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @param mixed $refreshToken
     */
    public function setRefreshToken($refreshToken)
    {
        $refreshToken1 = $refreshToken;
    }

    private function generateNewToken()
    {
        $data = [
            'apiKey' => $this->apiKey,
            'secretKey' => $this->secretKey,
        ];
        $result = $this->callCurl('/tokens', $data);

        if (empty($result['accessToken'])) {
            return 'Err in generate new token.';
        }
        if (!empty($result['accessToken'])) {
            $this->cache->store('accessToken', 'Bearer ' . $result['accessToken'], 24 * 60 * 60 - 60);
            $this->cache->store('refreshToken', $result['refreshToken'], 48 * 60 * 60 - 60);
            $this->setAccessToken('Bearer ' . $result['accessToken']);
            $this->setRefreshToken($result['refreshToken']);
            return 'ok';
        }
        return 'unExcepted Err in generateNewToken.';
    }

    /**
     * @param $purchaseId
     * @return bool|mixed|string
     * @throws Exception
     */
    public function paymentVerify($purchaseId)
    {
        $this->generateToken();
        $data = [];
        return $this->callCurl('/purchases/' . $purchaseId . '/verify', $data, true, 0, 'GET');
    }
}

