import { expect, test } from '@playwright/test'
import type { components, operations } from '../src/api/schema'

type RunCollection = components['schemas']['WordPressImportRunCollection']
type ListQuery = NonNullable<operations['listWordPressImportRuns']['parameters']['query']>

const historyPath = '/api/v1/admin/imports/wordpress/runs'
const source = 'migration-site'
const rest: RunCollection['items'][number] = {
  id: '2d1661fc-54a6-4a66-a123-3c8f006ee3db',
  source,
  input: 'rest',
  scope: 'selection',
  completedAt: '2026-11-20T00:00:00Z',
  counts: { imported: 1 },
}
const wxr: RunCollection['items'][number] = {
  ...rest,
  id: '4131137b-3f18-4bb0-a2bd-1d08b26cbaa9',
  input: 'wxr',
  scope: 'all',
}
const report: components['schemas']['WordPressImportReport'] = {
  source,
  input: 'rest',
  scope: 'selection',
  mode: 'apply',
  counts: { already_imported: 1 },
  skippedTypes: {},
  items: [{
    id: '14',
    type: 'page',
    status: 'publish',
    title: 'Archived migration report',
    sourcePath: '/archived',
    targetPath: '/archived',
    state: 'already_imported',
  }],
  media: [],
  taxonomy: { terms: [], assignments: [] },
  authors: [],
}

test('filters migration runs before paging and restores the list after reading a report', async ({ page }) => {
  const started: [string, Record<string, string>][] = []
  page.on('request', (request) => {
    const url = new URL(request.url())
    if (request.method() === 'GET' && url.pathname.startsWith(historyPath)) {
      started.push([url.pathname, Object.fromEntries(url.searchParams)])
    }
  })
  await page.route('**/api/v1/**', async (route) => {
    const { pathname, searchParams } = new URL(route.request().url())
    if (pathname === '/api/v1/setup') await route.fulfill({ json: { required: false } })
    else if (pathname === '/api/v1/auth/me') {
      await route.fulfill({ json: { id: rest.id, email: 'owner@morfira.test', roles: ['ROLE_OWNER'], adminLocale: 'en' } })
    } else if (pathname === historyPath) {
      const pageNumber = Number(searchParams.get('page'))
      const hasFilters = ['source', 'input', 'scope'].some((key) => searchParams.has(key))
      const matching = searchParams.get('source') === source
        && searchParams.get('input') === 'rest'
        && searchParams.get('scope') === 'selection'
      const results: RunCollection = {
        items: matching ? [rest] : hasFilters ? [] : [rest, wxr],
        page: pageNumber,
        hasMore: matching || !hasFilters ? pageNumber === 1 : false,
      }
      await route.fulfill({ json: results })
    } else if (pathname === `${historyPath}/${rest.id}`) await route.fulfill({ json: report })
    else await route.abort()
  })

  const openHistory = page.getByRole('button', { name: 'Import history', exact: true })
  const history = page.getByRole('table', { name: 'Import history', exact: true })
  const next = page.getByRole('button', { name: 'Next report page', exact: true })
  const sourceKey = page.getByRole('textbox', { name: 'Source key', exact: true })
  const apply = page.getByRole('button', { name: 'Apply filters', exact: true })
  const clear = page.getByRole('button', { name: 'Clear filters', exact: true })
  const format = page.getByRole('combobox', { name: 'Import format', exact: true })
  const scope = page.getByRole('combobox', { name: 'Import scope', exact: true })

  await page.goto('/admin/import')
  await expect(openHistory).toBeVisible()
  expect(started).toEqual([])
  await openHistory.click()
  await expect(history.getByRole('cell', { name: 'WXR file', exact: true })).toBeVisible()
  await expect(history.getByRole('cell', { name: 'WordPress REST API', exact: true })).toBeVisible()
  await next.click()
  await expect(page.getByText('Page 2', { exact: true })).toBeVisible()

  await sourceKey.fill(source)
  await format.click()
  await page.getByRole('option', { name: 'WordPress REST API', exact: true }).click()
  await scope.click()
  await page.getByRole('option', { name: 'Selected items', exact: true }).click()
  expect(started).toEqual([[historyPath, { page: '1' }], [historyPath, { page: '2' }]])
  await apply.click()
  await expect(page.getByText('Page 1', { exact: true })).toBeVisible()
  await expect(history.getByRole('cell', { name: 'WXR file', exact: true })).toHaveCount(0)
  await next.click()
  await expect(page.getByText('Page 2', { exact: true })).toBeVisible()
  await history.getByRole('button', { name: 'View report', exact: true }).click()
  await expect(page.getByText('Archived migration report', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Back to history', exact: true }).click()
  await expect(page.getByText('Page 2', { exact: true })).toBeVisible()
  await expect(sourceKey).toHaveValue(source)
  await expect(format).toHaveText('WordPress REST API')
  await expect(scope).toHaveText('Selected items')

  await sourceKey.fill('missing-source')
  await apply.click()
  await expect(page.getByText('No completed imports match the selected filters.', { exact: true })).toBeVisible()
  await expect(history).toHaveCount(0)
  await clear.click()
  await expect(page.getByText('Page 1', { exact: true })).toBeVisible()
  await expect(sourceKey).toHaveValue('')
  await expect(format).toHaveText('All formats')
  await expect(scope).toHaveText('All scopes')

  await page.reload()
  await expect(page).toHaveURL(/\/admin\/import$/)
  await openHistory.click()
  await expect(history.getByRole('cell', { name: 'WXR file', exact: true })).toBeVisible()
  await page.waitForLoadState('networkidle')

  const filters = { source, input: 'rest', scope: 'selection' } satisfies Omit<ListQuery, 'page'>
  expect(started).toEqual([
    [historyPath, { page: '1' }],
    [historyPath, { page: '2' }],
    [historyPath, { page: '1', ...filters }],
    [historyPath, { page: '2', ...filters }],
    [`${historyPath}/${rest.id}`, {}],
    [historyPath, { page: '2', ...filters }],
    [historyPath, { page: '1', ...filters, source: 'missing-source' }],
    [historyPath, { page: '1' }],
    [historyPath, { page: '1' }],
  ])
})
