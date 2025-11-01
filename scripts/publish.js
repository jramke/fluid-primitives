#!/usr/bin/env node

import { execSync } from 'child_process';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const rootDir = join(__dirname, '..');

// Get version argument
const versionArg = process.argv[2];

if (!versionArg) {
	console.error(
		'Error: Please provide a version argument (major, minor, patch, or a specific version like 1.2.3)'
	);
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
try {
	const composerJson = JSON.parse(readFileSync(composerJsonPath, 'utf8'));
	composerJson.version = newVersion;
	writeFileSync(composerJsonPath, JSON.stringify(composerJson, null, 4) + '\n');
	console.log('✓ composer.json updated');
} catch (error) {
	if (error.code === 'ENOENT') {
		console.error('Error: composer.json not found');
	} else if (error instanceof SyntaxError) {
		console.error('Error: composer.json is not valid JSON');
	} else {
		console.error(`Error updating composer.json: ${error.message}`);
	}
	process.exit(1);
}

// Update ext_emconf.php
console.log('Updating ext_emconf.php...');
const extEmconfPath = join(rootDir, 'ext_emconf.php');
try {
	let extEmconfContent = readFileSync(extEmconfPath, 'utf8');
	const updatedContent = extEmconfContent.replace(
		/'version'\s*=>\s*'[^']+'/,
		`'version' => '${newVersion}'`
	);

	if (updatedContent === extEmconfContent) {
		console.error('Error: Could not find version field in ext_emconf.php');
		process.exit(1);
	}

	writeFileSync(extEmconfPath, updatedContent);
	console.log('✓ ext_emconf.php updated');
} catch (error) {
	if (error.code === 'ENOENT') {
		console.error('Error: ext_emconf.php not found');
	} else {
		console.error(`Error updating ext_emconf.php: ${error.message}`);
	}
	process.exit(1);
}

console.log(`\n✨ Version bumped from ${currentVersion} to ${newVersion}`);
