<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Jsonata;
use Monster\JsonataPhp\Parser;
use Monster\JsonataPhp\Tokenizer;

const expression = 'Account.Order,,';

// $jsonata = new Jsonata(expression);
// print_r($jsonata->evaluate([
//     'Account' => [
//         'Order' => 'Laptop'
//     ]
// ]));

$tokenizer = new Tokenizer(expression);
print_r(json_encode($tokenizer->tokens(),JSON_PRETTY_PRINT));

// $parser = new Parser();
// print_r(json_encode($parser->parse(expression),JSON_PRETTY_PRINT));
