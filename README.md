# JSONata-PHP

A PHP implementation of the [JSONata query and transformation language](http://jsonata.org/). This library allows you to query, transform, and manipulate JSON data structures in your PHP applications using the powerful and intuitive JSONata syntax.

## What is JSONata?

JSONata is a lightweight query and transformation language for JSON data. Inspired by XPath 3.1, it provides a powerful way to extract and reshape data from any JSON document. It's perfect for complex data manipulation, templating, and data mapping tasks.

## Features

- Support for basic JSONata queries.
- Map operator for simple transformations.
- Conditional logic.
- Simple, intuitive API.
- No external dependencies.
- Robust error handling.

## Installation

You can install the package via [Composer](https://getcomposer.org/):

```bash
composer require monster-anshu/jsonata-php
```

## Usage

Using the library is straightforward. You create an instance of the `Jsonata` class, provide your JSONata expression, and then evaluate it against your data.

### Basic Example

Here's a simple example of how to extract the `name` of each product from a JSON object.

```php
<?php

require __DIR__ . 'vendor/autoload.php';

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
```

**Expected Output:**

```bash
Array
(
    [id] => A1
    [total] => 200
)
```

## Testing

On Progress

## Contributing

Contributions are welcome! Please feel free to submit a pull request or open an issue for any bugs or feature requests.

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally (`git clone https://github.com/your-username/jsonata-php.git`).
3. **Create** a new branch (`git checkout -b my-feature`).
4. **Commit** your changes (`git commit -am 'Add some feature'`).
5. **Push** to the branch (`git push origin my-feature`).
6. **Create** a new Pull Request.

## License

The MIT License (MIT). Please see the [License File](LICENSE.md "null") for more information.