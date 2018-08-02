<?php

require __DIR__ . '/bootstrap.php';

use Intacct\Functions\AccountsReceivable\ArPaymentCreate;

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

// ROW NAMES
$paymentAmountRowName = 'payment_amount';
$customerAccountIdRowName = 'customer_account_id';
$dateReceivedRowName = 'date_received';
$paymentMethodRowName = 'payment_method';
$bankAccountIdRowName = 'bank_account_id';
$overpaymentLocationIdRowName = 'overpayment_location_id';
$failureMessageRowName = 'failure_message';
$failureClassRowName = 'failure_class';
$failureErrorRowName = 'failure_error';
$timestampRowName = 'time_stamp';
// ROW INDICES (IN INSERTION ORDER)
$rowIndicesByRowName;
// INPUT FILE STATE
$csvInputFilePath = NULL;
$csvInputFileHandle = NULL;
$csvInputFileDataRow = NULL;
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
// DATA ROWS
$dataInputRows = NULL;
$dataInputRowNames = NULL;
$dataInputSuccessRows = NULL;
$dataInputFailureRows = NULL;

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

// GET FIRST ROW OF DATA (HEADER)
$csvInputFileDataRow = fgetcsv($csvInputFileHandle);
if($csvInputFileDataRow === FALSE){
    exit("Error: unable to get first row from CSV\n");
}

// GET PAYMENT AMOUNT & CUSTOMER ACCOUNT ID INDICES
$rowIndicesByRowName = array();
for($i=0; $i<count($csvInputFileDataRow); $i++){
    $csvFileDataCell = $csvInputFileDataRow[$i];
    if($csvFileDataCell === $customerAccountIdRowName){
        $rowIndicesByRowName[$customerAccountIdRowName] = $i;
    }
    else if($csvFileDataCell === $paymentAmountRowName){
        $rowIndicesByRowName[$paymentAmountRowName] = $i;
    }
    else if($csvFileDataCell === $dateReceivedRowName){
        $rowIndicesByRowName[$dateReceivedRowName] = $i;
    }
    else if($csvFileDataCell === $paymentMethodRowName){
        $rowIndicesByRowName[$paymentMethodRowName] = $i;
    }
    else if($csvFileDataCell === $bankAccountIdRowName){
        $rowIndicesByRowName[$bankAccountIdRowName] = $i;
    }
    else if($csvFileDataCell === $overpaymentLocationIdRowName){
        $rowIndicesByRowName[$overpaymentLocationIdRowName] = $i;
    }
}
if($rowIndicesByRowName[$paymentAmountRowName] === NULL){
    exit('Error: unable to find column named: ' . $paymentAmountRowName . "\n");
}
else if($rowIndicesByRowName[$customerAccountIdRowName] === NULL){
    exit('Error: unable to find column named: ' . $customerAccountIdRowName . "\n");
}
else if($rowIndicesByRowName[$dateReceivedRowName] === NULL){
    exit('Error: unable to find column named: ' . $dateReceivedRowName . "\n");
}
else if($rowIndicesByRowName[$paymentMethodRowName] === NULL){
    exit('Error: unable to find column named: ' . $paymentMethodRowName . "\n");
}
else if($rowIndicesByRowName[$bankAccountIdRowName] === NULL){
    exit('Error: unable to find column named: ' . $bankAccountIdRowName . "\n");
}
else if($rowIndicesByRowName[$overpaymentLocationIdRowName] === NULL){
    exit('Error: unable to find column named: ' . $overpaymentLocationIdRowName . "\n");
}

// READ AND VALIDATE ROWS
$rowIndex = 2;
$rowReadCount = 0;
$dataInputRows = array();
while(($csvInputFileDataRow = fgetcsv($csvInputFileHandle)) !== FALSE){
    $customerAccountId = trim($csvInputFileDataRow[$rowIndicesByRowName[$customerAccountIdRowName]]);
    $paymentAmount = trim($csvInputFileDataRow[$rowIndicesByRowName[$paymentAmountRowName]]);
    $dateReceived = trim($csvInputFileDataRow[$rowIndicesByRowName[$dateReceivedRowName]]);
    $paymentMethod = trim($csvInputFileDataRow[$rowIndicesByRowName[$paymentMethodRowName]]);
    $bankAccountId = trim($csvInputFileDataRow[$rowIndicesByRowName[$bankAccountIdRowName]]);
    $overpaymentLocationId = trim($csvInputFileDataRow[$rowIndicesByRowName[$overpaymentLocationIdRowName]]);
    // VERIFY ALL FIELDS ARE NOT EMPTY
    if(strlen($paymentAmount) > 0
        && strlen($customerAccountId) > 0){
        // VERIFY PAYMENT AMOUNT IS NUMERIC
        if(!is_numeric($paymentAmount)){
            exit('Error: payment amount is not a number, at row: ' . $rowIndex . "\n");
        }
        else{
            $rowReadCount++;
            // ADD ROW TO ROWS
            $dataInputRow = array();
            $dataInputRow[$customerAccountIdRowName] = $customerAccountId;
            $dataInputRow[$paymentAmountRowName] = $paymentAmount;
            $dataInputRow[$dateReceivedRowName] = $dateReceived;
            $dataInputRow[$paymentMethodRowName] = $paymentMethod;
            $dataInputRow[$bankAccountIdRowName] = $bankAccountId;
            $dataInputRow[$overpaymentLocationIdRowName] = $overpaymentLocationId;
            $dataInputRows[] = $dataInputRow;
        }
    }
    else{
        exit('Error: a field is empty, at row: ' + $rowIndex . "\n");
    }
    // INCREMENT ROW COUNTER
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
        $customerAccountId = $dataInputRow[$customerAccountIdRowName];
        $paymentAmount = $dataInputRow[$paymentAmountRowName];
        $dateReceived = $dataInputRow[$dateReceivedRowName];
        $paymentMethod = $dataInputRow[$paymentMethodRowName];
        $bankAccountId = $dataInputRow[$bankAccountIdRowName];
        $overpaymentLocationId = $dataInputRow[$overpaymentLocationIdRowName];
        $arPaymentCreate = new ArPaymentCreate();
        $arPaymentCreate->setCustomerId($customerAccountId);
        $arPaymentCreate->setTransactionPaymentAmount($paymentAmount);
        $arPaymentCreate->setReceivedDate(new DateTime($dateReceived));
        $arPaymentCreate->setPaymentMethod(constant("Intacct\Functions\AccountsReceivable\ArPaymentCreate::$paymentMethod"));
        $arPaymentCreate->setBankAccountId($bankAccountId);
        if(strlen($overpaymentLocationId) > 0){
            $arPaymentCreate->setOverpaymentLocationId($overpaymentLocationId);
        }
        // LOG TRANSACTION TIMESTAMP
        $timestamp = date('Y-m-d h:i:s a ') . date_default_timezone_get();
        $dataInputRow[$timestampRowName] = $timestamp;
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
        $dataInputRow[$failureClassRowName] = get_class($ex);
        $dataInputRow[$failureMessageRowName] = $ex->getMessage();
        $dataInputRow[$failureErrorRowName] = print_r($ex->getErrors(), TRUE);
        // REPLACE COMMAS
        $dataInputRow[$failureClassRowName] = str_replace(',', '.', $dataInputRow[$failureClassRowName]);
        $dataInputRow[$failureMessageRowName] = str_replace(',', '.', $dataInputRow[$failureMessageRowName]);
        $dataInputRow[$failureErrorRowName] = str_replace(',', '.', $dataInputRow[$failureErrorRowName]);
        $dataInputFailureRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo 'f';
    }
    catch (\Exception $ex) {
        $logger->error('An exception was thrown', [
            get_class($ex) => $ex->getMessage(),
        ]);
        // STORE ROW (WITH FAILURE INFO) FOR OUTPUT
        $dataInputRow[$failureClassRowName] = get_class($ex);
        $dataInputRow[$failureMessageRowName] = $ex->getMessage();
        $dataInputRow[$failureErrorRowName] = '';
        // REPLACE COMMAS
        $dataInputRow[$failureClassRowName] = str_replace(',', '.', $dataInputRow[$failureClassRowName]);
        $dataInputRow[$failureMessageRowName] = str_replace(',', '.', $dataInputRow[$failureMessageRowName]);
        $dataInputFailureRows[] = $dataInputRow;
        // OUTPUT MESSAGE
        echo 'f';
    }
}

echo "\n";

// GENERATE DATA FOR FILE OUTPUT
$dataInputRowNames = array();
foreach($rowIndicesByRowName as $rowName=>$rowIndex){
    $dataInputRowNames[] = $rowName;
}

// OUTPUT SUCCESS FILE
$uploadSuccessCount = 0;
$dataInputRowNames[] = $timestampRowName;
fputcsv($csvOutputSuccessFileHandle, $dataInputRowNames);
foreach($dataInputSuccessRows as $dataInputSuccessRow){
    $orderedRow = array();
    foreach($dataInputRowNames as $dataInputRowName){
        $orderedRow[] = $dataInputSuccessRow[$dataInputRowName];
    }
    $uploadSuccessCount++;
    fputcsv($csvOutputSuccessFileHandle, $orderedRow);
}

// OUTPUT FAILURE FILE
$uploadFailureCount = 0;
$dataInputRowNames[] = $failureClassRowName;
$dataInputRowNames[] = $failureMessageRowName;
$dataInputRowNames[] = $failureErrorRowName;
fputcsv($csvOutputFailureFileHandle, $dataInputRowNames);
foreach($dataInputFailureRows as $dataInputFailureRow){
    $orderedRow = array();
    foreach($dataInputRowNames as $dataInputRowName){
        $orderedRow[] = $dataInputFailureRow[$dataInputRowName];
    }
    $uploadFailureCount++;
    fputcsv($csvOutputFailureFileHandle, $orderedRow);
}

// OUTPUT RESULT COUNTS
echo 'Upload Success Row Count: ' . $uploadSuccessCount . "\n";
echo 'Upload Failure Row Count: ' . $uploadFailureCount . "\n";

