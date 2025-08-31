<?php

require __DIR__ . '/JsonataServer.php';

// Start server mode
$server = new JsonataSocketServer();

// Handle graceful shutdown
pcntl_signal(SIGINT, function() use ($server) {
    echo "\nShutting down server...\n";
    $server->stop();
    exit(0);
});

pcntl_signal(SIGTERM, function() use ($server) {
    echo "\nReceived SIGTERM, shutting down server...\n";
    $server->stop();
    exit(0);
});

echo "Starting JSONata PHP Server...\n";
echo "Press Ctrl+C to stop the server\n\n";

$server->start();
