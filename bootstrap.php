<?php
/**
 * Copyright 2018 Sage Intacct, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"). You may not
 * use this file except in compliance with the License. You may obtain a copy
 * of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * or in the "LICENSE" file accompanying this file. This file is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

$loader = require __DIR__ . '/vendor/autoload.php';

use Intacct\OnlineClient;
use Intacct\ClientConfig;

$handler = new \Monolog\Handler\StreamHandler(__DIR__ . '/logs/intacct.html');
$handler->setFormatter(new \Monolog\Formatter\HtmlFormatter());

$logger = new \Monolog\Logger('intacct-sdk-php-examples');
$logger->pushHandler($handler);

$clientConfig = new ClientConfig();
$clientConfig->setProfileFile(__DIR__ . '/.credentials.ini');
$clientConfig->setLogger($logger);

$client = new OnlineClient($clientConfig);

$formatter = new \Intacct\Logging\MessageFormatter(
    '"{method} {target} HTTP/{version}" {code}'
);
$client->getConfig()->setLogLevel(\Psr\Log\LogLevel::INFO);
$client->getConfig()->setLogMessageFormatter($formatter);