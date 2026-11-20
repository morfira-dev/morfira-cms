import { expect, test } from '@playwright/test'
import type { components, operations } from '../src/api/schema'

type Report = components['schemas']['WordPressImportReport']
type ImportRequest = operations['importWordPressRest']['requestBody']['content']['application/json']

const basePath = '/api/v1/admin/imports/wordpress'
const apiUrl = 'https://wordpress.example.test/wp-json/wp/v2'
const source = 'legacy-rest'
const runId = '4131137b-3f18-4bb0-a2bd-1d08b26cbaa9'
const draftId = '2d1661fc-54a6-4a66-a123-3c8f006ee3db'
const assetId = '099027af-2b3e-4fd2-88c1-25154789a0b3'
const cdnHost = 'cdn.example.test'
const plannedItems: Report['items'] = [
  { id: '141', type: 'page', title: 'REST welcome', status: 'publish', state: 'planned', sourcePath: '/welcome', targetPath: '/rest-welcome' },
  { id: '142', type: 'post', title: 'REST bulletin', status: 'publish', state: 'planned', sourcePath: '/news', targetPath: '/content/wordpress-posts/rest-news' },
]

test('previews, selects and reopens a public WordPress REST import', async ({ page }) => {
  const calls: ImportRequest[] = []
  let savedReport: Report | undefined
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (path === '/api/v1/setup') await route.fulfill({ json: { required: false } })
    else if (path === '/api/v1/auth/me') {
      await route.fulfill({ json: { id: draftId, email: 'owner@morfira.test', roles: ['ROLE_OWNER'], adminLocale: 'ru' } })
    } else if (path === '/api/v1/auth/csrf') await route.fulfill({ json: { token: 'fixture-csrf' } })
    else if (path === `${basePath}/rest`) {
      const request = route.request()
      expect(request.method()).toBe('POST')
      expect(request.headers()['x-csrf-token']).toBe('fixture-csrf')
      const body: ImportRequest = request.postDataJSON()
      calls.push(body)
      const preview: Report = {
        source: body.source, input: 'rest', mode: 'dry-run', counts: { planned: 2 }, skippedTypes: {},
        items: plannedItems, media: [{
          id: '143',
          state: body.downloadMedia ? (body.allowedMediaHosts?.includes(cdnHost) ? 'planned' : 'invalid') : 'media_directory_required',
          reason: body.downloadMedia && !body.allowedMediaHosts?.includes(cdnHost) ? 'The WordPress attachment host is not allowed.' : undefined,
        }, { id: '144', state: 'already_imported', assetId }],
        taxonomy: { terms: [], assignments: [] }, authors: [],
      }
      if (body.mode === 'dry-run') await route.fulfill({ json: preview })
      else {
        savedReport = {
          ...preview, mode: 'apply', scope: 'selection', counts: { imported: 1 },
          items: [{ ...plannedItems[0], state: 'imported', draft: { kind: 'page', id: draftId } }],
          media: [{ id: '143', state: 'imported', assetId }],
        }
        await route.fulfill({ json: savedReport })
      }
    } else if (path === `${basePath}/runs`) {
      await route.fulfill({ json: { items: [{
        id: runId, source, input: 'rest', scope: 'selection', completedAt: '2026-11-20T00:00:00Z', counts: { imported: 1 },
      }], page: 1, hasMore: false } })
    } else if (path === `${basePath}/runs/${runId}` && savedReport) await route.fulfill({ json: savedReport })
    else await route.abort()
  })

  await page.goto('/admin/import')
  const format = page.getByRole('combobox', { name: 'Формат источника' })
  await expect(format).toHaveText('Файл WXR')
  await format.click()
  await page.getByRole('option', { name: 'WordPress REST API', exact: true }).click()
  const url = page.getByRole('textbox', { name: 'URL WordPress REST API' })
  await url.fill(apiUrl)
  await page.getByRole('textbox', { name: 'Ключ источника' }).fill(source)
  const previewButton = page.getByRole('button', { name: 'Предпросмотр импорта', exact: true })
  const applyButton = page.getByRole('button', { name: 'Создать черновики', exact: true })
  const report = page.getByRole('table', { name: 'Отчёт импорта WordPress' })
  const media = page.getByRole('table', { name: 'Вложения', exact: true })
  const downloadImages = page.getByRole('checkbox', { name: 'Скачать общедоступные изображения', exact: true })
  const allowedHosts = page.getByRole('textbox', { name: 'Разрешённые хосты CDN', exact: true })
  const includeBulletin = report.getByRole('row', { name: /REST bulletin/ }).getByRole('checkbox')

  await expect(downloadImages).not.toBeChecked()
  await expect(allowedHosts).toHaveCount(0)
  await expect(downloadImages).toHaveAccessibleDescription(/до восьми новых изображений/)
  await previewButton.click()
  await expect(report.getByText('REST welcome')).toBeVisible()
  await expect(media.getByText('Нужны файлы вложений')).toBeVisible()
  const missingFilesHint = page.getByText('Чтобы перенести недостающие файлы вложений (1), включите «Скачать общедоступные изображения» и повторите предпросмотр.', { exact: true })
  await expect(missingFilesHint).toBeVisible()
  await includeBulletin.uncheck()
  await downloadImages.check()
  await expect(report).toHaveCount(0)
  await expect(applyButton).toHaveCount(0)
  await previewButton.click()
  await expect(includeBulletin).toBeChecked()
  await expect(media.getByText('Некорректные данные')).toBeVisible()
  await expect(missingFilesHint).toHaveCount(0)
  await expect(media.getByText('Хост вложения WordPress не разрешён.', { exact: true })).toBeVisible()
  await expect(media.getByText(/укажите его точное имя в поле «Разрешённые хосты CDN»/)).toBeVisible()
  await includeBulletin.uncheck()
  await allowedHosts.fill('https://cdn.example.test')
  await expect(previewButton).toBeDisabled()
  await expect(report).toHaveCount(0)
  await expect(applyButton).toHaveCount(0)
  await allowedHosts.fill('CDN.Example.Test\nImages.Example.Test')
  await previewButton.click()
  await expect(includeBulletin).toBeChecked()
  await expect(media.getByText('Готово к импорту')).toBeVisible()
  await expect(media.getByText('Хост вложения WordPress не разрешён.', { exact: true })).toHaveCount(0)
  await url.fill('https://changed.example.test/wp-json/wp/v2')
  await expect(report).toHaveCount(0)
  await expect(applyButton).toHaveCount(0)
  await url.fill(apiUrl)
  await previewButton.click()
  await includeBulletin.uncheck()
  await expect(page.getByText('Изображения будут скачаны из всего источника.', { exact: false })).toBeVisible()
  await applyButton.click()

  const draftLink = report.getByRole('link', { name: 'Открыть черновик в новой вкладке', exact: true })
  await expect(draftLink).toHaveAttribute('href', `/admin/pages?draft=${draftId}`)
  await expect(media.getByRole('cell', { name: assetId })).toBeVisible()
  expect(calls).toEqual([
    { apiUrl, source, mode: 'dry-run' },
    { apiUrl, source, mode: 'dry-run', downloadMedia: true },
    { apiUrl, source, mode: 'dry-run', downloadMedia: true, allowedMediaHosts: [cdnHost, 'images.example.test'] },
    { apiUrl, source, mode: 'dry-run', downloadMedia: true, allowedMediaHosts: [cdnHost, 'images.example.test'] },
    { apiUrl, source, mode: 'apply', selectedIds: '141', downloadMedia: true, allowedMediaHosts: [cdnHost, 'images.example.test'] },
  ])
  await page.getByRole('button', { name: 'История импорта', exact: true }).click()
  await page.getByRole('row', { name: /legacy-rest/ }).getByRole('button', { name: 'Открыть отчёт', exact: true }).click()
  await expect(draftLink).toHaveAttribute('href', `/admin/pages?draft=${draftId}`)
  await expect(media.getByRole('cell', { name: assetId })).toBeVisible()
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: 'Скачать отчёт JSON', exact: true }).click(),
  ])
  expect(download.suggestedFilename()).toBe('wordpress-import-legacy-rest-rest-apply.json')
})
