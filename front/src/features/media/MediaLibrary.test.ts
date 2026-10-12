import { describe, expect, it } from 'vitest'
import { getMediaThumbnail } from './MediaLibrary'
import type { MediaAsset } from './api'

const asset: MediaAsset = {
  id: '0199b348-1d8f-7e2f-8672-9b8356792e42',
  originalName: 'mountain.png',
  mimeType: 'image/webp',
  size: 12345,
  width: 1600,
  height: 900,
  defaultAlt: 'Mountain at sunrise',
  variants: [
    { url: '/media/example/960.webp', width: 960, height: 540 },
    { url: '/media/example/480.webp', width: 480, height: 270 },
  ],
  createdAt: '2026-10-10T12:00:00+00:00',
}

describe('getMediaThumbnail', () => {
  it('chooses the smallest responsive variant without mutating the API value', () => {
    expect(getMediaThumbnail(asset)).toBe('/media/example/480.webp')
    expect(asset.variants[0]?.width).toBe(960)
  })
})
