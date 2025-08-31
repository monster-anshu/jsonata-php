const JsonataClient = require('./client.js');

async function runTests() {
    const client = new JsonataClient();
    
    const testCases = [
        {
            name: "Filter products by price",
            expression: 'Account.Order.Product[Price > 50]',
            data: {
                "Account": {
                    "Order": {
                        "Product": [
                            {"ProductID": "A1", "Price": 100, "Quantity": 2},
                            {"ProductID": "A2", "Price": 30, "Quantity": 1},
                            {"ProductID": "A3", "Price": 75, "Quantity": 3}
                        ]
                    }
                }
            }
        },
        {
            name: "Get all product IDs",
            expression: 'Account.Order.Product.ProductID',
            data: {
                "Account": {
                    "Order": {
                        "Product": [
                            {"ProductID": "A1", "Price": 100, "Quantity": 2},
                            {"ProductID": "A2", "Price": 30, "Quantity": 1},
                            {"ProductID": "A3", "Price": 75, "Quantity": 3}
                        ]
                    }
                }
            }
        },
        {
            name: "Get first product",
            expression: 'Account.Order.Product[0]',
            data: {
                "Account": {
                    "Order": {
                        "Product": [
                            {"ProductID": "A1", "Price": 100, "Quantity": 2},
                            {"ProductID": "A2", "Price": 30, "Quantity": 1},
                            {"ProductID": "A3", "Price": 75, "Quantity": 3}
                        ]
                    }
                }
            }
        }
    ];

    for (const testCase of testCases) {
        console.log(`\n=== ${testCase.name} ===`);
        console.log('Expression:', testCase.expression);
        
        try {
            const result = await client.evaluate(testCase.expression, testCase.data);
            // console.log('Result:', JSON.stringify(result, null, 2));
            console.log('Result:', result);
        } catch (error) {
            console.error('Error:', error.message);
        }
    }
}

// Run tests
runTests().catch(console.error);
