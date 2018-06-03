<?php

require __DIR__ . '/bootstrap.php';

use Intacct\Functions\AccountsReceivable\CustomerCreate;
use Intacct\Functions\AccountsReceivable\CustomerDelete;
use Intacct\Functions\AccountsReceivable\CustomerUpdate;
use Intacct\Functions\Common\Read;
use Intacct\Functions\Common\ReadByName;

// STATE
$paymentAmountRowName = 'payment_amount';
$customerAccountIdRowName = 'customer_account_id';
$csvFilePath = NULL;
$csvFileHandle = NULL;
$csvFileDataRow = NULL;
$paymentAmountRowIndex = NULL;
$customerAccountIdRowIndex = NULL;
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
}
if($paymentAmountRowIndex === NULL){
    exit('Error: unable to find column named: ' . $paymentAmountRowName . "\n");
}
else if($customerAccountIdRowIndex === NULL){
    exit('Error: unable to find column named: ' . $customerAccountIdRowName . "\n");
}

// READ AND VALIDATE ROWS
$rowIndex = 2;
$rowReadCount = 0;
$rowSkipCount = 0;
$dataRows = array();
while(($csvFileDataRow = fgetcsv($csvFileHandle)) !== FALSE){
    $paymentAmount = trim($csvFileDataRow[$paymentAmountRowIndex]);
    $customerAccountId = trim($csvFileDataRow[$customerAccountIdRowIndex]);
    // VERIFY BOTH FIELDS ARE NOT EMPTY
    if(strlen($paymentAmount) > 0
        || strlen($customerAccountId) > 0){
        // VERIFY PAYMENT AMOUNT IS NUMERIC
        if(!is_numeric($paymentAmount)){
            exit('Error: payment amount is not a number, at row: ' . $rowIndex . "\n");
        }
        else{
            $rowReadCount++;
            // ADD ROW TO ROWS
            $dataRow = array();
            $dataRow[$paymentAmountRowName] = $paymentAmount;
            $dataRow[$customerAccountIdRowName] = $customerAccountId;
            $dataRows[] = $dataRow;
        }
    }
    else{
        $rowSkipCount++;
    }
    // INCREMENT ROW COUNTER
    $rowIndex++;
}
fclose($csvFileHandle);
echo "data row stats\n";
echo "\tread: " . $rowReadCount . "\n";
echo "\tempty: " . $rowSkipCount . "\n";

// EXIT IF NOTHING READ
if(count($dataRows) === 0){
    exit("Error: no rows read\n");
}

// PROCESS ROWS
// ...
