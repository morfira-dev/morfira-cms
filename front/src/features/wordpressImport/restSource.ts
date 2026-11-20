export const MAX_REST_URL_LENGTH = 512
const MAX_MEDIA_HOSTS = 8

const unsupportedUrlCharacters = /[^\x21-\x7e]|[\\?#@%]/
const relativePathSegment = /\/\.{1,2}(?:\/|$)/
const hostnameLabel = /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i
const publicHostnameSuffix = /\.[a-z][a-z0-9-]*$/i
const reservedHostnameSuffix = /\.(?:localhost|local|internal|lan|intranet|onion|invalid|home\.arpa)$/i

export function parseWordPressMediaHosts(value: string): string[] | null {
  const hosts = value.split(/\r?\n/).map((host) => host.trim()).filter(Boolean)
  if (hosts.length > MAX_MEDIA_HOSTS || hosts.some((host) =>
    host.length > 253
    || !publicHostnameSuffix.test(host)
    || reservedHostnameSuffix.test(host)
    || !host.split('.').every((label) => hostnameLabel.test(label)))) {
    return null
  }

  return [...new Set(hosts.map((host) => host.toLowerCase()))]
}

export function isWordPressRestNamespaceUrl(value: string): boolean {
  if (value.length > MAX_REST_URL_LENGTH || !/^https:\/\/[^/]/i.test(value)
    || unsupportedUrlCharacters.test(value) || relativePathSegment.test(value)) {
    return false
  }

  try {
    const url = new URL(value)
    return url.port === ''
      && /\.[a-z][a-z0-9-]*$/.test(url.hostname)
      && /^(?:\/[a-zA-Z0-9._~-]+)*\/wp-json\/wp\/v2\/?$/.test(url.pathname)
  } catch {
    return false
  }
}
