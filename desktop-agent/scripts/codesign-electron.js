#!/usr/bin/env node
// macOS only: remove quarantine + apply ad-hoc codesign so Gatekeeper allows the unsigned Electron binary.
// Runs automatically via postinstall. Safe to run on Windows/Linux (no-ops).

if (process.platform !== 'darwin') process.exit(0);

const { execSync } = require('child_process');
const path = require('path');

const electronApp = path.join(__dirname, '..', 'node_modules', 'electron', 'dist', 'Electron.app');

try {
    execSync(`xattr -dr com.apple.quarantine "${electronApp}"`, { stdio: 'inherit' });
    execSync(`codesign --force --deep --sign - "${electronApp}"`, { stdio: 'inherit' });
    console.log('Electron.app: quarantine removed and ad-hoc signed.');
} catch (e) {
    console.warn('codesign-electron: non-fatal error —', e.message);
}
