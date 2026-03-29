const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/browser',
  timeout: 30000,
  use: {
    baseURL: 'http://127.0.0.1:8889',
    headless: true,
    acceptDownloads: true,
  },
  webServer: {
    command: 'php tests/browser/bootstrap-site.php && php -S 127.0.0.1:8889 tests/browser/router.php',
    url: 'http://127.0.0.1:8889/wp-login.php',
    reuseExistingServer: false,
    timeout: 120000,
    stdout: 'ignore',
    stderr: 'ignore',
  },
});
