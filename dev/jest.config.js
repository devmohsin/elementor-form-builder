/** @type {import('jest').Config} */
module.exports = {
  testEnvironment: 'jsdom',
  rootDir: '..',
  testMatch: [ '<rootDir>/dev/tests/js/**/*.test.js' ],
  setupFiles: [ '<rootDir>/dev/tests/js/setup.js' ],
  setupFilesAfterEnv: [ '<rootDir>/dev/tests/js/setupRealDom.js' ],
  coverageDirectory: '<rootDir>/dev/coverage/js',
  collectCoverageFrom: [
    'assets/frontend/js/*.js',
    '!assets/frontend/js/vendor/**',
  ],
};
