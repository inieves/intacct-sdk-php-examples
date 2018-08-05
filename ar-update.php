<?php

/////////////
// INCLUDE //
/////////////

$loader = require __DIR__ . '/vendor/autoload.php';

use Intacct\OnlineClient;
use Intacct\ClientConfig;
use Intacct\Functions\AccountsReceivable\ArPaymentCreate;

////////////
// CONFIG //
////////////

// CREDENTIALS
$sender_id = 'blackwalnutadvisors';
$sender_password = 'JHb9W52;ja*+';
$user_id = '87y9cw3wTwTDCQ';

// CONFIG COLUMN NAMES
$configCompanyIdColumnName = 'company_id';
$configUserPasswordColumnName = 'user_password';

// DATA COLUMN NAMES
$dataPaymentAmountColumnName = 'payment_amount';
$dataCustomerAccountIdColumnName = 'customer_account_id';
$dataDateReceivedColumnName = 'date_received';
$dataPaymentMethodColumnName = 'payment_method';
$dataBankAccountIdColumnName = 'bank_account_id';
$dataUndepositedFundsGlAccountNumberColumnName = 'undeposited_funds_gi_account_number';
$dataOverpaymentLocationIdColumnName = 'overpayment_location_id';

// INFO COLUMN NAMES
$infoTimestampColumnName = 'time_stamp';
$infoFailureMessageColumnName = 'failure_message';
$infoFailureClassColumnName = 'failure_class';
$infoFailureErrorColumnName = 'failure_error';

// OUTPUT FILES
$csvOutputSuccessFileNameTag = '_s';
$csvOutputFailureFileNameTag = '_f';

// PROGRESS OUTPUT
$successProgressOutput = 's';
$failureProgressOutput = 'f';

// TIMESTAMP
$timestampFormat = 'Y-m-d h:i:s a ';

// SET TIMEZONE
date_default_timezone_set('America/Los_Angeles');

// LOGGING
$loggerName = 'intacct-sdk-php-examples';
$logPath = '/logs/intacct.html';

///////////
// STATE //
///////////

// CONFIG COLUMNS
$configColumnNames = array($configCompanyIdColumnName, $configUserPasswordColumnName);
// CONFIG COLUMN INDICES (IN INSERTION ORDER)
$configColumnIndicesByColumnName = NULL;
// CONFIG ROWS
$config = NULL;
$configRow = NULL;

// DATA COLUMNS
$dataColumnNames = array($dataPaymentAmountColumnName, $dataCustomerAccountIdColumnName, $dataDateReceivedColumnName, $dataPaymentMethodColumnName, $dataBankAccountIdColumnName, $dataUndepositedFundsGlAccountNumberColumnName, $dataOverpaymentLocationIdColumnName);
// DATA COLUMN INDICES (IN INSERTION ORDER)
$dataColumnIndicesByColumnName = NULL;
// DATA ROWS
$dataInputRow = NULL;
$dataInputRows = NULL;
$dataInputColumnNames = NULL;
$dataInputSuccessRows = NULL;
$dataInputFailureRows = NULL;

// INFO COLUMNS
$infoColumnNames = array($infoTimestampColumnName, $infoFailureMessageColumnName, $infoFailureClassColumnName, $infoFailureErrorColumnName);

// INPUT FILE STATE
$csvInputFilePath = NULL;
$csvInputFileHandle = NULL;
$csvInputFileDataRow = NULL;
$csvInputFileConfigRow = NULL;
// OUTPUT SUCCESS FILE STATE
$csvOutputSuccessFilePath = NULL;
$csvOutputSuccessFileHandle = NULL;
$csvOutputSuccessFileDataRow = NULL;
// OUTPUT FAILURE FILE STATE
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

//////////////////
// CONFIG INPUT //
//////////////////

// GET FIRST CONFIG ROW (HEADER)
$csvInputFileConfigRow = fgetcsv($csvInputFileHandle);
if($csvInputFileConfigRow === FALSE){
    exit("Error: unable to get first config row from CSV\n");
}

// GET CONFIG COLUMN INDICES
$configColumnIndicesByColumnName = getColumnIndicesByColumnName($csvInputFileConfigRow, $configColumnNames);

// READ CONFIG SETTINGS ROW
$configRow = fgetcsv($csvInputFileHandle);
$config = getRowGivenColumnNames($configRow, $configColumnIndicesByColumnName, $configColumnNames);

///////////
// SPACE //
///////////

// GET BLANK ROW
fgetcsv($csvInputFileHandle);

////////////////
// DATA INPUT //
////////////////

// GET FIRST DATA ROW (HEADER)
$csvInputFileDataRow = fgetcsv($csvInputFileHandle);
if($csvInputFileDataRow === FALSE){
    exit("Error: unable to get first data row from CSV\n");
}

// GET DATA COLUMN INDICES
$dataColumnIndicesByColumnName = getColumnIndicesByColumnName($csvInputFileDataRow, $dataColumnNames);

// READ AND VALIDATE DATA ROWS
$rowIndex = 5;
$rowReadCount = 0;
$dataInputRows = array();
while(($csvInputFileDataRow = fgetcsv($csvInputFileHandle)) !== FALSE){
    // CREATE ROW
    $dataInputRow = getRowGivenColumnNames($csvInputFileDataRow, $dataColumnIndicesByColumnName, $dataColumnNames);
    // VALIDATE
    $errorMessage = NULL;
    if(strlen($dataInputRow[$dataBankAccountIdColumnName]) > 0 && strlen($dataInputRow[$dataUndepositedFundsGlAccountNumberColumnName]) > 0){
        $errorMessage = 'both ' . $dataBankAccountIdColumnName . ' and ' . $dataUndepositedFundsGlAccountNumberColumnName . ' are specified, only specify one';
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

//////////////////
// AUTHENTICATE //
//////////////////

$handler = new \Monolog\Handler\StreamHandler(__DIR__ . $logPath);
$handler->setFormatter(new \Monolog\Formatter\HtmlFormatter());

$logger = new \Monolog\Logger($loggerName);
$logger->pushHandler($handler);

$company_id = $config[$configCompanyIdColumnName];
$user_password = $config[$configUserPasswordColumnName];
$client = authenticate($sender_id, $sender_password, $company_id, $user_id, $user_password, $logger);

///////////////////////
// PUSH TRANSACTIONS //
///////////////////////

$dataInputSuccessRows = array();
$dataInputFailureRows = array();
// PROCESS EACH ROW
foreach($dataInputRows as $dataInputRow) {
    try {
        // CREATE NEW AR PAYMENT
        $customerAccountId = $dataInputRow[$dataCustomerAccountIdColumnName];
        $paymentAmount = $dataInputRow[$dataPaymentAmountColumnName];
        $dateReceived = $dataInputRow[$dataDateReceivedColumnName];
        $paymentMethod = $dataInputRow[$dataPaymentMethodColumnName];
        $bankAccountId = $dataInputRow[$dataBankAccountIdColumnName];
        $undepositedFundsGlAccountNumber = $dataInputRow[$dataUndepositedFundsGlAccountNumberColumnName];
        $overpaymentLocationId = $dataInputRow[$dataOverpaymentLocationIdColumnName];
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
        $timestamp = date($timestampFormat) . date_default_timezone_get();
        $dataInputRow[$infoTimestampColumnName] = $timestamp;
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
        echo $successProgressOutput;
    }
    catch (\Intacct\Exception\ResponseException $ex) {
        $logger->error('An Intacct response exception was thrown', [
            get_class($ex) => $ex->getMessage(),
            'Errors' => $ex->getErrors(),
        ]);
        // STORE ROW (WITH FAILURE INFO) FOR OUTPUT
        $dataInputRow[$infoFailureClassColumnName] = get_class($ex);
        $dataInputRow[$infoFailureMessageColumnName] = $ex->getMessage();
        $dataInputRow[$infoFailureErrorColumnName] = print_r($ex->getErrors(), TRUE);
        // REPLACE COMMAS
        $dataInputRow[$infoFailureClassColumnName] = str_replace(',', '.', $dataInputRow[$infoFailureClassColumnName]);
        $dataInputRow[$infoFailureMessageColumnName] = str_replace(',', '.', $dataInputRow[$infoFailureMessageColumnName]);
        $dataInputRow[$infoFailureErrorColumnName] = str_replace(',', '.', $dataInputRow[$infoFailureErrorColumnName]);
        $dataInputFailureRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo $failureProgressOutput;
    }
    catch (\Exception $ex) {
        $logger->error('An exception was thrown', [
            get_class($ex) => $ex->getMessage(),
        ]);
        // STORE ROW (WITH FAILURE INFO) FOR OUTPUT
        $dataInputRow[$infoFailureClassColumnName] = get_class($ex);
        $dataInputRow[$infoFailureMessageColumnName] = $ex->getMessage();
        $dataInputRow[$infoFailureErrorColumnName] = '';
        // REPLACE COMMAS
        $dataInputRow[$infoFailureClassColumnName] = str_replace(',', '.', $dataInputRow[$infoFailureClassColumnName]);
        $dataInputRow[$infoFailureMessageColumnName] = str_replace(',', '.', $dataInputRow[$infoFailureMessageColumnName]);
        $dataInputFailureRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo $failureProgressOutput;
    }
}

echo "\n";

//////////////////
// OUTPUT FILES //
//////////////////

// GENERATE CONFIG COLUMN NAMES AND SETTINGS
$orderedConfigSettingNames = array();
foreach($configColumnIndicesByColumnName as $rowName=>$rowIndex){
    $orderedConfigSettingNames[] = $rowName;
}
// GENERATE DATA COLUMN NAMES
$dataInputColumnNames = array();
foreach($dataColumnIndicesByColumnName as $rowName=>$rowIndex){
    $dataInputColumnNames[] = $rowName;
}
// GENERATE INFO COLUMN NAMES
foreach($infoColumnNames as $infoColumnName){
    $dataInputColumnNames[] = $infoColumnName;
}

// OUTPUT SUCCESS FILE
$uploadSuccessCount = outputFile($csvOutputSuccessFileHandle, $orderedConfigSettingNames, $config, $dataInputColumnNames, $dataInputSuccessRows);

// OUTPUT FAILURE FILE
$uploadFailureCount = outputFile($csvOutputFailureFileHandle, $orderedConfigSettingNames, $config, $dataInputColumnNames, $dataInputFailureRows);

// OUTPUT RESULT COUNTS
echo 'Upload Success Row Count: ' . $uploadSuccessCount . "\n";
echo 'Upload Failure Row Count: ' . $uploadFailureCount . "\n";

///////////////
// FUNCTIONS //
///////////////

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

function getColumnIndicesByColumnName($csvInputFileRow, $columnNames){
    $columnIndicesByColumnName = array();
    for($i=0; $i<count($csvInputFileRow); $i++){
        $csvFileDataCell = trim($csvInputFileRow[$i]);
        foreach($columnNames as $columnName){
            if($csvFileDataCell === $columnName){
                $columnIndicesByColumnName[$columnName] = $i;
            }
        }
    }
    $errorColumn = NULL;
    foreach($columnNames as $columnName){
        if($columnIndicesByColumnName[$columnName] === NULL){
            $errorColumn = $columnName;
            break;
        }
    }
    if($errorColumn != NULL){
        exit('Error: unable to find column named: ' . $errorColumn . "\n");
    }
    return $columnIndicesByColumnName;
}

function getRowGivenColumnNames($rawRow, $columnIndicesByColumnName, $columnNames){
    $row = array();
    foreach($columnNames as $columnName){
        $row[$columnName] = trim($rawRow[$columnIndicesByColumnName[$columnName]]);
    }
    return $row;
}

function outputFile($fileHandle, $orderedConfigColumnNames, $configRow, $orderedDataColumnNames, $dataRows){
    fputcsv($fileHandle, $orderedConfigColumnNames);
    $orderedConfigRow = array();
    foreach($orderedConfigColumnNames as $orderedConfigColumnName){
        $orderedConfigRow[] = $configRow[$orderedConfigColumnName];
    }
    fputcsv($fileHandle, $orderedConfigRow);
    fputcsv($fileHandle, array());
    $rowCount = 0;
    fputcsv($fileHandle, $orderedDataColumnNames);
    foreach($dataRows as $dataRow){
        $orderedDataRow = array();
        foreach($orderedDataColumnNames as $orderedDataColumnName){
            $orderedDataRow[] = $dataRow[$orderedDataColumnName];
        }
        $rowCount++;
        fputcsv($fileHandle, $orderedDataRow);
    }
    return $rowCount;
}