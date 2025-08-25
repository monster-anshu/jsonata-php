<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Jsonata;
use Monster\JsonataPhp\Parser;
use Monster\JsonataPhp\Tokenizer;

const expression = '
Account.Order.Product[Price > 50].{ "id": ProductID, "total": Price * Quantity }
';

const input = [
    "Account" => [
        "Order" => [
            "Product" => [
                ["ProductID" => "A1", "Price" => 100, "Quantity" => 2],
                ["ProductID" => "A2", "Price" => 30, "Quantity" => 1],
            ],
        ],
    ],
];


$jsonata = new Jsonata(expression);
print_r($jsonata->evaluate(input));

// $tokenizer = new Tokenizer(expression);
// print_r(json_encode($tokenizer->tokens()) . "\n");

// $parser = new Parser();
// print_r(json_encode($parser->parse(expression),JSON_PRETTY_PRINT));
