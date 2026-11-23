#!/usr/bin/env node

import { readFile, writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { replaceOpenApiVersion } from './openapi-release-version.mjs'

const version = process.argv[2]
const schemaPath = resolve(process.argv[3] ?? 'back/openapi/openapi.yaml')

if (typeof version !== 'string' || !/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/.test(version)) {
  throw new Error('A valid semantic version is required.')
}

const source = await readFile(schemaPath, 'utf8')
await writeFile(schemaPath, replaceOpenApiVersion(source, version), 'utf8')

console.log(`Updated OpenAPI version to ${version}.`)
