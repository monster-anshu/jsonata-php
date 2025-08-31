# JSONata PHP Server with Node.js Client

This implementation provides a Unix socket-based server in PHP that can execute JSONata expressions, with a Node.js client for communication.

## Architecture

- **PHP Server**: Unix socket server that receives JSONata expressions and data, executes them using the PHP JSONata implementation, and returns results
- **Node.js Client**: Client library that connects to the PHP server and sends expressions for evaluation

## Modular Structure

The code is organized into separate files for better maintainability:

- **`JsonataServer.php`**: Contains the `JsonataSocketServer` class with all server functionality
- **`start-server.php`**: Simple script to start the server with proper signal handling
- **`main.php`**: Basic test script for direct JSONata evaluation
- **`example.php`**: Shows how to use the server class programmatically

## Files

- `JsonataServer.php` - PHP server class with Unix socket implementation
- `start-server.php` - Script to start the JSONata server
- `main.php` - Simple test script for direct JSONata evaluation
- `example.php` - Example of using JsonataServer class programmatically
- `client.js` - Node.js client library
- `test.js` - Test script demonstrating various JSONata expressions

## Usage

### 1. Start the PHP Server

```bash
cd test
php start-server.php
```

The server will start and listen on `/tmp/jsonata.sock`. You should see:
```
JSONata PHP Server started on: /tmp/jsonata.sock
Waiting for connections...
```

### 2. Use the Node.js Client

#### Basic Usage

```javascript
const JsonataClient = require('./client.js');

const client = new JsonataClient();

const expression = 'Account.Order.Product[Price > 50].{ "id": ProductID, "total": Price * Quantity }';
const data = {
    "Account": {
        "Order": {
            "Product": [
                {"ProductID": "A1", "Price": 100, "Quantity": 2},
                {"ProductID": "A2", "Price": 30, "Quantity": 1}
            ]
        }
    }
};

client.evaluate(expression, data)
    .then(result => console.log(result))
    .catch(error => console.error(error));
```

#### Run the Example Client

```bash
node client.js
```

#### Run the Test Suite

```bash
node test.js
```

### 3. Stop the Server

```bash
./stop-server.sh
```

Or manually:
```bash
# Find the process
ps aux | grep "php start-server.php"

# Kill it (replace PID with actual process ID)
kill <PID>

# Remove socket file
rm /tmp/jsonata.sock
```

## Protocol

The communication protocol uses a simple binary format:

1. **Request Format**:
   - 4 bytes: Length of JSON request (big-endian)
   - JSON data: `{"expression": "...", "data": {...}}`

2. **Response Format**:
   - 4 bytes: Length of JSON response (big-endian)
   - JSON data: `{"success": true, "result": ...}` or `{"error": "..."}`

## Features

- **Multi-process handling**: Each client connection is handled in a separate process
- **Error handling**: Comprehensive error handling for both client and server
- **Timeout support**: Client requests timeout after 10 seconds
- **Graceful shutdown**: Server can be stopped with Ctrl+C
- **Socket cleanup**: Automatically removes socket file on startup/shutdown

## Requirements

- PHP 8.0+ with socket and pcntl extensions
- Node.js 14+
- Unix-like system (for Unix domain sockets)

## Supported Expressions

The PHP JSONata implementation supports basic expressions including:
- Property access: `Account.Order.Product`
- Array indexing: `Account.Order.Product[0]`
- Filtering: `Account.Order.Product[Price > 50]`
- Property projection: `Account.Order.Product.ProductID`

Complex expressions like object construction and aggregation functions may not be fully supported in the current PHP implementation.

## Security Considerations

- The socket file is created with 0666 permissions for easy access
- In production, consider using more restrictive permissions
- The server accepts any JSON data - validate inputs as needed
- Consider adding authentication if needed for production use

## Troubleshooting

1. **"Socket not found"**: Make sure the PHP server is running
2. **"Permission denied"**: Check socket file permissions
3. **"Connection refused"**: Server might not be running or socket path is wrong
4. **"Request timeout"**: Server might be overloaded or unresponsive
