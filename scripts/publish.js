#!/usr/bin/env node

import { readFileSync, writeFileSync } from 'fs';
import { execSync } from 'child_process';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const rootDir = join(__dirname, '..');

// Get version argument
const versionArg = process.argv[2];

if (!versionArg) {
    console.error('Error: Please provide a version argument (major, minor, patch, or a specific version like 1.2.3)');
    console.error('Usage: npm run publish <major|minor|patch|X.Y.Z>');
    process.exit(1);
}

// Parse current version from package.json
const packageJsonPath = join(rootDir, 'package.json');
const packageJson = JSON.parse(readFileSync(packageJsonPath, 'utf8'));
const currentVersion = packageJson.version;

console.log(`Current version: ${currentVersion}`);

// Calculate new version
let newVersion;
if (['major', 'minor', 'patch'].includes(versionArg)) {
    const [major, minor, patch] = currentVersion.split('.').map(Number);
    
    switch (versionArg) {
        case 'major':
            newVersion = `${major + 1}.0.0`;
            break;
        case 'minor':
            newVersion = `${major}.${minor + 1}.0`;
            break;
        case 'patch':
            newVersion = `${major}.${minor}.${patch + 1}`;
            break;
    }
} else {
    // Validate custom version format (X.Y.Z)
    const versionRegex = /^\d+\.\d+\.\d+$/;
    if (!versionRegex.test(versionArg)) {
        console.error('Error: Invalid version format. Must be X.Y.Z (e.g., 1.2.3)');
        process.exit(1);
    }
    newVersion = versionArg;
}

console.log(`New version: ${newVersion}`);

// Run build command
console.log('\nRunning build command...');
try {
    execSync('npm run build', { cwd: rootDir, stdio: 'inherit' });
    console.log('Build completed successfully!');
} catch (error) {
    console.error('Build failed!');
    process.exit(1);
}

// Update package.json
console.log('\nUpdating package.json...');
packageJson.version = newVersion;
writeFileSync(packageJsonPath, JSON.stringify(packageJson, null, 4) + '\n');
console.log('✓ package.json updated');

// Update composer.json
console.log('Updating composer.json...');
const composerJsonPath = join(rootDir, 'composer.json');
const composerJson = JSON.parse(readFileSync(composerJsonPath, 'utf8'));
composerJson.version = newVersion;
writeFileSync(composerJsonPath, JSON.stringify(composerJson, null, 4) + '\n');
console.log('✓ composer.json updated');

// Update ext_emconf.php
console.log('Updating ext_emconf.php...');
const extEmconfPath = join(rootDir, 'ext_emconf.php');
let extEmconfContent = readFileSync(extEmconfPath, 'utf8');
extEmconfContent = extEmconfContent.replace(
    /'version'\s*=>\s*'[^']+'/,
    `'version' => '${newVersion}'`
);
writeFileSync(extEmconfPath, extEmconfContent);
console.log('✓ ext_emconf.php updated');

console.log(`\n✨ Version bumped from ${currentVersion} to ${newVersion}`);
console.log('\nNext steps:');
console.log('1. Review the changes');
console.log('2. Commit the version bump: git add . && git commit -m "Bump version to ' + newVersion + '"');
console.log('3. Tag the release: git tag v' + newVersion);
console.log('4. Push changes: git push && git push --tags');
