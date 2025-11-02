#!/usr/bin/env node

import { execSync } from 'child_process';
import { readFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const rootDir = join(__dirname, '..');

// Get target version argument (optional)
const targetVersion = process.argv[2];

console.log('🔍 Checking @zag-js packages...\n');

// Parse current dependencies from package.json
const packageJsonPath = join(rootDir, 'package.json');
const packageJson = JSON.parse(readFileSync(packageJsonPath, 'utf8'));
const dependencies = packageJson.dependencies || {};

// Find all @zag-js packages
const zagPackages = Object.keys(dependencies).filter(pkg => pkg.startsWith('@zag-js/'));

if (zagPackages.length === 0) {
	console.log('No @zag-js packages found in dependencies.');
	process.exit(0);
}

console.log(`Found ${zagPackages.length} @zag-js packages:\n`);
zagPackages.forEach(pkg => {
	console.log(`  ${pkg}: ${dependencies[pkg]}`);
});
console.log();

// Determine target version
let versionToInstall;
if (targetVersion) {
	// Validate version format
	const versionRegex = /^\d+\.\d+\.\d+$/;
	if (!versionRegex.test(targetVersion)) {
		console.error('Error: Invalid version format. Must be X.Y.Z (e.g., 1.26.4)');
		process.exit(1);
	}
	versionToInstall = targetVersion;
	console.log(`📌 Using specified version: ${versionToInstall}\n`);
} else {
	// Fetch latest version from npm
	console.log('📡 Fetching latest version from npm...');
	try {
		const latestVersion = execSync('npm view @zag-js/core version', {
			cwd: rootDir,
			encoding: 'utf8',
		}).trim();
		versionToInstall = latestVersion;
		console.log(`✓ Latest version: ${versionToInstall}\n`);
	} catch (error) {
		console.error('Error: Could not fetch latest version from npm');
		console.error(error.message);
		process.exit(1);
	}
}

// Check if any packages need updating
const packagesToUpdate = zagPackages.filter(pkg => {
	const currentVersion = dependencies[pkg].replace(/^[\^~]/, '');
	return currentVersion !== versionToInstall;
});

if (packagesToUpdate.length === 0) {
	console.log(`✨ All @zag-js packages are already at version ${versionToInstall}`);
	process.exit(0);
}

console.log(
	`📦 Updating ${packagesToUpdate.length} package(s) to version ${versionToInstall}...\n`
);

// Install each package with the target version
const packageList = zagPackages.map(pkg => `${pkg}@^${versionToInstall}`).join(' ');

console.log('Running npm install...');
try {
	execSync(`npm install ${packageList}`, {
		cwd: rootDir,
		stdio: 'inherit',
	});
	console.log('\n✓ All packages updated successfully!');
} catch (error) {
	console.error('\n❌ Failed to update packages');
	process.exit(1);
}

// Show updated versions
console.log('\n📋 Updated versions:');
const updatedPackageJson = JSON.parse(readFileSync(packageJsonPath, 'utf8'));
const updatedDependencies = updatedPackageJson.dependencies || {};
zagPackages.forEach(pkg => {
	console.log(`  ${pkg}: ${updatedDependencies[pkg]}`);
});

console.log(`\n✨ Successfully bumped all @zag-js packages to version ^${versionToInstall}`);
