import { readdir, readFile } from 'node:fs/promises'
import { extname, join, relative, sep } from 'node:path'

const sourceRoot = new URL('../src/', import.meta.url)
const forbiddenImport = /from\s+['"](?:@mui|@emotion)\//
const violations = []

async function visit(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name)

    if (entry.isDirectory()) {
      await visit(path)
      continue
    }

    if (!['.ts', '.tsx'].includes(extname(entry.name))) {
      continue
    }

    const relativePath = relative(sourceRoot.pathname, path)
    const isUiLayer = relativePath === 'ui' || relativePath.startsWith(`ui${sep}`)

    if (!isUiLayer && forbiddenImport.test(await readFile(path, 'utf8'))) {
      violations.push(relativePath)
    }
  }
}

await visit(sourceRoot.pathname)

if (violations.length > 0) {
  console.error('Direct MUI/Emotion imports are only allowed inside src/ui:')
  for (const violation of violations) {
    console.error(`- ${violation}`)
  }
  process.exitCode = 1
} else {
  console.log('UI boundary check passed.')
}
