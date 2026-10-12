import { expect, test } from '@playwright/test'

test('owner creates, edits and publishes a page through the production surface', async ({ page, request }) => {
  await page.goto('/admin')

  await expect(page.getByRole('heading', { name: 'Create the CMS owner' })).toBeVisible()
  await page.getByLabel('Email').fill('owner@morfira.test')
  await page.locator('input[type="password"]').first().fill('Morfira-beta-2026!')
  await page.getByLabel('Confirm password').fill('Morfira-beta-2026!')
  await page.getByRole('button', { name: 'Create owner' }).click()

  await expect(page.getByText('owner@morfira.test')).toBeVisible()
  await page.getByRole('link', { name: 'Pages' }).click()
  await expect(page.getByRole('heading', { name: 'Pages' })).toBeVisible()

  await page.getByRole('textbox', { name: 'Title' }).fill('Beta home')
  await page.getByRole('textbox', { name: 'Slug' }).fill('beta-home')
  await page.getByRole('button', { name: 'Create draft' }).click()
  await expect(page.getByRole('cell', { name: 'Beta home' })).toBeVisible()

  await page.getByRole('row', { name: /Beta home/ }).getByRole('button', { name: 'Edit' }).click()
  await page.getByRole('button', { name: 'Add Hero' }).click()
  await page.getByLabel('Heading').fill('Morfira beta is live')
  await page.getByRole('button', { name: 'Save revision' }).click()
  await expect(page.getByText('Draft revision 2')).toBeVisible()
  await page.getByRole('button', { name: 'Publish', exact: true }).click()
  await expect(page.getByText(/Revision 2 is live/)).toBeVisible()

  const publicPage = await request.get('/beta-home')
  expect(publicPage.status()).toBe(200)
  expect(await publicPage.text()).toContain('Morfira beta is live')

  const headlessPage = await request.get('/api/v1/content/pages/beta-home')
  expect(headlessPage.status()).toBe(200)
  await expect(headlessPage.json()).resolves.toMatchObject({ slug: 'beta-home', sourceRevision: 2 })
})
