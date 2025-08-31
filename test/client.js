const net = require('net');
const fs = require('fs');

class JsonataClient {
    constructor(socketPath = '/tmp/jsonata.sock') {
        this.socketPath = socketPath;
    }

    async evaluate(expression, data) {
        return new Promise((resolve, reject) => {
            // Check if socket exists
            if (!fs.existsSync(this.socketPath)) {
                reject(new Error(`Socket not found: ${this.socketPath}. Make sure the PHP server is running.`));
                return;
            }

            const client = new net.Socket();
            let responseData = '';
            let expectedLength = 0;
            let receivedLength = 0;

            client.connect(this.socketPath, () => {
                console.log('Connected to PHP JSONata server');
                
                // Prepare request
                const request = {
                    expression: expression,
                    data: data
                };
                
                const requestData = JSON.stringify(request);
                const length = Buffer.alloc(4);
                length.writeUInt32BE(requestData.length, 0);
                
                // Send request
                client.write(length);
                client.write(requestData);
            });

            client.on('data', (data) => {
                if (expectedLength === 0) {
                    // First 4 bytes contain the response length
                    expectedLength = data.readUInt32BE(0);
                    responseData = data.slice(4).toString();
                    receivedLength = responseData.length;
                } else {
                    responseData += data.toString();
                    receivedLength += data.length;
                }

                if (receivedLength >= expectedLength) {
                    try {
                        const response = JSON.parse(responseData);
                        client.destroy();
                        
                        if (response.error) {
                            reject(new Error(response.error));
                        } else {
                            resolve(response.result);
                        }
                    } catch (error) {
                        client.destroy();
                        reject(new Error(`Failed to parse response: ${error.message}`));
                    }
                }
            });

            client.on('error', (error) => {
                reject(new Error(`Connection error: ${error.message}`));
            });

            client.on('close', () => {
                if (expectedLength === 0) {
                    reject(new Error('Connection closed before receiving response'));
                }
            });

            // Set timeout
            setTimeout(() => {
                client.destroy();
                reject(new Error('Request timeout'));
            }, 10000);
        });
    }
}

module.exports = JsonataClient;
