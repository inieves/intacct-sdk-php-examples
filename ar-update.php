<?php

require __DIR__ . '/bootstrap.php';

use Intacct\Functions\AccountsReceivable\ArPaymentCreate;

// ROW NAMES
$paymentAmountRowName = 'payment_amount';
$customerAccountIdRowName = 'customer_account_id';
$dateReceivedRowName = 'date_received';
$paymentMethodRowName = 'payment_method';
$bankAccountIdRowName = 'bank_account_id';
// ROW IDS
$paymentAmountRowIndex = NULL;
$customerAccountIdRowIndex = NULL;
$dateReceivedRowIndex = NULL;
$paymentMethodRowIndex = NULL;
$bankAccountIdRowIndex = NULL;
// FILE STATE
$csvFilePath = NULL;
$csvFileHandle = NULL;
$csvFileDataRow = NULL;
// DATA STATE
$dataRows = NULL;

// GET CSV FILE PATH
if(count($argv) !== 2){
    exit("Error: missing CSV file path\n");
}
$csvFilePath = $argv[1];

// GET INPUT FILE HANDLE
$csvFileHandle = @fopen($csvFilePath, "r");
if($csvFileHandle === FALSE){
    exit("Error: unable to open CSV file for reading\n");
}

// GET FIRST ROW OF DATA (HEADER)
$csvFileDataRow = fgetcsv($csvFileHandle);
if($csvFileDataRow === FALSE){
    exit("Error: unable to get first row from CSV\n");
}

// GET PAYMENT AMOUNT & CUSTOMER ACCOUNT ID INDICES
for($i=0; $i<count($csvFileDataRow); $i++){
    $csvFileDataCell = $csvFileDataRow[$i];
    if($csvFileDataCell === $paymentAmountRowName){
        $paymentAmountRowIndex = $i;
    }
    else if($csvFileDataCell === $customerAccountIdRowName){
        $customerAccountIdRowIndex = $i;
    }
    else if($csvFileDataCell === $dateReceivedRowName){
        $dateReceivedRowIndex = $i;
    }
    else if($csvFileDataCell === $paymentMethodRowName){
        $paymentMethodRowIndex = $i;
    }
    else if($csvFileDataCell === $bankAccountIdRowName){
        $bankAccountIdRowIndex = $i;
    }
}
if($paymentAmountRowIndex === NULL){
    exit('Error: unable to find column named: ' . $paymentAmountRowName . "\n");
}
else if($customerAccountIdRowIndex === NULL){
    exit('Error: unable to find column named: ' . $customerAccountIdRowName . "\n");
}
else if($dateReceivedRowIndex === NULL){
    exit('Error: unable to find column named: ' . $dateReceivedRowName . "\n");
}
else if($paymentMethodRowIndex === NULL){
    exit('Error: unable to find column named: ' . $paymentMethodRowName . "\n");
}
else if($bankAccountIdRowIndex === NULL){
    exit('Error: unable to find column named: ' . $bankAccountIdRowName . "\n");
}

// READ AND VALIDATE ROWS
$rowIndex = 2;
$rowReadCount = 0;
$dataRows = array();
while(($csvFileDataRow = fgetcsv($csvFileHandle)) !== FALSE){
    $customerAccountId = trim($csvFileDataRow[$customerAccountIdRowIndex]);
    $paymentAmount = trim($csvFileDataRow[$paymentAmountRowIndex]);
    $dateReceived = trim($csvFileDataRow[$dateReceivedRowIndex]);
    $paymentMethod = trim($csvFileDataRow[$paymentMethodRowIndex]);
    $bankAccountId = trim($csvFileDataRow[$bankAccountIdRowIndex]);
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
            $dataRow = array();
            $dataRow[$customerAccountIdRowName] = $customerAccountId;
            $dataRow[$paymentAmountRowName] = $paymentAmount;
            $dataRow[$dateReceivedRowName] = $dateReceived;
            $dataRow[$paymentMethodRowName] = $paymentMethod;
            $dataRow[$bankAccountIdRowName] = $bankAccountId;
            $dataRows[] = $dataRow;
        }
    }
    else{
        exit('Error: a field is empty, at row: ' + $rowIndex . "\n");
    }
    // INCREMENT ROW COUNTER
    $rowIndex++;
}
fclose($csvFileHandle);
echo "data row stats\n";
echo "\tread: " . $rowReadCount . "\n";

// EXIT IF NOTHING READ
if(count($dataRows) === 0){
    exit("Error: no rows read\n");
}


try {
    // PROCESS EACH ROW
    foreach($dataRows as $dataRow) {
        // CREATE NEW AR PAYMENT
        $customerAccountId = $dataRow[$customerAccountIdRowName];
        $paymentAmount = $dataRow[$paymentAmountRowName];
        $dateReceived = $dataRow[$dateReceivedRowName];
        $paymentMethod = $dataRow[$paymentMethodRowName];
        $bankAccountId = $dataRow[$bankAccountIdRowName];
        $arPaymentCreate = new ArPaymentCreate();
        $arPaymentCreate->setCustomerId($customerAccountId);
        $arPaymentCreate->setTransactionPaymentAmount($paymentAmount);
        $arPaymentCreate->setReceivedDate(new DateTime($dateReceived));
        $arPaymentCreate->setPaymentMethod(constant("Intacct\Functions\AccountsReceivable\ArPaymentCreate::$paymentMethod"));
        $arPaymentCreate->setBankAccountId($bankAccountId);
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
    }
} catch (\Intacct\Exception\ResponseException $ex) {
    $logger->error('An Intacct response exception was thrown', [
        get_class($ex) => $ex->getMessage(),
        'Errors' => $ex->getErrors(),
    ]);
    echo 'Failed! ' . $ex->getMessage();
} catch (\Exception $ex) {
    $logger->error('An exception was thrown', [
        get_class($ex) => $ex->getMessage(),
    ]);
    echo get_class($ex) . ': ' . $ex->getMessage();
}
