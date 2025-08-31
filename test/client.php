<?php

require __DIR__ . '/JsonataServer.php';

// Example of using the JsonataServer class programmatically
class JsonataService
{
    private JsonataSocketServer $server;
    
    public function __construct(string $socketPath = '/tmp/jsonata.sock')
    {
        $this->server = new JsonataSocketServer($socketPath);
    }
    
    public function start(): void
    {
        echo "Starting JSONata service...\n";
        $this->server->start();
    }
    
    public function stop(): void
    {
        echo "Stopping JSONata service...\n";
        $this->server->stop();
    }
}

// Example usage
if (php_sapi_name() === 'cli') {
    $service = new JsonataService();
    
    // Handle graceful shutdown
    pcntl_signal(SIGINT, function() use ($service) {
        echo "\nShutting down service...\n";
        $service->stop();
        exit(0);
    });
    
    $service->start();
} else {
    echo "This script is designed to run from command line.\n";
    echo "Usage: php example.php\n";
}
