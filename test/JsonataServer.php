<?php

require __DIR__ . '/../vendor/autoload.php';

use Monster\JsonataPhp\Jsonata;

class JsonataSocketServer
{
    private string $socketPath;
    private $socket;
    private bool $running = false;

    public function __construct(string $socketPath = '/tmp/jsonata.sock')
    {
        $this->socketPath = $socketPath;
    }

    public function start(): void
    {
        // Set up signal handling
        $this->setupSignalHandling();
        
        // Remove existing socket file if it exists
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }

        // Create Unix domain socket
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->socket === false) {
            throw new Exception("Failed to create socket: " . socket_strerror(socket_last_error()));
        }

        // Set socket options for better reliability
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1);

        // Bind socket to path
        if (!socket_bind($this->socket, $this->socketPath)) {
            throw new Exception("Failed to bind socket: " . socket_strerror(socket_last_error($this->socket)));
        }

        // Listen for connections
        if (!socket_listen($this->socket, 5)) {
            throw new Exception("Failed to listen on socket: " . socket_strerror(socket_last_error($this->socket)));
        }

        // Set socket permissions
        chmod($this->socketPath, 0666);

        echo "JSONata PHP Server started on: {$this->socketPath}\n";
        echo "Waiting for connections...\n";

        $this->running = true;
        $this->handleConnections();
    }

    private function handleConnections(): void
    {
        while ($this->running) {
            // Check for signals before accepting connections
            pcntl_signal_dispatch();
            
            // Use socket_select to make socket_accept interruptible
            $read = [$this->socket];
            $write = [];
            $except = [];
            
            $ready = socket_select($read, $write, $except, 1); // 1 second timeout
            
            if ($ready === false) {
                // Error in select
                continue;
            }
            
            if ($ready === 0) {
                // Timeout, check if we should still be running
                pcntl_signal_dispatch();
                continue;
            }
            
            $client = socket_accept($this->socket);
            if ($client === false) {
                continue;
            }

            // echo "Client connected\n";

            // Set socket options for the client
            socket_set_option($client, SOL_SOCKET, SO_KEEPALIVE, 1);
            socket_set_option($client, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 30, 'usec' => 0]);
            socket_set_option($client, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 30, 'usec' => 0]);

            // Handle client in a separate process
            $pid = pcntl_fork();
            if ($pid === -1) {
                // Fork failed
                socket_close($client);
                continue;
            } elseif ($pid === 0) {
                // Child process
                $this->handleClient($client);
                exit(0);
            } else {
                // Parent process
                socket_close($client);
            }
        }
    }

    private function handleClient($client): void
    {
        try {
            while (true) {
                // Read request length (4 bytes)
                $lengthData = socket_read($client, 4);
                if ($lengthData === false || strlen($lengthData) === 0) {
                    // echo "Client disconnected or no data received\n";
                    break;
                }

                // Check if we received exactly 4 bytes
                if (strlen($lengthData) !== 4) {
                    // echo "Invalid length header received: " . strlen($lengthData) . " bytes\n";
                    break;
                }

                $unpacked = unpack('N', $lengthData);
                if ($unpacked === false) {
                    // echo "Failed to unpack length data\n";
                    break;
                }
                
                $length = $unpacked[1];
                
                // Validate length
                if ($length <= 0 || $length > 1024 * 1024) { // Max 1MB
                    // echo "Invalid request length: $length\n";
                    break;
                }
                
                // Read request data
                $requestData = '';
                $bytesRead = 0;
                $timeout = time() + 30; // 30 second timeout
                
                while ($bytesRead < $length && time() < $timeout) {
                    $chunk = socket_read($client, $length - $bytesRead);
                    if ($chunk === false) {
                        echo "Error reading request data\n";
                        break 2;
                    }
                    if (strlen($chunk) === 0) {
                        // No data available, wait a bit
                        usleep(1000); // 1ms
                        continue;
                    }
                    $requestData .= $chunk;
                    $bytesRead += strlen($chunk);
                }

                if (strlen($requestData) !== $length) {
                    // echo "Incomplete request received: expected $length, got " . strlen($requestData) . "\n";
                    break;
                }

                // Parse request
                $request = json_decode($requestData, true);
                if ($request === null) {
                    $this->sendResponse($client, ['error' => 'Invalid JSON request']);
                    continue;
                }

                // Execute JSONata expression
                $result = $this->executeJsonata($request);
                
                // Send response
                $this->sendResponse($client, $result);

            }
        } catch (Exception $e) {
            echo "Error handling client: " . $e->getMessage() . "\n";
            try {
                $this->sendResponse($client, ['error' => $e->getMessage()]);
            } catch (Exception $sendError) {
                echo "Failed to send error response: " . $sendError->getMessage() . "\n";
            }
        } finally {
            socket_close($client);
        }
    }

    private function executeJsonata(array $request): array
    {
        try {
            if (!isset($request['expression']) || !isset($request['data'])) {
                return ['error' => 'Missing expression or data in request'];
            }

            $expression = $request['expression'];
            $data = $request['data'];

            $jsonata = new Jsonata($expression);
            $result = $jsonata->evaluate($data);

            return [
                'success' => true,
                'result' => $result
            ];

        } catch (Exception $e) {
            // exit on error
            return [
                'success' => false,
                // 'error' => $e->getMessage()
            ];
        }
    }

    private function sendResponse($client, array $response): void
    {
        try {
            $responseData = json_encode($response);
            if ($responseData === false) {
                // echo "Failed to encode response to JSON\n";
                return;
            }
            
            $length = strlen($responseData);
            
            // Send length (4 bytes)
            $lengthBytes = pack('N', $length);
            $bytesWritten = socket_write($client, $lengthBytes, 4);
            if ($bytesWritten !== 4) {
                // echo "Failed to write length header: expected 4, wrote $bytesWritten\n";
                return;
            }
            
            // Send response data
            $bytesWritten = socket_write($client, $responseData, $length);
            if ($bytesWritten !== $length) {
                // echo "Failed to write response data: expected $length, wrote $bytesWritten\n";
                return;
            }
            
        } catch (Exception $e) {
            // echo "Error sending response: " . $e->getMessage() . "\n";
        }
    }

    private function setupSignalHandling(): void
    {
        // Handle graceful shutdown signals
        pcntl_signal(SIGINT, function() {
            // echo "\nReceived SIGINT (Ctrl+C), shutting down server...\n";
            $this->running = false;
        });
        
        pcntl_signal(SIGTERM, function() {
            echo "\nReceived SIGTERM, shutting down server...\n";
            $this->running = false;
        });
    }

    public function stop(): void
    {
        $this->running = false;
        if ($this->socket) {
            socket_close($this->socket);
        }
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }
        echo "Server stopped\n";
    }
}
