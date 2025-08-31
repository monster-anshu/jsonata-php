<?php

require __DIR__ . '/JsonataServer.php';

echo "Starting JSONata PHP Server...\n";
echo "Press Ctrl+C to stop the server\n\n";

// Start server mode
$server = new JsonataSocketServer();
$server->start();
