# JSONata-PHP

🚀 A **PHP port of [JSONata](https://jsonata.org/)** – a lightweight query and transformation language for JSON data.  
This project is based on the [jsonata-java](https://github.com/IBM/jsonata-java) reference implementation, adapted for the PHP ecosystem.

---

## 📌 What is JSONata?

JSONata is a powerful query language for JSON:
- Navigate JSON structures (like XPath for JSON)
- Perform transformations, mapping, and filtering
- Use built-in functions and expressions
- Construct new JSON documents

Example (in JSONata):

```jsonata
Account.Order.Product[Price > 50].{ "id": ProductID, "total": Price * Quantity }
