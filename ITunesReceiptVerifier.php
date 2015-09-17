<?php

class ITunesReceiptVerifier
{
    const ENVIRONMENT_SANDBOX    = 0;
    const ENVIRONMENT_PRODUCTION = 1;

    private $enviroment = self::ENVIRONMENT_PRODUCTION;

    /**
     * @param $receipt
     * @param bool $resolveEnviroment
     * @return ITunesReceiptVerifyResult
     */
    public function verify($receipt, $resolveEnviroment = true)
    {
        return $this->verifyWithClient(
            $this->getBeginningEndpointClient($receipt),
            $resolveEnviroment
        );
    }

    /**
     * @param $client
     * @param $resolveEnviroment
     * @return ITunesReceiptVerifyResult
     */
    private function verifyWithClient($client, $resolveEnviroment)
    {
        $verifyResult = $client->send();
        if ($verifyResult->isSucceed()) {
            return $verifyResult;
        }
        switch ($verifyResult->getStatus()) {
            case ITunesReceiptVerifyResult::STATUS_INVALID_JSON:
            case ITunesReceiptVerifyResult::STATUS_INVALID_RECEIPT:
            case ITunesReceiptVerifyResult::STATUS_ERROR_VERIFY:
            case ITunesReceiptVerifyResult::STATUS_INVALID_KEY:
            case ITunesReceiptVerifyResult::STATUS_SERVER_MAINTENANCE:
            case ITunesReceiptVerifyResult::STATUS_EXPIRED:
                // 復帰が難しい例外
                throw new ITunesReceiptVerifyException("iTunes receipt verify failed", $verifyResult->getStatus(), $verifyResult);
                break;
            case ITunesReceiptVerifyResult::STATUS_REDIRECT_TO_PRODUCTION:
                if (!$resolveEnviroment) {
                    return $verifyResult;
                }
                return $this->verifyWithClient(
                    $this->createClient(ITunesReceiptVerifier::ENVIRONMENT_PRODUCTION, $client->getReceipt()),
                    false // 2度目は環境解決しない
                );
                break;
            case ITunesReceiptVerifyResult::STATUS_REDIRECT_TO_SANDBOX:
                if (!$resolveEnviroment) {
                    return $verifyResult;
                }
                return $this->verifyWithClient(
                    $this->createClient(ITunesReceiptVerifier::ENVIRONMENT_SANDBOX, $client->getReceipt()),
                    false // 2度目は環境解決しない
                );
                break;
        }
        // ここにはこないハズなので来たら例外投げる
        throw new ITunesReceiptVerifyException("Unexpected error", $verifyResult->getStatus(), $verifyResult);
    }

    /**
     * 利用する環境の設定
     * 環境を変更すると最初に問い合わせる環境が変わる
     * @param int $enviroment self::{ENVIRONMENT_SANDBOX, ENVIRONMENT_PRODUCTION}
     */
    public function setEnviroment($enviroment)
    {
        $this->enviroment = $enviroment;
    }

    /**
     * 最初に問い合わせる verifyReceipt 環境を設定済みで返す
     * @return ITunesReceiptVerifyCurlClient
     */
    private function getBeginningEndpointClient($receipt)
    {
        if ($this->enviroment == self::ENVIRONMENT_PRODUCTION) {
            return self::createClient(self::ENVIRONMENT_PRODUCTION, $receipt);
        }
        return self::createClient(self::ENVIRONMENT_SANDBOX, $receipt);
    }

    /**
     * @param $enviroment
     * @param $receipt
     * @return ITunesReceiptVerifyCurlClient
     */
    private static function createClient($enviroment, $receipt)
    {
        return new ITunesReceiptVerifyCurlClient($enviroment, $receipt);
    }
}

class ITunesReceiptVerifyCurlClient
{
    const ENDPOINT_SANDBOX    = 'https://sandbox.itunes.apple.com/verifyReceipt';
    const ENDPOINT_PRODUCTION = 'https://buy.itunes.apple.com/verifyReceipt';

    private $enviroment;
    private $receipt;

    public function __construct($enviroment, $receipt)
    {
        $this->enviroment = $enviroment;
        $this->receipt = $receipt;
    }

    private function getEndpoint()
    {
        if ($this->enviroment === ITunesReceiptVerifier::ENVIRONMENT_PRODUCTION) {
            return self::ENDPOINT_PRODUCTION;
        }
        return self::ENDPOINT_SANDBOX;
    }

    public function getReceipt()
    {
        return $this->receipt;
    }

    /**
     * @return ITunesReceiptVerifyResult
     */
    public function send()
    {
        $curl = curl_init($this->getEndpoint());
        $dataString = json_encode(array(
            'receipt-data' => $this->receipt,
        ));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($dataString))
        );
        return new ITunesReceiptVerifyResult(
            curl_exec($curl),
            curl_getinfo($curl, CURLINFO_HTTP_CODE)
        );
    }
}

class ITunesReceiptVerifyResult
{
    const STATUS_SUCCESS                = 0;
    const STATUS_INVALID_JSON           = 21000; // App Storeは、提供したJSONオブジェクトを読むことができません。
    const STATUS_INVALID_RECEIPT        = 21002; // receipt-dataプロパティのデータが不正であるか、または欠落しています。
    const STATUS_ERROR_VERIFY           = 21003; // レシートを認証できません。
    const STATUS_INVALID_KEY            = 21004; // この共有秘密鍵は、アカウントのファイルに保存された共有秘密鍵と一致しません。
    const STATUS_SERVER_MAINTENANCE     = 21005; // レシートサーバは現在利用できません。
    const STATUS_EXPIRED                = 21006; // このレシートは有効ですが、定期購読の期限が切れています。
    const STATUS_REDIRECT_TO_SANDBOX    = 21007; // テスト環境のレシートを、実稼働環境に送信して検証しようとしました。これはテスト環境に送信してください。
    const STATUS_REDIRECT_TO_PRODUCTION = 21008; // 実稼働環境のレシートを、テスト環境に送信して検証しようとしました。これは実稼働環境に送信してください。

    private $body;
    private $data;
    private $httpStatusCode;

    public function __construct($body, $httpStatusCode)
    {
        $this->body = $body;
        $this->httpStatusCode = $httpStatusCode;
        $this->init();
    }

    private function init()
    {
        $this->data = json_decode($this->body, true);
    }

    public function isSucceed()
    {
        return self::STATUS_SUCCESS === $this->data['status'];
    }

    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    public function getStatus()
    {
        return intval($this->data['status']);
    }

    public function getBody()
    {
        return $this->body;
    }
}

class ITunesReceiptVerifyException extends RuntimeException
{
    private $verifyResult = null;

    public function __construct($message, $code, $verifyResult)
    {
        $this->verifyResult = $verifyResult;
        parent::__construct($message, $code);
    }

    public function getVerifyResult()
    {
        return $this->verifyResult;
    }
}