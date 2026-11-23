import { expect, test, type Page, type Request } from '@playwright/test'
import type { components, operations } from '../src/api/schema'

type Report = components['schemas']['WordPressImportReport']
type Run = components['schemas']['WordPressImportRunCollection']['items'][number]
type RestRequest = operations['importWordPressRest']['requestBody']['content']['application/json']

const importPath = '/api/v1/admin/imports/wordpress'
const historyPath = `${importPath}/runs`
const fixtureId = '2d1661fc-54a6-4a66-a123-3c8f006ee3db'
const importedPage: Report['items'][number] = {
  id: '101', type: 'page', title: 'Previously imported page', status: 'publish', state: 'planned',
  sourcePath: '/previous', targetPath: '/previous',
}
const remainingPage: Report['items'][number] = {
  ...importedPage, id: '102', title: 'Remaining page', sourcePath: '/remaining', targetPath: '/remaining',
}

function makeRun(input: Run['input'], source: string): Run {
  return { id: fixtureId, source, input, scope: 'selection', channel: 'studio', completedAt: '2026-11-23T00:00:00Z', counts: { imported: 1 } }
}

function makeReport(run: Run, mode: Report['mode'], items: Report['items']): Report {
  const { input, source, scope } = run
  const counts: Report['counts'] = {}
  for (const { state } of items) counts[state] = (counts[state] ?? 0) + 1
  return {
    input, source, mode, ...(mode === 'apply' ? { scope } : {}), counts, skippedTypes: {}, items,
    media: [], taxonomy: { terms: [], assignments: [] }, authors: [],
  }
}

async function mockImport(page: Page, run: Run, report: Report, submit: (request: Request) => Report | Promise<Report>) {
  const historyQueries: Record<string, string>[] = []
  page.on('request', (request) => {
    const { pathname, searchParams } = new URL(request.url())
    if (request.method() === 'GET' && pathname === historyPath) {
      historyQueries.push(Object.fromEntries(searchParams))
    }
  })
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const { pathname } = new URL(request.url())
    if (pathname === '/api/v1/setup') await route.fulfill({ json: { required: false } })
    else if (pathname === '/api/v1/auth/me') {
      await route.fulfill({ json: { id: fixtureId, email: 'owner@morfira.test', roles: ['ROLE_OWNER'], adminLocale: 'en' } })
    } else if (pathname === '/api/v1/auth/csrf') await route.fulfill({ json: { token: 'fixture-csrf' } })
    else if (pathname === (run.input === 'rest' ? `${importPath}/rest` : importPath)) {
      expect(request.method()).toBe('POST')
      await route.fulfill({ json: await submit(request) })
    } else if (pathname === historyPath) await route.fulfill({ json: { items: [run], page: 1, hasMore: false } })
    else if (pathname === `${historyPath}/${run.id}`) await route.fulfill({ json: report })
    else await route.abort()
  })
  return historyQueries
}

test('continues a REST batch from the result and resumes it from history', async ({ page }) => {
  const run = makeRun('rest', 'rest-migration')
  const apiUrl = 'https://wordpress.example.test/wp-json/wp/v2'
  const resumedApiUrl = 'https://old-wordpress.example.test/wp-json/wp/v2'
  const settings = { apiUrl, source: run.source, downloadMedia: true, allowedMediaHosts: ['cdn.example.test'] }
  const initial = makeReport(run, 'dry-run', [importedPage, remainingPage])
  const applied = makeReport(run, 'apply', [{ ...importedPage, state: 'imported' }])
  const replay = makeReport(run, 'dry-run', [{ ...importedPage, state: 'already_imported' }, remainingPage])
  const submissions: RestRequest[] = []
  const historyQueries = await mockImport(page, run, applied, (request) => {
    const payload: RestRequest = request.postDataJSON()
    submissions.push(payload)
    if (payload.mode === 'apply') return applied
    return submissions.length === 1 ? initial : replay
  })

  await page.goto('/admin/import')
  const format = page.getByRole('combobox', { name: 'Import source', exact: true })
  const source = page.getByRole('textbox', { name: 'Source key', exact: true })
  const endpoint = page.getByRole('textbox', { name: 'WordPress REST API URL' })
  const downloadMedia = page.getByRole('checkbox', { name: 'Download public images', exact: true })
  const cdnHosts = page.getByRole('textbox', { name: 'Allowed CDN hosts' })
  const preview = page.getByRole('button', { name: 'Preview import', exact: true })
  const apply = page.getByRole('button', { name: 'Create drafts', exact: true })
  const remaining = page.getByRole('row', { name: /Remaining page/ }).getByRole('checkbox')
  await format.click()
  await page.getByRole('option', { name: 'WordPress REST API', exact: true }).click()
  await endpoint.fill(apiUrl)
  await source.fill(run.source)
  await downloadMedia.check()
  await cdnHosts.fill(settings.allowedMediaHosts[0])
  await preview.click()
  await remaining.uncheck()
  await apply.click()
  await expect(page.getByRole('heading', { name: 'Import result' })).toBeVisible()

  await page.getByRole('button', { name: 'View history for this source', exact: true }).click()
  await expect(page.getByRole('table', { name: 'Import history' })).toBeVisible()
  await expect(source).toHaveValue(run.source)
  expect(historyQueries).toEqual([{ page: '1', source: run.source }])
  await page.getByRole('button', { name: 'Back to import', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Import result' })).toBeVisible()
  await expect(endpoint).toHaveValue(apiUrl)
  await expect(downloadMedia).toBeChecked()
  await expect(cdnHosts).toHaveValue(settings.allowedMediaHosts[0])

  await page.getByRole('button', { name: 'Preview source again', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Import preview' })).toBeVisible()
  await expect(page.getByRole('row', { name: /Previously imported page/ })).toContainText('Already imported')
  await expect(remaining).toBeChecked()
  expect(submissions).toEqual([
    { ...settings, mode: 'dry-run' },
    { ...settings, mode: 'apply', selectedIds: importedPage.id },
    { ...settings, mode: 'dry-run' },
  ])

  await remaining.uncheck()
  const openHistory = page.getByRole('button', { name: 'Import history', exact: true })
  await openHistory.click()
  await page.getByRole('button', { name: 'Back to import', exact: true }).click()
  await expect(remaining).not.toBeChecked()
  await openHistory.click()
  await page.getByRole('button', { name: 'View report', exact: true }).click()
  await page.getByRole('button', { name: 'Continue from this source', exact: true }).click()
  await expect(format).toHaveText('WordPress REST API')
  await expect(source).toHaveValue(run.source)
  await expect(endpoint).toHaveValue('')
  await expect(downloadMedia).not.toBeChecked()
  await expect(cdnHosts).toHaveCount(0)
  await expect(page.getByText('Enter the WordPress REST API URL and preview the source to continue.')).toBeVisible()
  await expect(preview).toBeDisabled()
  await expect(apply).toHaveCount(0)
  expect(submissions).toHaveLength(3)

  await endpoint.fill(resumedApiUrl)
  await expect(preview).toBeEnabled()
  await expect(apply).toHaveCount(0)
  await preview.click()
  await expect(remaining).toBeChecked()
  expect(submissions.slice(3)).toEqual([{ apiUrl: resumedApiUrl, source: run.source, mode: 'dry-run' }])
})

test('resumes WXR history with the saved key and needs a new file', async ({ page }) => {
  const run = makeRun('wxr', 'archived-wxr')
  const otherSource = 'current-wxr'
  const applied = makeReport(run, 'apply', [{ ...importedPage, state: 'imported' }])
  const submissions: { source: FormDataEntryValue | null; mode: FormDataEntryValue | null; file: string | undefined }[] = []
  await mockImport(page, run, applied, async (request) => {
    const form = await new Response(request.postData(), { headers: { 'Content-Type': request.headers()['content-type'] } }).formData()
    const file = form.get('file')
    const source = form.get('source')
    submissions.push({ source, mode: form.get('mode'), file: typeof file === 'object' ? file?.name : undefined })
    return makeReport({ ...run, source: String(source) }, 'dry-run', [remainingPage])
  })

  await page.goto('/admin/import')
  const format = page.getByRole('combobox', { name: 'Import source', exact: true })
  const source = page.getByRole('textbox', { name: 'Source key', exact: true })
  const file = page.getByLabel('WXR export', { exact: true })
  const preview = page.getByRole('button', { name: 'Preview import', exact: true })
  const apply = page.getByRole('button', { name: 'Create drafts', exact: true })
  const selectFile = (name: string) => file.setInputFiles({ name, mimeType: 'application/xml', buffer: Buffer.from('<rss/>') })
  await source.fill(otherSource)
  await selectFile('current.xml')
  await preview.click()
  await expect(apply).toBeEnabled()

  await page.getByRole('button', { name: 'Import history', exact: true }).click()
  await page.getByRole('button', { name: 'View report', exact: true }).click()
  await page.getByRole('button', { name: 'Continue from this source', exact: true }).click()
  await expect(format).toHaveText('WXR file')
  await expect(source).toHaveValue(run.source)
  await expect(file).toHaveValue('')
  await expect(page.getByText('Choose the WXR file and preview the source to continue.')).toBeVisible()
  await expect(preview).toBeDisabled()
  await expect(apply).toHaveCount(0)
  expect(submissions).toEqual([{ source: otherSource, mode: 'dry-run', file: 'current.xml' }])

  await selectFile('archived.xml')
  await expect(preview).toBeEnabled()
  await expect(apply).toHaveCount(0)
  await preview.click()
  await expect(apply).toBeEnabled()
  expect(submissions.slice(1)).toEqual([{ source: run.source, mode: 'dry-run', file: 'archived.xml' }])
})
