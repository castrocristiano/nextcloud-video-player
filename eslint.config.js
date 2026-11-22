import globals from "globals";

export default [
  {
    files: ["js/**/*.js", "tests/js/**/*.js"],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "module",
      globals: {
        ...globals.browser,
        ...globals.node,
        OC: "readonly",
        OCA: "readonly",
      },
    },
    rules: {
      "no-unused-vars": ["error", { argsIgnorePattern: "^_" }],
      "no-undef": "error",
      "no-eval": "error",
      "no-implied-eval": "error",
      "eqeqeq": ["error", "always"],
    },
  },
];
