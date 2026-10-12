#!/usr/bin/env node

import { readFile, writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'

const version = process.argv[2]
const schemaPath = resolve(process.argv[3] ?? 'back/openapi/openapi.yaml')

if (typeof version !== 'string' || !/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/.test(version)) {
  throw new Error('A valid semantic version is required.')
}

const source = await readFile(schemaPath, 'utf8')
const lines = source.split(/(?<=\n)/)
const infoStart = lines.findIndex((line) => line.replace(/\r?\n$/, '') === 'info:')

if (infoStart === -1) {
  throw new Error('OpenAPI info section was not found.')
}

let versionLine = -1
for (let index = infoStart + 1; index < lines.length; index += 1) {
  const line = lines[index].replace(/\r?\n$/, '')
  if (line !== '' && !line.startsWith(' ')) break
  if (line.startsWith('  version:')) {
    versionLine = index
    break
  }
}

if (versionLine === -1) {
  throw new Error('OpenAPI info.version was not found.')
}

const lineEnding = lines[versionLine].endsWith('\r\n') ? '\r\n' : lines[versionLine].endsWith('\n') ? '\n' : ''
lines[versionLine] = `  version: ${version}${lineEnding}`
await writeFile(schemaPath, lines.join(''), 'utf8')

console.log(`Updated OpenAPI version to ${version}.`)
