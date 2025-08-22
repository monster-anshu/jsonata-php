<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Jsonata;

const expression = 'Account.Order';

$jsonata = new Jsonata(expression);
print_r(json_encode($jsonata->ast,JSON_PRETTY_PRINT));
