<?php
require_once dirname(__FILE__)."/ITunesReceiptVerifier.php";

date_default_timezone_set('Asia/Tokyo');

$receipt = file_get_contents(dirname(__FILE__).'/receipt');
//$receipt = 'dummy';

try {
    $verifier = new ITunesReceiptVerifier();
    //$verifier->setEnviroment(ITunesReceiptVerifier::ENVIRONMENT_PRODUCTION);
    $verifier->setEnviroment(ITunesReceiptVerifier::ENVIRONMENT_SANDBOX);
    $verifyResult = $verifier->verify($receipt);
    
    echo "http status code:".$verifyResult->getHttpStatusCode().PHP_EOL;
    var_export(json_decode($verifyResult->getBody(), JSON_PRETTY_PRINT));
} catch (ITunesReceiptVerifyException $e) {
    echo "App Store Verify Failed Code:".$e->getCode().PHP_EOL;
    if (!is_null($e->getVerifyResult())) {
        var_export(json_decode($e->getVerifyResult()->getBody(), JSON_PRETTY_PRINT));
    }
}
