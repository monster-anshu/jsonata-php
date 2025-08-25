<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Jsonata;

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
$result = $jsonata->evaluate(input);
print_r($result);