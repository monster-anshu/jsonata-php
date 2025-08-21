<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Parser;

const expression = 'hello';

$parser = new Parser();
$ast = $parser->parse(expression);
print_r(json_encode($ast,JSON_PRETTY_PRINT));