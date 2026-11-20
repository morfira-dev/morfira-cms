import { expect, test } from '@playwright/test'

const dashboardReads = [
  '/api/v1/admin/pages',
  '/api/v1/admin/media',
  '/api/v1/admin/content-types',
  '/api/v1/admin/taxonomies',
]
const startupReads = ['/api/v1/setup', '/api/v1/auth/me', ...dashboardReads]

test('loads each admin startup resource once and keeps counts when switching language', async ({ page }) => {
  const started: string[] = []
  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (request.method() === 'GET' && startupReads.includes(path)) started.push(path)
  })
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (path === '/api/v1/setup') await route.fulfill({ json: { required: false } })
    else if (path === '/api/v1/auth/me') {
      await route.fulfill({ json: {
        id: '2d1661fc-54a6-4a66-a123-3c8f006ee3db',
        email: 'owner@morfira.test',
        roles: ['ROLE_OWNER'],
        adminLocale: 'en',
      } })
    } else if (dashboardReads.includes(path)) await route.fulfill({ json: { items: [] } })
    else if (path === '/api/v1/auth/csrf') await route.fulfill({ json: { token: 'test-csrf' } })
    else if (path === '/api/v1/auth/preferences') await route.fulfill({ status: 204 })
    else await route.abort()
  })

  await page.goto('/admin?from=direct#overview')
  await expect(page.getByRole('button', { name: 'Open', exact: true })).toHaveCount(4)
  await expect(page).toHaveURL(/\/admin\/\?from=direct#overview$/)
  await page.getByRole('button', { name: 'Interface language', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Открыть', exact: true })).toHaveCount(4)
  await page.waitForLoadState('networkidle')

  expect(started.sort()).toEqual([...startupReads].sort())

  await page.getByRole('link', { name: 'Импорт WordPress', exact: true }).click()
  await page.getByRole('link', { name: 'Обзор', exact: true }).click()
  await expect(page).toHaveURL(/\/admin\/$/)
  await page.reload()
  await expect(page.getByRole('button', { name: 'Open', exact: true })).toHaveCount(4)
  await expect(page).toHaveURL(/\/admin\/$/)
})
