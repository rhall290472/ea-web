// generate-version.js
const fs = require('fs');
const { execSync } = require('child_process');

try {
  // Get latest tag (e.g., v1.2.0), fallback to "dev"
  const tag = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim() || 'dev';

  // Get short commit hash
  const hash = execSync('git rev-parse --short HEAD', { encoding: 'utf8' }).trim();

  // Format date: November 7, 2025
  const date = new Date().toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });

  const version = { tag, commit: hash, date };

  // Write to public/version.json
  fs.writeFileSync('public/version.json', JSON.stringify(version, null, 2));
  console.log('version.json updated:', version);
} catch (error) {
  console.warn('Git not available (e.g., in production). Using fallback.');
  fs.writeFileSync('public/version.json', JSON.stringify({
    tag: 'unknown',
    commit: 'unknown',
    date: new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  }, null, 2));
}