const uuidPattern = /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i

function readUuid(parameters: URLSearchParams, name: string): string | null {
  const values = parameters.getAll(name)
  const id = values[0]
  return values.length === 1 && id.length === 36 && uuidPattern.test(id) ? id.toLowerCase() : null
}

export function readDraftLink(search: string) {
  const parameters = new URLSearchParams(search)
  if (!parameters.has('draft')) return null

  return { id: readUuid(parameters, 'draft'), contentTypeId: readUuid(parameters, 'type') }
}
