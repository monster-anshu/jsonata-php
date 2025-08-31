const JsonataClient = require("./client.js");

const client = new JsonataClient();
function josnata(expr) {
  return {
    evaluate: async (input, bindings) => {
      const result = await client.evaluate(expr, input);
      return result;
    },
  };
}

module.exports = josnata;
