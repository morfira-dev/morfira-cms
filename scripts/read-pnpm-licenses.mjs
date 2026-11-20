import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const tree = JSON.parse(execFileSync('pnpm', ['list', '--lockfile-only', '--depth', 'Infinity', '--json'], {
  encoding: 'utf8',
  maxBuffer: 16 * 1024 * 1024,
}));
const sections = ['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'];
const manifests = new Map();
const licenses = new Map();

function readManifest(directory) {
  const manifestPath = join(directory, 'package.json');
  if (!manifests.has(manifestPath)) {
    manifests.set(manifestPath, JSON.parse(readFileSync(manifestPath, 'utf8')));
  }
  return manifests.get(manifestPath);
}

function recordLicense(name, version, directory) {
  const manifest = readManifest(directory);
  const identifier = `${name}@${version}`;
  if (manifest.name !== name || manifest.version !== version || typeof version !== 'string') {
    throw new Error(`Installed manifest does not match ${identifier}.`);
  }

  const declaredLicense = typeof manifest.license === 'string' ? manifest.license : manifest.license?.type;
  let license = typeof declaredLicense === 'string' ? declaredLicense : 'UNKNOWN';
  if (identifier === 'qrcode-terminal@0.12.0' && license === 'UNKNOWN'
    && manifest.licenses?.some(({ type }) => type === 'Apache 2.0')
    && /Apache License\s+Version 2\.0/u.test(readFileSync(join(directory, 'LICENSE'), 'utf8'))) {
    license = 'Apache-2.0';
  }
  if (licenses.has(identifier) && licenses.get(identifier) !== license) {
    throw new Error(`Conflicting licenses for ${identifier}.`);
  }
  licenses.set(identifier, license);
  return manifest;
}

function visitBundledModules(directory, required = []) {
  const modules = join(directory, 'node_modules');
  if (!existsSync(modules) && required.length === 0) return;

  for (const name of required) {
    readManifest(join(modules, name));
  }
  const names = readdirSync(modules).filter((name) => !name.startsWith('.')).flatMap((name) =>
    name.startsWith('@') ? readdirSync(join(modules, name)).map((child) => `${name}/${child}`) : [name]);
  for (const name of names) {
    const installed = join(modules, name);
    recordLicense(name, readManifest(installed).version, installed);
    visitBundledModules(installed);
  }
}

function visit(node) {
  for (const section of sections) {
    for (const [name, dependency] of Object.entries(node[section] ?? {})) {
      const packageName = dependency.from ?? name;
      const manifest = recordLicense(packageName, dependency.version, dependency.path);
      const bundle = manifest.bundleDependencies ?? manifest.bundledDependencies;
      if (bundle === true) visitBundledModules(dependency.path, Object.keys(manifest.dependencies ?? {}));
      if (Array.isArray(bundle) && bundle.length > 0) visitBundledModules(dependency.path, bundle);
      visit(dependency);
    }
  }
}

for (const project of tree) {
  visit(project);
}
if (licenses.size === 0) {
  throw new Error('The pnpm lockfile inventory is empty.');
}

console.log(JSON.stringify(Object.fromEntries(licenses)));
