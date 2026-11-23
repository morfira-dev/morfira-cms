import { execFileSync } from 'node:child_process'
import { replaceOpenApiVersion } from './openapi-release-version.mjs'

const openApiPath = 'back/openapi/openapi.yaml'
const changelogPath = 'CHANGELOG.md'

function git(...args) {
  return execFileSync('git', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] })
}

function verifyReleaseTag(tag) {
  if (process.argv.length !== 3 || !/^v(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/.test(tag)) {
    throw new Error('Usage: node scripts/verify-release-tag.mjs vX.Y.Z')
  }
  const version = tag.slice(1)
  const tagCommit = git('rev-parse', '--verify', `refs/tags/${tag}^{commit}`).trim()
  const [, candidateCommit, ...otherParents] = git('rev-list', '--parents', '-n', '1', tagCommit).trim().split(' ')
  const subject = git('show', '-s', '--format=%s', tagCommit).trim()
  if (!candidateCommit || otherParents.length > 0 || subject !== `chore(release): ${version} [skip ci]`) {
    throw new Error(`${tag} must point to a semantic-release commit with one available parent.`)
  }

  const changedPaths = git('diff', '--name-only', '--no-renames', '-z', candidateCommit, tagCommit).split('\0').filter(Boolean)
  if (changedPaths.length !== 2 || !changedPaths.includes(changelogPath) || !changedPaths.includes(openApiPath)) {
    throw new Error('The release commit must change only CHANGELOG.md and back/openapi/openapi.yaml.')
  }

  const candidateApi = git('show', `${candidateCommit}:${openApiPath}`)
  const taggedApi = git('show', `${tagCommit}:${openApiPath}`)
  if (replaceOpenApiVersion(candidateApi, version) !== taggedApi) {
    throw new Error(`Tagged OpenAPI must match the candidate contract with only info.version changed to ${version}.`)
  }

  const [heading] = git('show', `${tagCommit}:${changelogPath}`).trimStart().split(/\r?\n/)
  if (!heading.startsWith(`## [${version}](`)) {
    throw new Error(`The first changelog heading must match ${tag}.`)
  }

  return { tag, tagCommit, candidateCommit }
}

try {
  console.log(JSON.stringify(verifyReleaseTag(process.argv[2]), null, 2))
} catch (error) {
  console.error(error.stderr?.trim() || error.message)
  process.exitCode = 1
}
