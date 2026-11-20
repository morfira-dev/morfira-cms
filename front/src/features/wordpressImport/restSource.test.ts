import { describe, expect, it } from 'vitest'
import { isWordPressRestNamespaceUrl, parseWordPressMediaHosts } from './restSource'

describe('isWordPressRestNamespaceUrl', () => {
  it.each([
    'https://blog.example/wp-json/wp/v2',
    'https://blog.example:443/wordpress-2.1/wp-json/wp/v2/',
  ])('accepts a complete HTTPS namespace %s', (url) => {
    expect(isWordPressRestNamespaceUrl(url)).toBe(true)
  })

  it.each([
    '',
    'http://blog.example/wp-json/wp/v2',
    'https://blog.example',
    'https://blog.example/wp-json/wp/v2/posts',
    'https://blog.example/wp-json/wp/v2?context=edit',
    'https://blog.example/wp-json/wp/v2#anchor',
    'https://user:password@blog.example/wp-json/wp/v2',
    'https://blog.example:8443/wp-json/wp/v2',
    'https://blog.example/old/../wp-json/wp/v2',
    'https://blog.example/old/%2e%2e/wp-json/wp/v2',
    `https://blog.example/${'a'.repeat(512)}/wp-json/wp/v2`,
  ])('rejects an invalid namespace %s', (url) => {
    expect(isWordPressRestNamespaceUrl(url)).toBe(false)
  })
})

describe('parseWordPressMediaHosts', () => {
  it('normalizes names, removes duplicates and ignores blank lines', () => {
    expect(parseWordPressMediaHosts(' CDN.example \r\n\r\ncdn.example\r\nimages-2.example\r\n'))
      .toEqual(['cdn.example', 'images-2.example'])
    expect(parseWordPressMediaHosts('\n  \n')).toEqual([])
  })

  it('accepts up to eight nonempty entries before deduplicating', () => {
    expect(parseWordPressMediaHosts(Array(8).fill('cdn.example').join('\n'))).toEqual(['cdn.example'])
    expect(parseWordPressMediaHosts(Array(9).fill('cdn.example').join('\n'))).toBeNull()
  })

  it.each([
    '*.example',
    'https://cdn.example',
    'cdn.example:443',
    'cdn.example/path',
    'uploads..example',
    'uploads-.example',
    'uploads._example',
    'uploads.example.',
    'exämple.org',
    'cdn',
    '192.0.2.1',
    'uploads.internal',
    'uploads.home.arpa',
    `${'a'.repeat(64)}.example`,
    `${Array(4).fill('a'.repeat(63)).join('.')}.example`,
  ])('rejects unsupported media hostname %s', (host) => {
    expect(parseWordPressMediaHosts(host)).toBeNull()
  })
})
