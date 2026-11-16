import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    files: ['resources/js/**/*.js', 'tests/js/**/*.js', 'tests/e2e/**/*.js'],
    languageOptions: {
      ecmaVersion: 2023,
      sourceType: 'module',
      globals: { ...globals.browser, ...globals.node },
    },
  },
  {
    files: ['resources/js/editor-bridge.js', 'resources/js/viewer.js'],
    languageOptions: { sourceType: 'script' },
  },
];
