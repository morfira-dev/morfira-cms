export function replaceOpenApiVersion(source, version) {
  const lines = source.split(/(?<=\n)/)
  const infoStart = lines.findIndex((line) => line.replace(/\r?\n$/, '') === 'info:')

  if (infoStart === -1) {
    throw new Error('OpenAPI info section was not found.')
  }

  for (let index = infoStart + 1; index < lines.length; index += 1) {
    const line = lines[index].replace(/\r?\n$/, '')
    if (line !== '' && !line.startsWith(' ')) break
    if (line.startsWith('  version:')) {
      const ending = lines[index].slice(line.length)
      lines[index] = `  version: ${version}${ending}`
      return lines.join('')
    }
  }

  throw new Error('OpenAPI info.version was not found.')
}
