import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const tree = JSON.parse(execFileSync('pnpm', ['list', '--lockfile-only', '--depth', 'Infinity', '--json'], { encoding: 'utf8' }));
const sections = ['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'];
const manifests = new Map();
const licenses = new Map();

function visit(node) {
  for (const section of sections) {
    for (const [name, dependency] of Object.entries(node[section] ?? {})) {
      const manifestPath = join(dependency.path, 'package.json');
      if (!manifests.has(manifestPath)) {
        manifests.set(manifestPath, JSON.parse(readFileSync(manifestPath, 'utf8')));
      }

      const manifest = manifests.get(manifestPath);
      const packageName = dependency.from ?? name;
      const identifier = `${packageName}@${dependency.version}`;
      if (manifest.name !== packageName || manifest.version !== dependency.version) {
        throw new Error(`Installed manifest does not match ${identifier} from the pnpm lockfile.`);
      }

      const license = typeof manifest.license === 'string' ? manifest.license : 'UNKNOWN';
      if (licenses.has(identifier) && licenses.get(identifier) !== license) {
        throw new Error(`Conflicting licenses for ${identifier}.`);
      }
      licenses.set(identifier, license);
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
