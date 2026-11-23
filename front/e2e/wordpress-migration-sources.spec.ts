import { expect, test } from '@playwright/test'
import type { components } from '../src/api/schema'

const importPath = '/api/v1/admin/imports/wordpress'
const sourcesPath = `${importPath}/sources`
const historyPath = `${importPath}/runs`
const legacy = 'news-legacy'
const runId = '88888888-8888-4888-8888-888888888888'

type Source = components['schemas']['WordPressImportSourceCollection']['items'][number]

function sourceSummary(source: string, id: string): Source {
  return {
    source, runCount: 3, studioRuns: 1, cliRuns: 2,
    latestRun: {
      id, source, input: 'rest', scope: 'all', channel: 'cli', completedAt: '2026-11-23T12:00:00Z', counts: { imported: 1 },
    },
  }
}

test('finds migration sources, restores source paging, and opens scoped run history', async ({ page }) => {
  const recent = sourceSummary('news-recent', '99999999-9999-4999-8999-999999999999')
  const archived = sourceSummary(legacy, runId)
  const store = sourceSummary('store', '77777777-7777-4777-8777-777777777777')
  const report: components['schemas']['WordPressImportReport'] = {
    source: legacy, input: 'rest', mode: 'apply', counts: { imported: 1 }, skippedTypes: {},
    items: [{ id: '104', type: 'page', title: 'Imported news archive', status: 'publish', state: 'imported', sourcePath: '/news', targetPath: '/news-archive' }],
    media: [], authors: [], taxonomy: { terms: [], assignments: [] },
  }
  const sourceRequests: Record<string, string>[] = []
  const runRequests: Record<string, string>[] = []
  const reportRequests: string[] = []
  page.on('request', (request) => {
    const { pathname, searchParams } = new URL(request.url())
    if (pathname === sourcesPath) sourceRequests.push(Object.fromEntries(searchParams))
    else if (pathname === historyPath) runRequests.push(Object.fromEntries(searchParams))
    else if (pathname.startsWith(`${historyPath}/`)) reportRequests.push(pathname)
  })
  await page.route('**/api/v1/**', async (route) => {
    const { pathname, searchParams } = new URL(route.request().url())
    if (pathname === '/api/v1/setup') await route.fulfill({ json: { required: false } })
    else if (pathname === '/api/v1/auth/me') {
      await route.fulfill({ json: { id: runId, email: 'owner@morfira.test', roles: ['ROLE_OWNER'], adminLocale: 'en' } })
    } else if (pathname === sourcesPath) {
      const pageNumber = Number(searchParams.get('page'))
      const prefix = searchParams.get('prefix')
      const items = !prefix ? [recent, store] : pageNumber === 1 ? [recent] : [archived]
      await route.fulfill({ json: { items, page: pageNumber, hasMore: prefix === 'news-' && pageNumber === 1 } })
    } else if (pathname === historyPath) {
      const items = searchParams.get('source') === legacy ? [archived.latestRun] : [recent.latestRun, archived.latestRun]
      await route.fulfill({ json: { items, page: 1, hasMore: false } })
    } else if (pathname === `${historyPath}/${runId}`) await route.fulfill({ json: report })
    else await route.abort()
  })

  await page.goto('/admin/import')
  const sourceKey = page.getByRole('textbox', { name: 'Source key', exact: true })
  await sourceKey.fill('current-wizard')
  expect(sourceRequests).toEqual([])
  await page.getByRole('button', { name: 'Import history', exact: true }).click()
  const historyTable = page.getByRole('table', { name: 'Import history', exact: true })
  await expect(historyTable).toBeVisible()
  await page.getByRole('button', { name: 'Migration sources', exact: true }).click()
  const sourceTable = page.getByRole('table', { name: 'Migration sources', exact: true })
  await expect(sourceTable.getByRole('row', { name: /store/ })).toBeVisible()
  expect(reportRequests).toEqual([])

  const prefix = page.getByRole('textbox', { name: 'Source key prefix', exact: true })
  await prefix.fill('news-')
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click()
  await expect(sourceTable.getByRole('row', { name: /store/ })).toHaveCount(0)
  await page.getByRole('button', { name: 'Next report page', exact: true }).click()
  const sourceRow = sourceTable.getByRole('row', { name: /news-legacy/ })
  await expect(sourceRow).toBeVisible()
  await expect(sourceRow.getByText('Imported as draft · 1', { exact: true })).toBeVisible()
  await sourceRow.getByRole('button', { name: 'Open latest report', exact: true }).click()
  await expect(page.getByText('Imported news archive', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Back to sources', exact: true }).click()
  await expect(sourceRow).toBeVisible()
  await expect(prefix).toHaveValue('news-')
  await expect(page.getByText('Page 2', { exact: true })).toBeVisible()

  await sourceRow.getByRole('button', { name: 'View source runs', exact: true }).click()
  await expect(sourceKey).toHaveValue(legacy)
  await expect(historyTable.getByRole('row', { name: /news-legacy/ })).toBeVisible()
  await expect(historyTable.getByRole('row', { name: /news-recent/ })).toHaveCount(0)
  await page.getByRole('button', { name: 'Back to import', exact: true }).click()
  await expect(sourceKey).toHaveValue('current-wizard')

  expect(sourceRequests).toEqual([{ page: '1' }, { page: '1', prefix: 'news-' }, { page: '2', prefix: 'news-' }, { page: '2', prefix: 'news-' }])
  expect(runRequests).toEqual([{ page: '1' }, { page: '1', source: legacy }])
  expect(reportRequests).toEqual([`${historyPath}/${runId}`])
})
