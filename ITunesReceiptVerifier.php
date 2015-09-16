<?php

class ITunesReceiptVerifier
{
    const ENVIRONMENT_SANDBOX    = 0;
    const ENVIRONMENT_PRODUCTION = 1;

    private $enviroment = self::ENVIRONMENT_PRODUCTION;

    public function verify($receipt)
    {
        
    }

    private function getCurlClient()
    {
        
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
     * 最初に問い合わせる verifyReceipt を環境を分けて返す
     * @return ITunesReceiptVerifierCurlClient
     */
    private function getEndpointBeginningClient()
    {
        if ($this->enviroment == self::ENVIRONMENT_PRODUCTION) {
            return new ITunesReceiptVerifierCurlClient(self::ENVIRONMENT_PRODUCTION, "");
        }
        return new ITunesReceiptVerifierCurlClient(self::ENVIRONMENT_PRODUCTION, "");
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

    public function send()
    {
        $curl = curl_init($url);
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
        // TODO: これをわたす
        $response = curl_exec($curl);
    }
}


class ITunesReceiptVerifyResult
{
    private $body;
    private $httpStatus;
}