import pluginJs from "@eslint/js";
import { defineConfig, globalIgnores } from "eslint/config";
import eslintPluginPrettierRecommended from "eslint-plugin-prettier/recommended";
import globals from "globals";
import tseslint from "typescript-eslint";
// import eslintPluginUnicorn from "eslint-plugin-unicorn";

/** @type {import('eslint').Linter.Config[]} */
export default defineConfig([
    globalIgnores([
        "electron-plugin/dist/**/*",
        "out/**/*",
        'electron-plugin/src/preload/livewire-dispatcher.js'
    ]),
    {
        files: ["**/*.{js,mjs,cjs,ts}"],
    },
    {
        languageOptions: {
            globals: {
                ...globals.builtin,
                ...globals.browser,
                ...globals.node,
            },
        },
    },
    pluginJs.configs.recommended,
    ...tseslint.configs.recommended,
    eslintPluginPrettierRecommended,
    {
        rules: {
            "@typescript-eslint/ban-ts-comment": [
                "error",
                {
                    "ts-ignore": false,
                    "ts-expect-error": false,
                    "ts-nocheck": false,
                    "ts-check": false
                }
            ],
            "prettier/prettier": [
                "error",
                {
                    "singleQuote": true
                }
            ]
        }
    },
    // {
    //     languageOptions: {
    //         globals: globals.builtin,
    //     },
    //     plugins: {
    //         unicorn: eslintPluginUnicorn,
    //     },
    //     rules: {
    //         'unicorn/prefer-module': 'error',
    //     },
    // },
]);
