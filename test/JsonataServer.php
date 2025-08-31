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
        // Remove existing socket file if it exists
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }

        // Create Unix domain socket
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->socket === false) {
            throw new Exception("Failed to create socket: " . socket_strerror(socket_last_error()));
        }

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
            $client = socket_accept($this->socket);
            if ($client === false) {
                continue;
            }

            echo "Client connected\n";

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
                    break;
                }

                $length = unpack('N', $lengthData)[1];
                
                // Read request data
                $requestData = '';
                $bytesRead = 0;
                while ($bytesRead < $length) {
                    $chunk = socket_read($client, $length - $bytesRead);
                    if ($chunk === false || strlen($chunk) === 0) {
                        break;
                    }
                    $requestData .= $chunk;
                    $bytesRead += strlen($chunk);
                }

                if (strlen($requestData) !== $length) {
                    echo "Incomplete request received\n";
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
            $this->sendResponse($client, ['error' => $e->getMessage()]);
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
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendResponse($client, array $response): void
    {
        $responseData = json_encode($response);
        $length = strlen($responseData);
        
        // Send length (4 bytes)
        socket_write($client, pack('N', $length), 4);
        
        // Send response data
        socket_write($client, $responseData, $length);
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
