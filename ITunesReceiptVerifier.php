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
                // ���A�������O
                throw new ITunesReceiptVerifyException("iTunes receipt verify failed", $verifyResult->getStatus(), $verifyResult);
                break;
            case ITunesReceiptVerifyResult::STATUS_REDIRECT_TO_PRODUCTION:
                if (!$resolveEnviroment) {
                    return $verifyResult;
                }
                return $this->verifyWithClient(
                    $this->createClient(ITunesReceiptVerifier::ENVIRONMENT_PRODUCTION, $client->getReceipt()),
                    false // 2�x�ڂ͊��������Ȃ�
                );
                break;
            case ITunesReceiptVerifyResult::STATUS_REDIRECT_TO_SANDBOX:
                if (!$resolveEnviroment) {
                    return $verifyResult;
                }
                return $this->verifyWithClient(
                    $this->createClient(ITunesReceiptVerifier::ENVIRONMENT_SANDBOX, $client->getReceipt()),
                    false // 2�x�ڂ͊��������Ȃ�
                );
                break;
        }
        // �����ɂ͂��Ȃ��n�Y�Ȃ̂ŗ������O������
        throw new ITunesReceiptVerifyException("Unexpected error", $verifyResult->getStatus(), $verifyResult);
    }

    /**
     * ���p������̐ݒ�
     * ����ύX����ƍŏ��ɖ₢���킹������ς��
     * @param int $enviroment self::{ENVIRONMENT_SANDBOX, ENVIRONMENT_PRODUCTION}
     */
    public function setEnviroment($enviroment)
    {
        $this->enviroment = $enviroment;
    }

    /**
     * �ŏ��ɖ₢���킹�� verifyReceipt ����ݒ�ς݂ŕԂ�
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
    const STATUS_INVALID_JSON           = 21000; // App Store�́A�񋟂���JSON�I�u�W�F�N�g��ǂނ��Ƃ��ł��܂���B
    const STATUS_INVALID_RECEIPT        = 21002; // receipt-data�v���p�e�B�̃f�[�^���s���ł��邩�A�܂��͌������Ă��܂��B
    const STATUS_ERROR_VERIFY           = 21003; // ���V�[�g��F�؂ł��܂���B
    const STATUS_INVALID_KEY            = 21004; // ���̋��L�閧���́A�A�J�E���g�̃t�@�C���ɕۑ����ꂽ���L�閧���ƈ�v���܂���B
    const STATUS_SERVER_MAINTENANCE     = 21005; // ���V�[�g�T�[�o�͌��ݗ��p�ł��܂���B
    const STATUS_EXPIRED                = 21006; // ���̃��V�[�g�͗L���ł����A����w�ǂ̊������؂�Ă��܂��B
    const STATUS_REDIRECT_TO_SANDBOX    = 21007; // �e�X�g���̃��V�[�g���A���ғ����ɑ��M���Č��؂��悤�Ƃ��܂����B����̓e�X�g���ɑ��M���Ă��������B
    const STATUS_REDIRECT_TO_PRODUCTION = 21008; // ���ғ����̃��V�[�g���A�e�X�g���ɑ��M���Č��؂��悤�Ƃ��܂����B����͎��ғ����ɑ��M���Ă��������B

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