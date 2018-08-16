<?php

/////////////
// INCLUDE //
/////////////

$loader = require __DIR__ . '/vendor/autoload.php';

use Intacct\OnlineClient;
use Intacct\ClientConfig;
use Intacct\RequestConfig;
use Intacct\Functions\AccountsReceivable\ArPaymentCreate;
use Intacct\Functions\AccountsReceivable\ArPaymentItem;
use Intacct\Functions\Common\ReadByQuery;
use Intacct\Functions\Common\Query\QueryString;

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
$dataUndepositedFundsGlAccountNumberColumnName = 'undeposited_funds_gl_account_number';
$dataOverpaymentLocationIdColumnName = 'overpayment_location_id';
$dataApplyToInvoiceNumber = 'apply_to_invoice_number';
$dataApplyToInvoiceAmount = 'apply_to_invoice_amount';

// PAYMENT OBJECT NAME
$paymentObjectName = 'payment';

// INFO COLUMN NAMES
$infoTimestampColumnName = 'time_stamp';
$infoErrorMessagesColumnName = 'error_messages';

// OUTPUT FILES
$firstDataRowIndex = 5;
$csvOutputSuccessFileNameTag = '_s';
$csvOutputFailureFileNameTag = '_f';

// PROGRESS OUTPUT
$successProgressOutput = 's';
$failureProgressOutput = 'f';

// FORMATS
$timestampFormat = 'Y-m-d h:i:s a ';
$dateReceivedFormat = 'Y-m-d';

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
$dataColumnNames = array($dataPaymentAmountColumnName, $dataCustomerAccountIdColumnName, $dataDateReceivedColumnName, $dataPaymentMethodColumnName, $dataBankAccountIdColumnName, $dataUndepositedFundsGlAccountNumberColumnName, $dataOverpaymentLocationIdColumnName, $dataApplyToInvoiceNumber, $dataApplyToInvoiceAmount);
// DATA COLUMN INDICES (IN INSERTION ORDER)
$dataColumnIndicesByColumnName = NULL;
// DATA ROWS
$dataInputRow = NULL;
$dataInputRows = NULL;
$dataInputColumnNames = NULL;
$dataInputSuccessRows = NULL;
$dataInputFailureRows = NULL;

// INFO COLUMNS
$infoColumnNames = array($infoTimestampColumnName, $infoErrorMessagesColumnName);

// RESULT OBJECTS
$resultObjects = NULL;

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

// INVOICE NUMBERS TO TRANSLATE TO RECORD NUMBERS
$applyToRecordNumbersByInvoiceNumber = NULL;
$invoiceNumbersByArPaymentControlId = NULL;

/////////////////
// INPUT FILES //
/////////////////

// GET FILE PATHS
if(count($argv) !== 2){
    exit("Error: missing CSV file path\n");
}
$csvInputFilePath = $argv[1];

// GET FILE HANDLES
$csvInputFileHandle = @fopen($csvInputFilePath, 'r');
if($csvInputFileHandle === FALSE){
    exit('Error: unable to open CSV input file for reading (' . $csvInputFilePath . ")\n");
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
    if(!empty($dataInputRow[$dataBankAccountIdColumnName]) && !empty($dataInputRow[$dataUndepositedFundsGlAccountNumberColumnName])){
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
echo 'Input Data Row Count: ' . $rowReadCount . "\n";

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

/////////////////////
// PARSE DATA ROWS //
/////////////////////

$rowIndex = $firstDataRowIndex;
$lastArPayment = NULL;
$arPayments = array();
$invoiceNumbersByArPaymentControlId = array();

foreach($dataInputRows as $dataInputRow){
    // CHECK IF ROW IS JUST PAYMENT ITEM FIELDS OR A FULL PAYMENT
    $bothPaymentItemFieldsSpecified = TRUE;
    $someNonPaymentItemFieldSpecified = FALSE;
    $allFieldsUnspecified = TRUE;
    foreach($dataColumnNames as $dataColumnName){
        if($dataColumnName === $dataApplyToInvoiceNumber
            || $dataColumnName === $dataApplyToInvoiceAmount){
            if(empty($dataInputRow[$dataColumnName])){
                $bothPaymentItemFieldsSpecified = FALSE;
            }
        }
        else{
            if(!empty($dataInputRow[$dataColumnName])){
                $someNonPaymentItemFieldSpecified = TRUE;
            }
        }
        if(!empty($dataInputRow[$dataColumnName])){
            $allFieldsUnspecified = FALSE;
        }
    }
    // GET ROWS
    $customerAccountId = $dataInputRow[$dataCustomerAccountIdColumnName];
    $paymentAmount = $dataInputRow[$dataPaymentAmountColumnName];
    $dateReceived = $dataInputRow[$dataDateReceivedColumnName];
    $paymentMethod = $dataInputRow[$dataPaymentMethodColumnName];
    $bankAccountId = $dataInputRow[$dataBankAccountIdColumnName];
    $undepositedFundsGlAccountNumber = $dataInputRow[$dataUndepositedFundsGlAccountNumberColumnName];
    $overpaymentLocationId = $dataInputRow[$dataOverpaymentLocationIdColumnName];
    $applyToInvoiceNumber = $dataInputRow[$dataApplyToInvoiceNumber];
    $applyToInvoiceAmount = $dataInputRow[$dataApplyToInvoiceAmount];
    if($allFieldsUnspecified){
        exit('Error: all fields unspecified at row: ' . $rowIndex . "\n");
    }
    // HANDLE JUST PAYMENT ITEM
    else if($bothPaymentItemFieldsSpecified && !$someNonPaymentItemFieldSpecified && $lastArPayment!=NULL){
        addArPaymentItemWithAmountToArPayment($lastArPayment, $applyToInvoiceAmount);
        storeArPaymentInvoiceNumber($lastArPayment, $applyToInvoiceNumber, $invoiceNumbersByArPaymentControlId);
    }
    // HANDLE FULL PAYMENT
    else{
        $paymentMethodString = getPaymentMethodStringFromConstant($paymentMethod);
        if($paymentMethodString === NULL){
            exit('Error: invalid payment method at row: ' . $rowIndex . "\n");
        }
        $arPayment = new ArPaymentCreate();
        $arPayment->setCustomerId($customerAccountId);
        $arPayment->setTransactionPaymentAmount($paymentAmount);
        $arPayment->setReceivedDate(new DateTime($dateReceived));
        $arPayment->setPaymentMethod($paymentMethodString);
        if(!empty($bankAccountId)){
            $arPayment->setBankAccountId($bankAccountId);
        }
        if(!empty($undepositedFundsGlAccountNumber)){
            $arPayment->setUndepositedFundsGlAccountNo($undepositedFundsGlAccountNumber);
        }
        if(!empty($overpaymentLocationId)){
            $arPayment->setOverpaymentLocationId($overpaymentLocationId);
        }
        if($bothPaymentItemFieldsSpecified){
            addArPaymentItemWithAmountToArPayment($arPayment, $applyToInvoiceAmount);
            storeArPaymentInvoiceNumber($arPayment, $applyToInvoiceNumber, $invoiceNumbersByArPaymentControlId);
        }
        // UPDATE LAST PAYMENT
        $lastArPayment = $arPayment;
        // RECORD PAYMENT
        $arPayments[] = $arPayment;
    }
    $rowIndex++;
}

////////////////////////////////////////////////////////////
// TRANSLATE INVOICE NUMBERS TO RECORD NUMBERS (IN BATCH) //
////////////////////////////////////////////////////////////

$applyToRecordNumbersByInvoiceNumber = getRecordNumbersByInvoiceNumbers($invoiceNumbersByArPaymentControlId, $client, $logger);

foreach($arPayments as $arPayment){
    translateInvoiceNumbersToRecordNumbersInArPaymentItems($arPayment, $applyToRecordNumbersByInvoiceNumber);
}

/////////////////////////////////
// PROCESS PAYMENTS (IN BATCH) //
/////////////////////////////////

echo "\n";

$isSuccess = NULL;

// LOG TRANSACTION TIMESTAMP
$timestamp = date($timestampFormat) . date_default_timezone_get();
$logger->info('Executing batch transaction to Intacct API');
$requestConfig = new RequestConfig();
$requestConfig->setTransaction(true);
$response = $client->executeBatch($arPayments, $requestConfig);
$results = $response->getResults();

$isSuccess = $results[0]->getStatus() === 'success';
$resultObjects = array();

if($isSuccess){
    foreach($arPayments as $arPayment){
        // CREATE OBJECT FOR OUTPUT
        $resultObject = array();
        $resultObject[$paymentObjectName] = $arPayment;
        $resultObject[$infoTimestampColumnName] = $timestamp;
        // ADD FAILURE INFO TO OBJECT
        $resultObject[$infoErrorMessagesColumnName] = '';
        // STORE OBJECT FOR OUTPUT
        $resultObjects[] = $resultObject;
    }
    // OUTPUT MESSAGE
    echo "SUCCESS\n";
}
else{
    $errorsByControlId = array();
    foreach($results as $result){
        $errors = $result->getErrors();
        if(!empty($errors)){
            $errorsString = implode("\n", $errors);//print_r($errors, TRUE);
            $errorsString = str_replace(',', '.', $errorsString);
            $errorsByControlId[$result->getControlId()] = $errorsString;
        }
        else{
            $errorsByControlId[$result->getControlId()] = '';
        }
    }
    foreach($arPayments as $arPayment){
        // CREATE OBJECT FOR OUTPUT
        $resultObject = array();
        $resultObject[$paymentObjectName] = $arPayment;
        $resultObject[$infoTimestampColumnName] = $timestamp;
        // ADD FAILURE INFO TO OBJECT
        if(isset($errorsByControlId[$arPayment->getControlId()])){
            $resultObject[$infoErrorMessagesColumnName] = $errorsByControlId[$arPayment->getControlId()];
        }
        else{
            $resultObject[$infoErrorMessagesColumnName] = '';
        }
        // STORE OBJECT FOR OUTPUT
        $resultObjects[] = $resultObject;
    }
    // OUTPUT MESSAGE
    echo "FAILURE (all transactions rolled back)\n";
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

if($isSuccess){
    $csvOutputFilePath = getNewFilePath($csvInputFilePath, $csvOutputSuccessFileNameTag);
}
else{
    $csvOutputFilePath = getNewFilePath($csvInputFilePath, $csvOutputFailureFileNameTag);
}

$csvOutputFileHandle = @fopen($csvOutputFilePath, 'w');
if($csvOutputFileHandle === FALSE){
    exit('Error: unable to open CSV output file for writing: (' . $csvOutputFilePath . ")\n");
}
$transactionCount = outputFile($csvOutputFileHandle, $orderedConfigSettingNames, $config, $dataInputColumnNames, $infoColumnNames, $resultObjects, $invoiceNumbersByArPaymentControlId);

// OUTPUT RESULT COUNTS
echo 'Transaction Count: ' . $transactionCount . "\n";


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
        if(!isset($columnIndicesByColumnName[$columnName])){
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

function outputFile($fileHandle, $orderedConfigColumnNames, $configRow, $orderedDataColumnNames, $infoColumnNames, $dataObjects, $invoiceNumbersByArPaymentControlId){
    global $dateReceivedFormat, $paymentObjectName, $dataCustomerAccountIdColumnName, $dataPaymentAmountColumnName, $dataDateReceivedColumnName, $dataPaymentMethodColumnName, $dataBankAccountIdColumnName, $dataUndepositedFundsGlAccountNumberColumnName, $dataOverpaymentLocationIdColumnName, $dataApplyToInvoiceNumber, $dataApplyToInvoiceAmount;
    // OUTPUT CONFIG HEADER
    fputcsv($fileHandle, $orderedConfigColumnNames);
    // OUTPUT CONFIG SETTINGS
    $orderedConfigRow = array();
    foreach($orderedConfigColumnNames as $orderedConfigColumnName){
        $orderedConfigRow[] = $configRow[$orderedConfigColumnName];
    }
    fputcsv($fileHandle, $orderedConfigRow);
    // OUTPUT BLANK ROW
    fputcsv($fileHandle, array('',''));
    // OUTPUT DATA HEADER
    $orderedDataAndInfoColumnNames = array_merge($orderedDataColumnNames, $infoColumnNames);
    fputcsv($fileHandle, $orderedDataAndInfoColumnNames);
    // OUTPUT DATA ROWS
    $objectCount = 0;
    foreach($dataObjects as $dataObject){
        $invoiceNumbers = NULL;
        // GET OBJECT FIELDS
        $arPaymentFields = array();
        $arPaymentFields[$dataCustomerAccountIdColumnName] = $dataObject[$paymentObjectName]->getCustomerId();
        $arPaymentFields[$dataPaymentAmountColumnName] = $dataObject[$paymentObjectName]->getTransactionPaymentAmount();
        $arPaymentFields[$dataDateReceivedColumnName] = $dataObject[$paymentObjectName]->getReceivedDate()->format($dateReceivedFormat);
        $arPaymentFields[$dataPaymentMethodColumnName] = getPaymentMethodConstantFromString($dataObject[$paymentObjectName]->getPaymentMethod());
        $arPaymentFields[$dataBankAccountIdColumnName] = $dataObject[$paymentObjectName]->getBankAccountId();
        $arPaymentFields[$dataUndepositedFundsGlAccountNumberColumnName] = $dataObject[$paymentObjectName]->getUndepositedFundsGlAccountNo();
        $arPaymentFields[$dataOverpaymentLocationIdColumnName] = $dataObject[$paymentObjectName]->getOverpaymentLocationId();
        $arPaymentApplyToTransactions = $dataObject[$paymentObjectName]->getApplyToTransactions();
        if(empty($arPaymentApplyToTransactions)){
            $arPaymentFields[$dataApplyToInvoiceNumber] = '';
            $arPaymentFields[$dataApplyToInvoiceAmount] = '';
        }
        else{
            $invoiceNumbers = $invoiceNumbersByArPaymentControlId[$dataObject[$paymentObjectName]->getControlId()];
            $firstPaymentItem = array_shift($arPaymentApplyToTransactions);
            $firstInvoiceNumber = array_shift($invoiceNumbers);
            $arPaymentFields[$dataApplyToInvoiceNumber] = $firstInvoiceNumber;
            $arPaymentFields[$dataApplyToInvoiceAmount] = $firstPaymentItem->getAmountToApply();
        }
        // BUILD ORDERED TRANSACTION ROW
        $orderedTransactionRow = array();
        foreach($orderedDataColumnNames as $orderedDataColumnName){
            $orderedTransactionRow[] = $arPaymentFields[$orderedDataColumnName];
        }
        foreach($infoColumnNames as $infoColumnName){
            $orderedTransactionRow[] = $dataObject[$infoColumnName];
        }
        fputcsv($fileHandle, $orderedTransactionRow);
        $objectCount++;
        // BUILD ORDERED ADDITIONAL PAYMENT ITEM ROWS
        while(!empty($arPaymentApplyToTransactions)){
            $orderedAdditionalPaymentItemRow = array();
            $additionalPaymentItem = array_shift($arPaymentApplyToTransactions);
            $additionalInvoiceNumber = array_shift($invoiceNumbers);
            foreach($orderedDataColumnNames as $orderedDataColumnName){
                if($orderedDataColumnName === $dataApplyToInvoiceNumber){
                    $orderedAdditionalPaymentItemRow[] = $additionalInvoiceNumber;
                }
                else if($orderedDataColumnName === $dataApplyToInvoiceAmount){
                    $orderedAdditionalPaymentItemRow[] = $additionalPaymentItem->getAmountToApply();
                }
                else{
                    $orderedAdditionalPaymentItemRow[] = '';
                }
            }
            foreach($infoColumnNames as $infoColumnName){
                $orderedAdditionalPaymentItemRow[] = '';
            }
            fputcsv($fileHandle, $orderedAdditionalPaymentItemRow);
        }
    }
    return $objectCount;
}

function addArPaymentItemWithAmountToArPayment($arPayment, $invoiceAmount){
    $arPaymentItem = new ArPaymentItem();
    $arPaymentItem->setAmountToApply($invoiceAmount);
    $applyToTransactions = $arPayment->getApplyToTransactions();
    $applyToTransactions[] = $arPaymentItem;
    $arPayment->setApplyToTransactions($applyToTransactions);
}

function storeArPaymentInvoiceNumber($arPayment, $applyToInvoiceNumber, &$invoiceNumbersByArPaymentControlId){
    if(!isset($invoiceNumbersByArPaymentControlId[$arPayment->getControlId()])){
        $invoiceNumbersByArPaymentControlId[$arPayment->getControlId()] = array();
    }
    $invoiceNumbersByArPaymentControlId[$arPayment->getControlId()][] = $applyToInvoiceNumber;
}

function storeInvoiceNumbersFromArPaymentItems($arPayment, $invoiceNumbersByArPaymentControlId){
    $invoiceNumbers = array();
    $applyToTransactions = $arPayment->getApplyToTransactions();
    foreach($applyToTransactions as $arPaymentItem){
        $invoiceNumbers[] = $arPaymentItem->getApplyToRecordId();
    }
    $invoiceNumbersByArPaymentControlId[$arPayment->getControlId()] = $invoiceNumbers;
}

function translateInvoiceNumbersToRecordNumbersInArPaymentItems($arPayment, $recordNumbersByInvoiceNumbers){
    $applyToTransactions = $arPayment->getApplyToTransactions();
    foreach($applyToTransactions as $arPaymentItem){
        $invoiceNumber = $arPaymentItem->getApplyToRecordId();
        if(isset($recordNumbersByInvoiceNumbers[$invoiceNumber])){
            $recordNumber = $recordNumbersByInvoiceNumbers[$invoiceNumber];
        }
        else{
            $recordNumber = NULL;
        }
        $arPaymentItem->setApplyToRecordId($recordNumber);
    }
}

function getPaymentMethodConstantFromString($s){
    switch($s){
        case 'Printed Check': return 'PAYMENT_METHOD_CHECK';
        case 'Cash': return 'PAYMENT_METHOD_CASH';
        case 'EFT': return 'PAYMENT_METHOD_RECORD_TRANSFER';
        case 'Credit Card': return 'PAYMENT_METHOD_CREDIT_CARD';
        case 'Online': return 'PAYMENT_METHOD_ONLINE';
        case 'Online Charge Card': return 'PAYMENT_METHOD_ONLINE_CREDIT_CARD';
        case 'Online ACH Debit': return 'PAYMENT_METHOD_ONLINE_ACH_DEBIT';
        default: return 'ERROR';
    }
}

function getPaymentMethodStringFromConstant($c){
    switch($c){
        case 'PAYMENT_METHOD_CHECK':
        case 'PAYMENT_METHOD_CASH':
        case 'PAYMENT_METHOD_RECORD_TRANSFER':
        case 'PAYMENT_METHOD_CREDIT_CARD':
        case 'PAYMENT_METHOD_ONLINE':
        case 'PAYMENT_METHOD_ONLINE_CREDIT_CARD':
        case 'PAYMENT_METHOD_ONLINE_ACH_DEBIT':
            return constant("Intacct\Functions\AccountsReceivable\ArPaymentCreate::$c");
        default: return NULL;
    }
}

function getRecordNumbersByInvoiceNumbers($invoiceNumbersByArPaymentControlId, $client, $logger){
    $allInvoiceNumbers = array();
    foreach($invoiceNumbersByArPaymentControlId as $key=>$value){
        foreach($value as $invoiceNumber){
            $allInvoiceNumbers[] = "'" . $invoiceNumber . "'";
        }
    }
    $queryString = new QueryString('RECORDID in (' . implode(',', $allInvoiceNumbers) . ')');
    $readByQuery = new ReadByQuery();
    $readByQuery->setObjectName('ARINVOICE');
    $readByQuery->setFields(array('RECORDNO', 'RECORDID'));
    $readByQuery->setQuery($queryString);
    try{
        // EXECUTE
        $logger->info('Executing batch query to Intacct API');
        $response = $client->execute($readByQuery);
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
    }
    catch (\Intacct\Exception\ResponseException $ex){
        $logger->error('An Intacct response exception was thrown', [
            get_class($ex) => $ex->getMessage(),
            'Errors' => $ex->getErrors(),
        ]);
        exit('Error: could not translate an invoice_number (' . $invoiceNumber . ") into a record number\n");
    }
    catch (\Exception $ex){
        $logger->error('An exception was thrown', [
            get_class($ex) => $ex->getMessage(),
        ]);
        exit('Error: could not translate an invoice_number (' . $invoiceNumber . ") into a record number\n");
    }
    $objects = json_decode(json_encode($result->getData()), 1);
    $recordNumbers = array();
    foreach($objects as $object){
        $recordNumbers[$object['RECORDID']] = $object['RECORDNO'];
    }
    return $recordNumbers;
}