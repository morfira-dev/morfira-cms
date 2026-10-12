import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { uploadMedia, type MediaAsset } from './api'

const asset: MediaAsset = {
  id: '0199b348-1d8f-7e2f-8672-9b8356792e42',
  originalName: 'mountain.png',
  mimeType: 'image/webp',
  size: 12345,
  width: 1600,
  height: 900,
  defaultAlt: 'Mountain at sunrise',
  variants: [{ url: '/media/example/480.webp', width: 480, height: 270 }],
  createdAt: '2026-10-10T12:00:00+00:00',
}

afterEach(() => {
  vi.unstubAllGlobals()
  resetCsrfToken()
})

describe('uploadMedia', () => {
  it('sends the image and alt as multipart without setting Content-Type', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(asset), { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    const file = new File(['image'], 'mountain.png', { type: 'image/png' })
    await expect(uploadMedia(file, 'Mountain at sunrise')).resolves.toEqual(asset)

    const [, uploadInit] = fetchMock.mock.calls[1]
    const headers = uploadInit?.headers as Headers
    const body = uploadInit?.body as FormData

    expect(headers.get('X-CSRF-Token')).toBe('csrf-token')
    expect(headers.has('Content-Type')).toBe(false)
    expect(body.get('file')).toBe(file)
    expect(body.get('alt')).toBe('Mountain at sunrise')
  })
})
