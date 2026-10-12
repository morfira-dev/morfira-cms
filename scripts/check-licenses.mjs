import { readFile } from 'node:fs/promises';
import process from 'node:process';

const npmLockfiles = ['package-lock.json', 'front/package-lock.json'];
const composerLockfile = 'back/composer.lock';
const reviewedContainerImages = new Map([
  ['dunglas/frankenphp:php8.4-bookworm', 'MIT image label; PHP and Debian components retain their own licenses'],
  ['mariadb:11.4', 'GPL-2.0 image label; approved only as a separately operated database service'],
  ['composer:2', 'Composer is MIT; the image has no license OCI label and contains PHP and OS components'],
  ['node:22-bookworm-slim', 'Node.js is MIT; the image has no license OCI label and contains Debian components'],
]);
const reviewedGithubActions = new Map([
  ['actions/checkout@v5', 'MIT'],
  ['actions/setup-node@v6', 'MIT'],
]);

const acceptedLicenses = new Map([
  ['MIT', 'permissive'],
  ['ISC', 'permissive'],
  ['Apache-2.0', 'permissive with notice requirements'],
  ['BSD-2-Clause', 'permissive with notice requirements'],
  ['BSD-3-Clause', 'permissive with notice requirements'],
  ['BlueOak-1.0.0', 'permissive'],
  ['Python-2.0', 'permissive with notice requirements'],
  ['Artistic-2.0', 'permissive with conditions'],
  ['CC0-1.0', 'public-domain dedication'],
  ['CC-BY-3.0', 'attribution required'],
  ['(MIT OR CC0-1.0)', 'permissive choice'],
  ['(BSD-2-Clause OR MIT OR Apache-2.0)', 'permissive choice'],
]);

const npmMetadataOverrides = new Map([
  [
    'node_modules/npm/node_modules/qrcode-terminal@0.12.0',
    {
      license: 'Apache-2.0',
      reason: 'The npm lock metadata omits the legacy `licenses` field; the installed package LICENSE is Apache-2.0.',
    },
  ],
]);

const failures = [];
const summaries = [];

async function readJson(path) {
  return JSON.parse(await readFile(path, 'utf8'));
}

for (const manifest of ['package.json', 'front/package.json', 'back/composer.json']) {
  const metadata = await readJson(manifest);
  if (metadata.license !== 'MIT') {
    failures.push(`${manifest}: the Morfira project license must remain MIT unless an explicit licensing decision changes it.`);
  }
}

function addLicense(counts, license) {
  counts.set(license, (counts.get(license) ?? 0) + 1);
}

function validateLicense(source, identifier, license) {
  const isReviewedLightningCss = source === 'front/package-lock.json'
    && license === 'MPL-2.0'
    && /^node_modules\/lightningcss(?:-[^@]+)?@1\.33\.0$/u.test(identifier);

  if (!acceptedLicenses.has(license) && !isReviewedLightningCss) {
    failures.push(`${source}: ${identifier} uses unapproved license ${JSON.stringify(license)}.`);
  }
}

for (const lockfile of npmLockfiles) {
  const lock = await readJson(lockfile);
  const counts = new Map();
  let packages = 0;

  for (const [packagePath, metadata] of Object.entries(lock.packages ?? {})) {
    if (packagePath === '') {
      continue;
    }

    packages += 1;
    const identifier = `${packagePath}@${metadata.version ?? 'unknown-version'}`;
    const override = npmMetadataOverrides.get(identifier);
    const license = metadata.license ?? override?.license ?? 'UNKNOWN';

    addLicense(counts, license);
    validateLicense(lockfile, identifier, license);
  }

  summaries.push({ source: lockfile, packages, counts });
}

const composerLock = await readJson(composerLockfile);
const composerCounts = new Map();
let composerPackages = 0;

for (const section of ['packages', 'packages-dev']) {
  for (const metadata of composerLock[section] ?? []) {
    composerPackages += 1;
    const licenses = metadata.license?.length > 0 ? metadata.license : ['UNKNOWN'];

    for (const license of licenses) {
      addLicense(composerCounts, license);
      validateLicense(composerLockfile, `${metadata.name}@${metadata.version}`, license);
    }
  }
}

summaries.push({ source: composerLockfile, packages: composerPackages, counts: composerCounts });

const composeFiles = await Promise.all(['compose.yaml', 'compose.prod.yaml', 'compose.e2e.yaml'].map((path) => readFile(path, 'utf8')));
const dockerfiles = await Promise.all(['back/Dockerfile', 'Dockerfile', 'docker/Dockerfile.e2e'].map((path) => readFile(path, 'utf8')));
const releaseWorkflow = await readFile('.github/workflows/release.yml', 'utf8');
const containerImages = [...new Set([
  ...composeFiles.flatMap((file) => [...file.matchAll(/^\s*image:\s*([^\s#]+).*$/gm)].map((match) => match[1])),
  ...dockerfiles.flatMap((file) => [...file.matchAll(/^FROM\s+([^\s]+).*$/gim)].map((match) => match[1])),
])];

for (const image of containerImages) {
  if (!reviewedContainerImages.has(image)) {
    failures.push(`container images: ${image} has not received a license review.`);
  }
}

const githubActions = [...releaseWorkflow.matchAll(/^\s*uses:\s*([^\s#]+).*$/gm)].map((match) => match[1]);
for (const action of githubActions) {
  if (!reviewedGithubActions.has(action)) {
    failures.push(`GitHub Actions: ${action} has not received a license review.`);
  }
}

for (const { source, packages, counts } of summaries) {
  const licenseSummary = [...counts.entries()]
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([license, count]) => `${license}: ${count}`)
    .join(', ');

  console.log(`${source}: ${packages} packages (${licenseSummary})`);
}

console.log('project manifests: MIT');

for (const image of containerImages) {
  console.log(`container image ${image}: ${reviewedContainerImages.get(image) ?? 'UNREVIEWED'}`);
}

for (const action of githubActions) {
  console.log(`GitHub Action ${action}: ${reviewedGithubActions.get(action) ?? 'UNREVIEWED'}`);
}

if (failures.length > 0) {
  console.error('\nDependency license policy failed:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  console.error('\nReview the package license and update the policy only after an explicit product/legal decision.');
  process.exitCode = 1;
} else {
  console.log('\nDependency license policy passed.');
}
