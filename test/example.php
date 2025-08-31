<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Jsonata;

// Test mode - run the original example
$expression = '
Account.Order.Product[Price > 50].{ "id": ProductID, "total": Price * Quantity }
';
$expression = '
Account.Order.Product[Price > 50]
';

$input = [
    "Account" => [
        "Order" => [
            "Product" => [
                ["ProductID" => "A1", "Price" => 100, "Quantity" => 2],
                ["ProductID" => "A2", "Price" => 30, "Quantity" => 1],
                ["ProductID" => "A2", "Price" => 300, "Quantity" => 1],
                ["ProductID" => "A2", "Price" => 309, "Quantity" => 1],
            ],
        ],
    ],
];

$jsonata = new Jsonata($expression);
$result = $jsonata->evaluate($input);
echo "Test result:\n";
print_r(($result));
// print_r(json_encode($result, JSON_PRETTY_PRINT));

echo "\nTo start the server, run: php start-server.php\n";