import { readFile } from 'node:fs/promises'
import { expect, test } from '@playwright/test'
import type { components, operations } from '../src/api/schema'

type Report = components['schemas']['WordPressImportReport']
type Material = Report['items'][number]
type ImportRequest = operations['importWordPressRest']['requestBody']['content']['application/json']

const importPath = '/api/v1/admin/imports/wordpress'
const apiUrl = 'https://wordpress.example.test/wp-json/wp/v2'
const source = 'material-review'
const runId = '4131137b-3f18-4bb0-a2bd-1d08b26cbaa9'
const draftId = '2d1661fc-54a6-4a66-a123-3c8f006ee3db'

const cleanMaterials: Material[] = Array.from({ length: 25 }, (_, index) => ({
  id: String(101 + index),
  title: `Ordinary material ${index + 1}`,
  type: 'page',
  status: 'publish',
  state: 'planned',
  sourcePath: `/ordinary-${index + 1}`,
  targetPath: `/ordinary-${index + 1}`,
}))

const preview: Report = {
  source, input: 'rest', mode: 'dry-run', counts: { planned: 27 }, skippedTypes: {},
  items: [
    ...cleanMaterials,
    {
      id: '126', title: 'Page requiring review', type: 'page', status: 'publish', state: 'planned',
      sourcePath: '/review-page', targetPath: '/review-page', redirect: 'conflict',
      images: { inline: 0, missingInline: 3, featured: 'missing' },
    },
    {
      id: '127', title: 'Post requiring review', type: 'post', status: 'publish', state: 'planned',
      sourcePath: '/review-post', targetPath: '/content/wordpress-posts/review-post', redirect: 'unsupported',
      images: { inline: 1, missingInline: 1, featured: 'none' }, author: 'missing',
    },
  ],
  media: [], taxonomy: { terms: [], assignments: [] }, authors: [],
}

const applied: Report = {
  ...preview, mode: 'apply', scope: 'selection', counts: { imported: 26 },
  items: preview.items.slice(1).map((item): Material => ({
    ...item,
    state: 'imported',
    ...(item.id === '126' ? { draft: { kind: 'page', id: draftId } } : {}),
  })),
}

test('reviews overlapping WordPress issues across preview, result and history', async ({ page }) => {
  const submissions: ImportRequest[] = []
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (path === '/api/v1/setup') await route.fulfill({ json: { required: false } })
    else if (path === '/api/v1/auth/me') {
      await route.fulfill({ json: { id: draftId, email: 'owner@morfira.test', roles: ['ROLE_OWNER'], adminLocale: 'en' } })
    } else if (path === '/api/v1/auth/csrf') await route.fulfill({ json: { token: 'fixture-csrf' } })
    else if (path === `${importPath}/rest`) {
      const request: ImportRequest = route.request().postDataJSON()
      submissions.push(request)
      await route.fulfill({ json: request.mode === 'dry-run' ? preview : applied })
    } else if (path === `${importPath}/runs`) {
      await route.fulfill({ json: { items: [{
        id: runId, source, input: 'rest', scope: 'selection', channel: 'studio', completedAt: '2026-11-23T00:00:00Z', counts: applied.counts,
      }], page: 1, hasMore: false } })
    } else if (path === `${importPath}/runs/${runId}`) await route.fulfill({ json: applied })
    else await route.abort()
  })

  await page.goto('/admin/import')
  await page.getByRole('combobox', { name: 'Import source', exact: true }).click()
  await page.getByRole('option', { name: 'WordPress REST API', exact: true }).click()
  await page.getByRole('textbox', { name: 'WordPress REST API URL' }).fill(apiUrl)
  await page.getByRole('textbox', { name: 'Source key', exact: true }).fill(source)
  await page.getByRole('button', { name: 'Preview import', exact: true }).click()

  const table = page.getByRole('table', { name: 'WordPress import report' })
  const allIssues = page.getByRole('button', { name: 'Materials with issues · 2', exact: true })
  const missingInline = page.getByRole('button', { name: 'Missing inline images · 2', exact: true })
  const missingFeatured = page.getByRole('button', { name: 'Missing featured images · 1', exact: true })
  const missingAuthor = page.getByRole('button', { name: 'Missing author profiles · 1', exact: true })
  const redirects = page.getByRole('button', { name: 'Redirect issues · 2', exact: true })
  const excluded = table.getByRole('row', { name: /Ordinary material 1\b/ }).getByRole('checkbox')
  const reviewPage = table.getByRole('row', { name: /Page requiring review/ })
  const reviewPost = table.getByRole('row', { name: /Post requiring review/ })

  await expect(missingInline).toBeVisible()
  await expect(missingFeatured).toBeVisible()
  await expect(missingAuthor).toBeVisible()
  await expect(redirects).toBeVisible()
  await excluded.uncheck()
  await expect(reviewPage).toHaveCount(0)
  await page.getByRole('button', { name: 'Next report page', exact: true }).click()
  await expect(reviewPage).toBeVisible()
  await allIssues.click()
  await expect(allIssues).toHaveAttribute('aria-pressed', 'true')
  await expect(table.getByRole('row')).toHaveCount(3)
  await expect(reviewPage).toBeVisible()
  await expect(reviewPost).toBeVisible()

  const search = page.getByRole('textbox', { name: 'Search by title, ID or path' })
  await search.fill('/review-post')
  await expect(reviewPage).toHaveCount(0)
  await expect(reviewPost).toBeVisible()
  await missingFeatured.click()
  await expect(page.getByText('No items match the selected filters.', { exact: true })).toBeVisible()
  await missingFeatured.click()
  await expect(missingFeatured).toHaveAttribute('aria-pressed', 'false')
  await expect(reviewPost).toBeVisible()
  await missingAuthor.click()
  await page.getByRole('button', { name: 'Clear filters', exact: true }).click()
  await expect(search).toHaveValue('')
  await expect(missingAuthor).toHaveAttribute('aria-pressed', 'false')
  await expect(excluded).not.toBeChecked()

  await allIssues.click()
  await page.getByRole('button', { name: 'Create drafts', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Import result' })).toBeVisible()
  expect(submissions).toEqual([
    { apiUrl, source, mode: 'dry-run' },
    { apiUrl, source, mode: 'apply', selectedIds: applied.items.map((item) => item.id).join(',') },
  ])
  await missingFeatured.click()
  await expect(reviewPost).toHaveCount(0)
  await expect(reviewPage.getByRole('link', { name: 'Open draft in a new tab' }))
    .toHaveAttribute('href', `/admin/pages?draft=${draftId}`)

  await page.getByRole('button', { name: 'Import history', exact: true }).click()
  await page.getByRole('table', { name: 'Import history', exact: true })
    .getByRole('button', { name: 'View report', exact: true }).click()
  await missingAuthor.click()
  await expect(reviewPage).toHaveCount(0)
  await expect(reviewPost).toBeVisible()
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: 'Download JSON report', exact: true }).click(),
  ])
  expect(download.suggestedFilename()).toBe('wordpress-import-material-review-rest-apply.json')
  expect(JSON.parse(await readFile(await download.path(), 'utf8'))).toEqual(applied)
})
