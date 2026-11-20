import { expect, test } from '@playwright/test'

const pageId = '4131137b-3f18-4bb0-a2bd-1d08b26cbaa9'
const entryId = '2d1661fc-54a6-4a66-a123-3c8f006ee3db'
const typeId = '4db104e3-c094-4aa9-a289-c22b3052737c'
const missingId = '4604106e-ee24-4c1f-8851-8a8ab3632d55'
const timestamp = '2026-11-20T00:00:00Z'
const pageDraft = {
  id: pageId, title: 'Linked page draft', slug: 'linked-page', version: 1,
  createdAt: timestamp, updatedAt: timestamp, status: 'draft', locale: 'en', translationGroupId: pageId, blocks: [],
  seo: { title: '', description: '', noIndex: false, openGraphImageAssetId: null },
  layout: { theme: 'default', template: 'default', headerNavigationCode: null, footerNavigationCode: null },
}
const entryDraft = {
  id: entryId, title: 'Linked entry draft', slug: 'linked-entry', version: 1,
  createdAt: timestamp, updatedAt: timestamp, contentTypeId: typeId, values: {},
}
const contentType = {
  id: typeId, name: 'Linked types', apiName: 'linked-types', fields: [], version: 1, createdAt: timestamp, updatedAt: timestamp,
}

test('opens draft URLs directly with one set of editor reads and handles unavailable links', async ({ page }) => {
  const started: string[] = []
  page.on('request', (request) => {
    if (request.method() === 'GET') started.push(new URL(request.url()).pathname)
  })
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())
    switch (url.pathname) {
      case '/api/v1/setup':
        await route.fulfill({ json: { required: false } })
        break
      case '/api/v1/auth/me':
        await route.fulfill({ json: { id: missingId, email: 'editor@morfira.test', roles: ['ROLE_EDITOR'], adminLocale: 'en' } })
        break
      case '/api/v1/admin/pages':
        await route.fulfill({ json: { items: [pageDraft] } })
        break
      case '/api/v1/admin/settings':
        await route.fulfill({ json: { defaultLocale: 'en' } })
        break
      case '/api/v1/admin/content-types':
        await route.fulfill({ json: { items: [contentType] } })
        break
      case '/api/v1/admin/entries':
        await route.fulfill({ json: { items: url.searchParams.get('contentTypeId') === typeId ? [entryDraft] : [] } })
        break
      case `/api/v1/admin/entries/${entryId}`:
        await route.fulfill({ json: entryDraft })
        break
      case `/api/v1/admin/pages/${pageId}/schedule`:
      case `/api/v1/admin/entries/${entryId}/schedule`:
        await route.fulfill({ status: 404, json: { error: 'Schedule not found.' } })
        break
      default:
        await route.fulfill({ json: { items: [] } })
    }
  })

  const pageUrl = `/admin/pages?draft=${pageId}`
  await page.goto(pageUrl)
  await expect(page.getByRole('heading', { name: 'Edit page', exact: true })).toBeVisible()
  await expect(page.locator('input[value="Linked page draft"]')).toBeVisible()
  await page.waitForLoadState('networkidle')
  for (const resource of ['pages', 'media', 'navigation', 'sections', 'settings', 'themes']) {
    expect(started.filter((path) => path === `/api/v1/admin/${resource}`)).toHaveLength(1)
  }
  for (const suffix of ['redirects', 'revisions', 'schedule']) {
    expect(started.filter((path) => path === `/api/v1/admin/pages/${pageId}/${suffix}`)).toHaveLength(1)
  }

  started.length = 0
  const entryUrl = `/admin/entries?draft=${entryId}&type=${typeId}`
  await page.goto(entryUrl)
  await expect(page.getByRole('heading', { name: 'Edit entry', exact: true })).toBeVisible()
  await expect(page.getByRole('textbox', { name: 'Title', exact: true })).toHaveValue('Linked entry draft')
  await page.waitForLoadState('networkidle')
  for (const resource of ['content-types', 'entries', 'media', 'taxonomies']) {
    expect(started.filter((path) => path === `/api/v1/admin/${resource}`)).toHaveLength(1)
  }
  for (const suffix of ['', '/references', '/revisions', '/terms', '/schedule']) {
    expect(started.filter((path) => path === `/api/v1/admin/entries/${entryId}${suffix}`)).toHaveLength(1)
  }
  await page.reload()
  await expect(page.getByRole('textbox', { name: 'Title', exact: true })).toHaveValue('Linked entry draft')

  await page.getByRole('link', { name: 'Dashboard', exact: true }).click()
  await expect(page).toHaveURL(/\/admin\/$/)
  await page.goto(`/admin/pages?draft=${missingId}`)
  await expect(page.getByText('The linked draft could not be opened.', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Pages', exact: true })).toBeVisible()
  await page.goto(`/admin/entries?draft=invalid&type=${typeId}`)
  await expect(page.getByText('This draft link is invalid.', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Entries', exact: true })).toBeVisible()
})
