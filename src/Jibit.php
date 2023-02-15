<?php

namespace Vahidkaargar\Jibit;


use Exception;

class Jibit
{
    const BASE_URL = 'https://napi.jibit.ir/ppg/v3';
    public mixed $accessToken;
    private string $apiKey;
    private string $secretKey;
    private Cache $cache;

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
    public function paymentRequest(int $amount, string $referenceNumber, string $userIdentifier, string $callbackUrl, string $currency = 'IRR', $description = null, $additionalData = null): mixed
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
    public function getOrderById($id): mixed
    {
        return $this->callCurl('/purchases?purchaseId=' . $id, [], true, 0, 'GET');

    }


    /**
     * @param bool $isForce
     * @return void
     * @throws Exception
     */
    private function generateToken(bool $isForce = false): void
    {
        $this->cache->eraseExpired();

        if ($isForce === false && $this->cache->isCached('accessToken')) {
            $this->setAccessToken($this->cache->retrieve('accessToken'));
        } else if ($this->cache->isCached('refreshToken')) {
            if (!$this->refreshTokens()) {
                $this->generateNewToken();
            }
        } else {
            $this->generateNewToken();
        }
    }


    /**
     * @throws Exception
     */
    private function generateNewToken(): void
    {
        $data = [
            'apiKey' => $this->apiKey,
            'secretKey' => $this->secretKey,
        ];
        $result = $this->callCurl('/tokens', $data);
        $this->setAndCacheToken($result);
    }


    /**
     * @throws Exception
     */
    private function refreshTokens(): bool
    {
        $data = [
            'accessToken' => str_replace('Bearer ', '', $this->cache->retrieve('accessToken')),
            'refreshToken' => $this->cache->retrieve('refreshToken'),
        ];
        $result = $this->callCurl('/tokens/refresh', $data);
        return $this->setAndCacheToken($result);
    }


    /**
     * @param string $url
     * @param array $arrayData
     * @param bool $haveAuth
     * @param int $try
     * @param string $method
     * @return bool|mixed|string
     * @throws Exception
     */
    private function callCurl(string $url, array $arrayData, bool $haveAuth = false, int $try = 0, string $method = 'POST'): mixed
    {
        $data = $arrayData;
        $jsonData = json_encode($data);
        $accessToken = '';
        if ($haveAuth) {
            $accessToken = $this->getAccessToken();
        }
        $ch = curl_init(self::BASE_URL . $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'github.com/vahidkaargar/jibit/');
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
                return $this->callCurl($url, $arrayData, true, 1, $method);
            }

            return 'Err in auth.';
        }

        return $result;

    }


    /**
     * @return mixed
     */
    public function getAccessToken(): mixed
    {
        return $this->accessToken;
    }


    /**
     * @param mixed $accessToken
     */
    public function setAccessToken(mixed $accessToken): void
    {
        $this->accessToken = $accessToken;
    }


    /**
     * @param $purchaseId
     * @return bool|mixed|string
     * @throws Exception
     */
    public function paymentVerify($purchaseId): mixed
    {
        $this->generateToken();
        $data = [];
        return $this->callCurl('/purchases/' . $purchaseId . '/verify', $data, true, 0, 'GET');
    }


    /**
     * @param mixed $result
     * @return true
     * @throws Exception
     */
    private function setAndCacheToken(mixed $result): bool
    {
        if (!empty($result['accessToken'])) {
            $this->cache->store('accessToken', 'Bearer ' . $result['accessToken'], 24 * 60 * 60 - 60);
            $this->cache->store('refreshToken', $result['refreshToken'], 48 * 60 * 60 - 60);
            $this->setAccessToken('Bearer ' . $result['accessToken']);
            return true;
        }

        return false;
    }
}
