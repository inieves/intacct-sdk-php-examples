<?php

$sender_id = 'blackwalnutadvisors';
$sender_password = 'JHb9W52;ja*+';
$user_id = 'webservices_ian';
$user_password = 'I3?k74AsYNp';

$loader = require __DIR__ . '/vendor/autoload.php';

use Intacct\OnlineClient;
use Intacct\ClientConfig;
use Intacct\Functions\AccountsReceivable\ArPaymentCreate;

$handler = new \Monolog\Handler\StreamHandler(__DIR__ . '/logs/intacct.html');
$handler->setFormatter(new \Monolog\Formatter\HtmlFormatter());

$logger = new \Monolog\Logger('intacct-sdk-php-examples');
$logger->pushHandler($handler);

function authenticate($sender_id, $sender_password, $company_id, $user_id, $user_password, $logger){
    $clientConfig = new ClientConfig();
    $clientConfig->setSenderId($sender_id);
    $clientConfig->setSenderPassword($sender_password);
    $clientConfig->setCompanyId($company_id);
    $clientConfig->setUserId($user_id);
    $clientConfig->setUserPassword($user_password);
    $clientConfig->setLogger($logger);
    $client = new OnlineClient($clientConfig);
    $formatter = new \Intacct\Logging\MessageFormatter(
        '"{method} {target} HTTP/{version}" {code}'
    );
    $client->getConfig()->setLogLevel(\Psr\Log\LogLevel::INFO);
    $client->getConfig()->setLogMessageFormatter($formatter);
    return $client;
}

function getNewFilePath($originalFilePath, $additionalFileName){
    $originalFileName = basename($originalFilePath);
    $dotPosition = strpos($originalFileName, '.');
    $newFileName = '';
    if($dotPosition === FALSE){
        $newFileName = $originalFileName . $additionalFileName;
    }
    else{
        $newFileName = substr_replace($originalFileName, $additionalFileName, $dotPosition, 0);
    }
    $newFilePath = substr($originalFilePath, 0, strlen($originalFilePath)-strlen($originalFileName)) . $newFileName;
    return $newFilePath;
}

// SET TIMEZONE
date_default_timezone_set('America/Los_Angeles');

// CONFIG ROW NAMES
$configCompanyIdRowName = 'company_id';
$configRowNames = array($configCompanyIdRowName);
// CONFIG ROW INDICES (IN INSERTION ORDER)
$configRowIndicesByRowName = NULL;
// CONFIG ROWS
$config = NULL;
$configRow = NULL;

// DATA ROW NAMES
$dataPaymentAmountRowName = 'payment_amount';
$dataCustomerAccountIdRowName = 'customer_account_id';
$dataDateReceivedRowName = 'date_received';
$dataPaymentMethodRowName = 'payment_method';
$dataBankAccountIdRowName = 'bank_account_id';
$dataUndepositedFundsGlAccountNumberRowName = 'undeposited_funds_gi_account_number';
$dataOverpaymentLocationIdRowName = 'overpayment_location_id';
$dataFailureMessageRowName = 'failure_message';
$dataFailureClassRowName = 'failure_class';
$dataFailureErrorRowName = 'failure_error';
$dataTimestampRowName = 'time_stamp';
$dataRowNames = array($dataPaymentAmountRowName, $dataCustomerAccountIdRowName, $dataDateReceivedRowName, $dataPaymentMethodRowName, $dataBankAccountIdRowName, $dataUndepositedFundsGlAccountNumberRowName, $dataOverpaymentLocationIdRowName);
// DATA ROW INDICES (IN INSERTION ORDER)
$dataRowIndicesByRowName = NULL;
// DATA ROWS
$dataInputRow = NULL;
$dataInputRows = NULL;
$dataInputRowNames = NULL;
$dataInputSuccessRows = NULL;
$dataInputFailureRows = NULL;

// INPUT FILE STATE
$csvInputFilePath = NULL;
$csvInputFileHandle = NULL;
$csvInputFileDataRow = NULL;
$csvInputFileConfigRow = NULL;
// OUTPUT SUCCESS FILE STATE
$csvOutputSuccessFileNameTag = '_s';
$csvOutputSuccessFilePath = NULL;
$csvOutputSuccessFileHandle = NULL;
$csvOutputSuccessFileDataRow = NULL;
// OUTPUT FAILURE FILE STATE
$csvOutputFailureFileNameTag = '_f';
$csvOutputFailureFilePath = NULL;
$csvOutputFailureFileHandle = NULL;
$csvOutputFailureFileDataRow = NULL;

///////////
// FILES //
///////////

// GET FILE PATHS
if(count($argv) !== 2){
    exit("Error: missing CSV file path\n");
}
$csvInputFilePath = $argv[1];
$csvOutputSuccessFilePath = getNewFilePath($csvInputFilePath, $csvOutputSuccessFileNameTag);
$csvOutputFailureFilePath = getNewFilePath($csvInputFilePath, $csvOutputFailureFileNameTag);

// GET FILE HANDLES
$csvInputFileHandle = @fopen($csvInputFilePath, 'r');
if($csvInputFileHandle === FALSE){
    exit('Error: unable to open CSV input file for reading (' . $csvInputFilePath . ")\n");
}
$csvOutputSuccessFileHandle = @fopen($csvOutputSuccessFilePath, 'w');
if($csvOutputSuccessFileHandle === FALSE){
    exit('Error: unable to open CSV output success file for writing: (' . $csvOutputSuccessFilePath . ")\n");
}
$csvOutputFailureFileHandle = @fopen($csvOutputFailureFilePath, 'w');
if($csvOutputFailureFileHandle === FALSE){
    exit('Error: unable to open CSV output failure file for writing: (' . $csvOutputFailureFilePath . ")\n");
}

////////////
// CONFIG //
////////////

// GET FIRST CONFIG ROW (HEADER)
$csvInputFileConfigRow = fgetcsv($csvInputFileHandle);
if($csvInputFileConfigRow === FALSE){
    exit("Error: unable to get first config row from CSV\n");
}

// GET CONFIG ROW INDICS
$configRowIndicesByRowName = array();
for($i=0; $i<count($csvInputFileConfigRow); $i++){
    $csvFileDataCell = trim($csvInputFileConfigRow[$i]);
    foreach($configRowNames as $configRowName){
        if($csvFileDataCell === $configRowName){
            $configRowIndicesByRowName[$configRowName] = $i;
        }
    }
}
$errorColumn = NULL;
foreach($configRowNames as $configRowName){
    if($configRowIndicesByRowName[$configRowName] === NULL){
        $errorColumn = $configRowName;
        break;
    }
}
if($errorColumn != NULL){
    exit('Error: unable to find config column named: ' . $errorColumn . "\n");
}

// READ CONFIG SETTINGS ROW
$configRow = fgetcsv($csvInputFileHandle);
$config = array();
$company_id = trim($configRow[$configRowIndicesByRowName[$configCompanyIdRowName]]);
$config[$configCompanyIdRowName] = $company_id;

//////////
// MISC //
//////////

// AUTHENTICATE
$client = authenticate($sender_id, $sender_password, $company_id, $user_id, $user_password, $logger);

// GET BLANK ROW
fgetcsv($csvInputFileHandle);

//////////
// DATA //
//////////

// GET FIRST DATA ROW (HEADER)
$csvInputFileDataRow = fgetcsv($csvInputFileHandle);
if($csvInputFileDataRow === FALSE){
    exit("Error: unable to get first data row from CSV\n");
}

// GET DATA ROW INDICES
$dataRowIndicesByRowName = array();
for($i=0; $i<count($csvInputFileDataRow); $i++){
    $csvFileDataCell = trim($csvInputFileDataRow[$i]);
    foreach($dataRowNames as $dataRowName){
        if($csvFileDataCell === $dataRowName){
            $dataRowIndicesByRowName[$dataRowName] = $i;
        }
    }
}
$errorColumn = NULL;
foreach($dataRowNames as $dataRowName){
    if($dataRowIndicesByRowName[$dataRowName] === NULL){
        $errorColumn = $dataRowName;
        break;
    }
}
if($errorColumn != NULL){
    exit('Error: unable to find data column named: ' . $errorColumn . "\n");
}

// READ AND VALIDATE DATA ROWS
$rowIndex = 5;
$rowReadCount = 0;
$dataInputRows = array();
while(($csvInputFileDataRow = fgetcsv($csvInputFileHandle)) !== FALSE){
    // CREATE ROW
    $dataInputRow = array();
    foreach($dataRowNames as $dataRowName){
        $dataInputRow[$dataRowName] = trim($csvInputFileDataRow[$dataRowIndicesByRowName[$dataRowName]]);
    }
    // VALIDATE
    $errorMessage = NULL;
    if(strlen($dataInputRow[$dataBankAccountIdRowName]) > 0 && strlen($dataInputRow[$dataUndepositedFundsGlAccountNumberRowName]) > 0){
        $errorMessage = 'both ' . $dataBankAccountIdRowName . ' and ' . $dataUndepositedFundsGlAccountNumberRowName . ' are specified, only specify one';
    }
    if($errorMessage != NULL){
        exit('Error at row ' . $rowIndex . ': ' . $errorMessage . "\n");
    }
    // ADD TO ROWS
    $dataInputRows[] = $dataInputRow;
    // INCREMENT COUNTS
    $rowReadCount++;
    $rowIndex++;
}
fclose($csvInputFileHandle);
echo 'Input Row Count: ' . $rowReadCount . "\n";

// EXIT IF NOTHING READ
if(count($dataInputRows) === 0){
    exit("Error: no rows input\n");
}

$dataInputSuccessRows = array();
$dataInputFailureRows = array();
// PROCESS EACH ROW
foreach($dataInputRows as $dataInputRow) {
    try {
        // CREATE NEW AR PAYMENT
        $customerAccountId = $dataInputRow[$dataCustomerAccountIdRowName];
        $paymentAmount = $dataInputRow[$dataPaymentAmountRowName];
        $dateReceived = $dataInputRow[$dataDateReceivedRowName];
        $paymentMethod = $dataInputRow[$dataPaymentMethodRowName];
        $bankAccountId = $dataInputRow[$dataBankAccountIdRowName];
        $undepositedFundsGlAccountNumber = $dataInputRow[$dataUndepositedFundsGlAccountNumberRowName];
        $overpaymentLocationId = $dataInputRow[$dataOverpaymentLocationIdRowName];
        $arPaymentCreate = new ArPaymentCreate();
        $arPaymentCreate->setCustomerId($customerAccountId);
        $arPaymentCreate->setTransactionPaymentAmount($paymentAmount);
        $arPaymentCreate->setReceivedDate(new DateTime($dateReceived));
        $arPaymentCreate->setPaymentMethod(constant("Intacct\Functions\AccountsReceivable\ArPaymentCreate::$paymentMethod"));
        if(strlen($bankAccountId) > 0){
            $arPaymentCreate->setBankAccountId($bankAccountId);
        }
        if(strlen($undepositedFundsGlAccountNumber) > 0){
            $arPaymentCreate->setUndepositedFundsGlAccountNo($undepositedFundsGlAccountNumber);
        }
        if(strlen($overpaymentLocationId) > 0){
            $arPaymentCreate->setOverpaymentLocationId($overpaymentLocationId);
        }
        // LOG TRANSACTION TIMESTAMP
        $timestamp = date('Y-m-d h:i:s a ') . date_default_timezone_get();
        $dataInputRow[$dataTimestampRowName] = $timestamp;
        // EXECUTE
        $logger->info('Executing query to Intacct API');
        $response = $client->execute($arPaymentCreate);
        $result = $response->getResult();
        // LOG RESULT
        $logger->debug('Query successful', [
            'Company ID' => $response->getAuthentication()->getCompanyId(),
            'User ID' => $response->getAuthentication()->getUserId(),
            'Request control ID' => $response->getControl()->getControlId(),
            'Function control ID' => $result->getControlId(),
            'Total count' => $result->getTotalCount(),
            'Data' => json_decode(json_encode($result->getData()), 1),
        ]);
        // STORE ROW FOR OUTPUT
        $dataInputSuccessRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo 's';
    }
    catch (\Intacct\Exception\ResponseException $ex) {
        $logger->error('An Intacct response exception was thrown', [
            get_class($ex) => $ex->getMessage(),
            'Errors' => $ex->getErrors(),
        ]);
        // STORE ROW (WITH FAILURE INFO) FOR OUTPUT
        $dataInputRow[$dataFailureClassRowName] = get_class($ex);
        $dataInputRow[$dataFailureMessageRowName] = $ex->getMessage();
        $dataInputRow[$dataFailureErrorRowName] = print_r($ex->getErrors(), TRUE);
        // REPLACE COMMAS
        $dataInputRow[$dataFailureClassRowName] = str_replace(',', '.', $dataInputRow[$dataFailureClassRowName]);
        $dataInputRow[$dataFailureMessageRowName] = str_replace(',', '.', $dataInputRow[$dataFailureMessageRowName]);
        $dataInputRow[$dataFailureErrorRowName] = str_replace(',', '.', $dataInputRow[$dataFailureErrorRowName]);
        $dataInputFailureRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo 'f';
    }
    catch (\Exception $ex) {
        $logger->error('An exception was thrown', [
            get_class($ex) => $ex->getMessage(),
        ]);
        // STORE ROW (WITH FAILURE INFO) FOR OUTPUT
        $dataInputRow[$dataFailureClassRowName] = get_class($ex);
        $dataInputRow[$dataFailureMessageRowName] = $ex->getMessage();
        $dataInputRow[$dataFailureErrorRowName] = '';
        // REPLACE COMMAS
        $dataInputRow[$dataFailureClassRowName] = str_replace(',', '.', $dataInputRow[$dataFailureClassRowName]);
        $dataInputRow[$dataFailureMessageRowName] = str_replace(',', '.', $dataInputRow[$dataFailureMessageRowName]);
        $dataInputFailureRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo 'f';
    }
}

echo "\n";

// GENERATE CONFIG ROW NAMES AND SETTINGS
$orderedConfigSettingNames = array();
$orderedConfigSettings = array();
foreach($configRowIndicesByRowName as $rowName=>$rowIndex){
    $orderedConfigSettingNames[] = $rowName;
    $orderedConfigSettings[] = $config[$rowName];
}
// GENERATE DATA ROW NAMES
$dataInputRowNames = array();
foreach($dataRowIndicesByRowName as $rowName=>$rowIndex){
    $dataInputRowNames[] = $rowName;
}

// OUTPUT SUCCESS FILE
fputcsv($csvOutputSuccessFileHandle, $orderedConfigSettingNames);
fputcsv($csvOutputSuccessFileHandle, $orderedConfigSettings);
fputcsv($csvOutputSuccessFileHandle, array());
$uploadSuccessCount = 0;
$dataInputRowNames[] = $dataTimestampRowName;
fputcsv($csvOutputSuccessFileHandle, $dataInputRowNames);
foreach($dataInputSuccessRows as $dataInputSuccessRow){
    $orderedDataRow = array();
    foreach($dataInputRowNames as $dataInputRowName){
        $orderedDataRow[] = $dataInputSuccessRow[$dataInputRowName];
    }
    $uploadSuccessCount++;
    fputcsv($csvOutputSuccessFileHandle, $orderedDataRow);
}

// OUTPUT FAILURE FILE
fputcsv($csvOutputFailureFileHandle, $orderedConfigSettingNames);
fputcsv($csvOutputFailureFileHandle, $orderedConfigSettings);
fputcsv($csvOutputFailureFileHandle, array());
$uploadFailureCount = 0;
$dataInputRowNames[] = $dataFailureClassRowName;
$dataInputRowNames[] = $dataFailureMessageRowName;
$dataInputRowNames[] = $dataFailureErrorRowName;
fputcsv($csvOutputFailureFileHandle, $dataInputRowNames);
foreach($dataInputFailureRows as $dataInputFailureRow){
    $orderedDataRow = array();
    foreach($dataInputRowNames as $dataInputRowName){
        $orderedDataRow[] = $dataInputFailureRow[$dataInputRowName];
    }
    $uploadFailureCount++;
    fputcsv($csvOutputFailureFileHandle, $orderedDataRow);
}

// OUTPUT RESULT COUNTS
echo 'Upload Success Row Count: ' . $uploadSuccessCount . "\n";
echo 'Upload Failure Row Count: ' . $uploadFailureCount . "\n";

