import { readFile } from 'node:fs/promises'
import { expect, test } from '@playwright/test'
import type { components } from '../src/api/schema'

test.setTimeout(90_000)

test('owner creates, edits and publishes a page through the production surface', async ({ browser, page, request }) => {
  const ownerEmail = 'owner@morfira.test'
  const ownerPassword = 'Morfira-beta-2026!'
  await page.goto('/admin')

  await expect(page.getByRole('heading', { name: 'Create the CMS owner' })).toBeVisible()
  await page.getByRole('button', { name: 'Russian' }).click()
  await expect(page.getByRole('heading', { name: 'Создание владельца CMS' })).toBeVisible()
  await page.getByLabel('Электронная почта').fill(ownerEmail)
  await page.locator('input[type="password"]').first().fill(ownerPassword)
  await page.getByLabel('Подтвердите пароль').fill(ownerPassword)
  const [loginResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/api/v1/auth/login') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Создать владельца' }).click(),
  ])
  expect(loginResponse.ok()).toBeTruthy()

  await expect(page.getByText(ownerEmail)).toBeVisible()
  await page.getByRole('button', { name: 'Interface language' }).click()
  await expect(page.getByRole('link', { name: 'Страницы' })).toBeVisible()
  await page.reload()
  await expect(page.getByRole('link', { name: 'Страницы' })).toBeVisible()
  await page.getByRole('link', { name: 'Страницы' }).click()
  await expect(page.getByRole('heading', { name: 'Страницы' })).toBeVisible()

  await page.getByRole('textbox', { name: 'Заголовок' }).fill('Beta home')
  await page.getByRole('textbox', { name: 'Slug' }).fill('beta-home')
  await page.getByRole('button', { name: 'Создать черновик' }).click()
  await expect(page.getByRole('cell', { name: 'Beta home' })).toBeVisible()

  await page.getByRole('row', { name: /Beta home/ }).getByRole('button', { name: 'Изменить' }).click()
  await page.getByLabel('Тема').click()
  await page.getByRole('option', { name: 'Atelier' }).click()
  await page.getByRole('button', { name: 'Добавить Hero' }).click()
  await page.getByLabel('Заголовок блока').fill('Morfira beta is live')
  await page.getByLabel('Сохранить текущий холст как').fill('Launch hero')
  await page.getByRole('button', { name: 'Сохранить секцию' }).click()
  await expect(page.getByText('Повторно используемая секция создана.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить RichText' }).click()
  const pageRichText = page.getByRole('textbox', { name: 'Содержимое форматированного текста' })
  await expect(pageRichText).toHaveClass(/\bmw-content\b/)
  await expect(pageRichText).toHaveText('Start writing useful content.')
  await pageRichText.click()
  await pageRichText.press('ControlOrMeta+a')
  await pageRichText.evaluate((target) => {
    const clipboardData = new DataTransfer()
    clipboardData.setData('text/html', '<p>Styled page copy</p>')
    target.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData }))
  })
  await expect(pageRichText).toHaveText('Styled page copy')
  await pageRichText.press('ControlOrMeta+a')
  await pageRichText.press('ControlOrMeta+i')
  await expect(pageRichText.locator('em')).toHaveText('Styled page copy')
  await pageRichText.press('End')
  await pageRichText.press('Enter')
  const editorToolbar = page.getByRole('toolbar').filter({
    has: page.getByRole('button', { name: 'Вставить таблицу', exact: true }),
  })
  await expect(editorToolbar.getByRole('button', { name: 'Поиск и замена', exact: true })).toBeVisible()
  await editorToolbar.getByRole('button', { name: 'Вставить таблицу', exact: true }).click()
  const tablePanel = page.getByRole('form', { name: 'Вставить таблицу', exact: true })
  await tablePanel.getByLabel('Строки таблицы', { exact: true }).fill('2')
  await tablePanel.getByLabel('Столбцы таблицы', { exact: true }).fill('2')
  await tablePanel.getByRole('button', { name: 'Вставить таблицу', exact: true }).click()
  const table = pageRichText.locator('table')
  await expect(table.locator('tr')).toHaveCount(2)
  await expect(table.locator('td')).toHaveCount(4)
  await table.locator('td').first().click()
  await page.keyboard.insertText('Editor table cell')
  await expect(table.locator('td').first()).toHaveText('Editor table cell')
  await page.getByRole('button', { name: 'Сохранить ревизию' }).click()
  await expect(page.getByText('Ревизия черновика 2')).toBeVisible()
  await page.reload()
  await page.getByRole('row', { name: /Beta home/ }).getByRole('button', { name: 'Изменить' }).click()
  await expect(pageRichText.locator('em')).toHaveText('Styled page copy')
  await expect(table.locator('td')).toHaveCount(4)
  await expect(table.locator('td').first()).toHaveText('Editor table cell')
  const publicPagePath = '/beta-home'
  const headlessPagePath = '/api/v1/content/pages/beta-home'
  expect((await request.get(publicPagePath)).status()).toBe(404)
  expect((await request.get(headlessPagePath)).status()).toBe(404)
  await page.getByRole('button', { name: 'Опубликовать', exact: true }).click()
  await expect(page.getByText(/Ревизия 2 опубликована/)).toBeVisible()

  const publicPage = await request.get(publicPagePath)
  expect(publicPage.status()).toBe(200)
  const publicPageHtml = await publicPage.text()
  expect(publicPageHtml).toContain('Morfira beta is live')
  expect(publicPageHtml).toContain('<em>Styled page copy</em>')
  const publishedTable = /<table\b[\s\S]*?Editor table cell[\s\S]*?<\/table>/
  expect(publicPageHtml).toMatch(publishedTable)

  const headlessPage = await request.get(headlessPagePath)
  expect(headlessPage.status()).toBe(200)
  await expect(headlessPage.json()).resolves.toMatchObject({
    slug: 'beta-home', locale: 'en', sourceRevision: 2, layout: { theme: 'atelier', template: 'editorial' },
    blocks: expect.arrayContaining([expect.objectContaining({
      type: 'richText', data: { content: expect.stringMatching(publishedTable) },
    })]),
  })

  const updatedHero = 'Morfira beta has an updated heading'
  await page.getByLabel('Заголовок блока').fill(updatedHero)
  await page.getByRole('button', { name: 'Сохранить ревизию' }).click()
  await expect(page.getByText('Ревизия черновика 3')).toBeVisible()
  const unchangedPublicPage = await request.get(publicPagePath)
  expect(unchangedPublicPage.status()).toBe(200)
  const unchangedPublicHtml = await unchangedPublicPage.text()
  expect(unchangedPublicHtml).toContain('Morfira beta is live')
  expect(unchangedPublicHtml).not.toContain(updatedHero)
  const unchangedHeadlessPage = await request.get(headlessPagePath)
  expect(unchangedHeadlessPage.status()).toBe(200)
  await expect(unchangedHeadlessPage.json()).resolves.toMatchObject({
    sourceRevision: 2,
    blocks: expect.arrayContaining([expect.objectContaining({
      type: 'hero', data: expect.objectContaining({ heading: 'Morfira beta is live' }),
    })]),
  })
  await page.getByRole('button', { name: 'Опубликовать', exact: true }).click()
  await expect(page.getByText(/Ревизия 3 опубликована/)).toBeVisible()

  const secondBrowser = await browser.newContext()
  try {
    const secondPage = await secondBrowser.newPage()
    await secondPage.goto(publicPagePath)
    await expect(secondPage.getByRole('heading', { name: updatedHero })).toBeVisible()

    await secondPage.goto('/admin/')
    await expect(secondPage.getByRole('heading', { name: 'Welcome back' })).toBeVisible()
    await secondPage.getByLabel('Email').fill(ownerEmail)
    await secondPage.getByLabel('Password').fill(ownerPassword)
    await secondPage.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(secondPage.getByRole('link', { name: 'Страницы', exact: true })).toBeVisible()

    await secondPage.goto(publicPagePath)
    await expect(secondPage.locator('html')).toHaveAttribute('lang', 'en')
    await expect(secondPage.getByRole('heading', { name: updatedHero })).toBeVisible()
  } finally {
    await secondBrowser.close()
  }
  const republishedHeadlessPage = await request.get(headlessPagePath)
  expect(republishedHeadlessPage.status()).toBe(200)
  await expect(republishedHeadlessPage.json()).resolves.toMatchObject({
    locale: 'en', sourceRevision: 3,
    blocks: expect.arrayContaining([expect.objectContaining({
      type: 'hero', data: expect.objectContaining({ heading: updatedHero }),
    })]),
  })

  await page.getByRole('textbox', { name: 'Заголовок' }).first().fill('Русская версия')
  await page.getByRole('textbox', { name: 'Slug' }).first().fill('russkaya-versiya')
  await page.getByLabel('Язык контента').first().fill('ru-RU')
  await page.getByLabel('Перевод страницы').click()
  await page.getByRole('option', { name: 'Beta home (en)' }).click()
  await page.getByRole('button', { name: 'Создать черновик' }).click()
  await page.getByRole('row', { name: /Русская версия/ }).getByRole('button', { name: 'Изменить' }).click()
  await page.getByText(/Launch hero · блоков: 1/).click()
  await page.getByLabel('Заголовок блока').fill('Morfira говорит по-русски')
  await page.getByRole('button', { name: 'Сохранить ревизию' }).click()
  await expect(page.getByText('Ревизия черновика 2')).toBeVisible()
  await page.getByRole('button', { name: 'Опубликовать', exact: true }).click()
  await expect(page.getByText(/Ревизия 2 опубликована/)).toBeVisible()

  const localizedPage = await request.get('/ru-RU/russkaya-versiya')
  expect(localizedPage.status()).toBe(200)
  expect(await localizedPage.text()).toContain('hreflang="en"')
  expect(await localizedPage.text()).toContain('Morfira говорит по-русски')

  const localizedApi = await request.get('/api/v1/content/pages/ru-RU/russkaya-versiya')
  expect(localizedApi.status()).toBe(200)
  await expect(localizedApi.json()).resolves.toMatchObject({ locale: 'ru-RU', url: '/ru-RU/russkaya-versiya' })

  await page.goto('/admin/content-types')
  await expect(page.getByRole('heading', { name: 'Типы контента' })).toBeVisible()
  await page.getByLabel('Название').first().fill('Articles')
  await page.getByLabel('API-имя').first().fill('articles')
  await page.getByRole('button', { name: 'Создать тип' }).click()
  await expect(page.getByText('Тип контента «Articles» создан.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить поле' }).click()
  await page.getByLabel('Подпись').fill('Body')
  await page.getByLabel('Ключ').fill('body')
  await page.getByLabel('Тип').click()
  await page.getByRole('option', { name: 'richText' }).click()
  await page.getByRole('button', { name: 'Добавить поле' }).click()
  await page.getByLabel('Подпись').last().fill('Cover image')
  await page.getByLabel('Ключ').last().fill('coverImage')
  await page.getByLabel('Тип').last().click()
  await page.getByRole('option', { name: 'media' }).click()
  await page.getByRole('button', { name: 'Сохранить схему' }).click()
  await expect(page.getByText('Версия схемы 2 сохранена.')).toBeVisible()

  await page.goto('/admin/entries')
  await expect(page.getByRole('heading', { name: 'Материалы' })).toBeVisible()
  await page.getByLabel('Тип контента').click()
  await page.getByRole('option', { name: 'Articles (articles)' }).click()
  await page.getByRole('button', { name: 'Новый' }).click()
  await page.getByRole('textbox', { name: 'Заголовок', exact: true }).fill('Rich article')
  await page.getByLabel('Slug').fill('rich-article')
  const richText = page.getByRole('textbox', { name: 'Body' })
  await expect(richText).toHaveClass(/\bmw-content\b/)
  await richText.fill('Safe formatted content')
  await richText.press('ControlOrMeta+a')
  await richText.press('ControlOrMeta+b')
  await expect(richText.locator('strong')).toHaveText('Safe formatted content')
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.getByText('Черновик версии 1 сохранён.')).toBeVisible()
  await page.getByRole('button', { name: 'Опубликовать', exact: true }).click()
  await expect(page.getByText('Версия 1 опубликована.')).toBeVisible()

  const publishedEntryApi = await request.get('/api/v1/content/entries/articles/rich-article')
  expect(publishedEntryApi.status()).toBe(200)
  const publishedEntry = await publishedEntryApi.json()
  expect(publishedEntry.values.body).toContain('<strong>Safe formatted content</strong>')

  const publishedEntryPage = await request.get('/content/articles/rich-article')
  expect(publishedEntryPage.status()).toBe(200)
  expect(await publishedEntryPage.text()).toContain('<strong>Safe formatted content</strong>')

  await page.goto('/admin/content-types')
  await page.getByLabel('Название').first().fill('Authors')
  await page.getByLabel('API-имя').first().fill('authors')
  await page.getByRole('button', { name: 'Создать тип' }).click()
  await expect(page.getByText('Тип контента «Authors» создан.')).toBeVisible()

  await page.goto('/admin/entries')
  await page.getByLabel('Тип контента').click()
  await page.getByRole('option', { name: 'Authors (authors)' }).click()
  await page.getByRole('button', { name: 'Новый' }).click()
  await page.getByRole('textbox', { name: 'Заголовок', exact: true }).fill('Author One')
  await page.getByLabel('Slug').fill('author-one')
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.getByRole('button', { name: /Author One/ })).toBeVisible()
  await page.getByRole('button', { name: 'Новый' }).click()
  await page.getByRole('textbox', { name: 'Заголовок', exact: true }).fill('Author Two')
  await page.getByLabel('Slug').fill('author-two')
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.getByRole('button', { name: /Author Two/ })).toBeVisible()

  await page.goto('/admin/content-types')
  await page.getByRole('button', { name: /Articles/ }).click()
  await page.getByRole('button', { name: 'Добавить поле' }).click()
  await page.getByLabel('Подпись').last().fill('Related authors')
  await page.getByLabel('Ключ').last().fill('relatedAuthors')
  await page.getByLabel('Тип').last().click()
  await page.getByRole('option', { name: 'relation' }).click()
  await page.getByLabel('Целевой тип').click()
  await page.getByRole('option', { name: 'Authors' }).click()
  await page.getByLabel('Разрешить несколько').click()
  await page.getByRole('button', { name: 'Сохранить схему' }).click()
  await expect(page.getByText('Версия схемы 3 сохранена.')).toBeVisible()

  await page.goto('/admin/entries')
  await page.getByLabel('Тип контента').click()
  await page.getByRole('option', { name: 'Articles (articles)' }).click()
  await page.getByRole('button', { name: /Rich article/ }).click()
  await expect(richText.locator('strong')).toHaveText('Safe formatted content')
  await page.getByLabel('Related authors').click()
  await page.getByRole('option', { name: 'Author Two' }).click()
  await page.getByRole('option', { name: 'Author One' }).click()
  await page.keyboard.press('Escape')
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.getByText('Черновик версии 2 сохранён.')).toBeVisible()
  await page.getByRole('button', { name: 'Опубликовать', exact: true }).click()
  await expect(page.getByText('Версия 2 опубликована.')).toBeVisible()

  const relatedEntryApi = await request.get('/api/v1/content/entries/articles/rich-article')
  const relatedEntry = await relatedEntryApi.json()
  expect(relatedEntry.values.relatedAuthors).toHaveLength(2)
  expect(relatedEntry.values.relatedAuthors[0]).not.toBe(relatedEntry.values.relatedAuthors[1])
  expect(await (await request.get('/content/articles/rich-article')).text()).toContain('<ol>')

  await page.goto('/admin/entries')
  await page.getByLabel('Тип контента').click()
  await page.getByRole('option', { name: 'Authors (authors)' }).click()
  await page.getByRole('button', { name: /Author One/ }).click()
  await expect(page.getByRole('heading', { name: 'Используется в материалах' })).toBeVisible()
  await page.getByRole('button', { name: /Rich article/ }).click()
  await expect(page.getByRole('textbox', { name: 'Заголовок', exact: true })).toHaveValue('Rich article')

  await page.getByRole('textbox', { name: 'Заголовок', exact: true }).fill('Changed article')
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.getByText('Черновик версии 3 сохранён.')).toBeVisible()
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByText('Ревизия 2 · Rich article').locator('xpath=../..').getByRole('button', { name: 'Восстановить' }).click()
  await expect(page.getByText('Ревизия 2 восстановлена как ревизия 4.')).toBeVisible()
  await expect(page.getByRole('textbox', { name: 'Заголовок', exact: true })).toHaveValue('Rich article')
  await expect(richText.locator('strong')).toHaveText('Safe formatted content')
  await expect((await request.get('/api/v1/content/entries/articles/rich-article')).json()).resolves.toMatchObject({ sourceVersion: 2, title: 'Rich article' })

  const media = {
    mimeType: 'image/webp', size: 1024, width: 640, height: 480, defaultAlt: 'Test image',
    collection: null, tags: [], variants: [], createdAt: '2026-11-20T00:00:00+00:00',
  }
  await page.route('**/api/v1/admin/media', (route) => route.fulfill({ json: { items: [
    { ...media, id: '0199b348-1d8f-7e2f-8672-9b8356792e43', originalName: 'private.webp', isPrivate: true },
    { ...media, id: '0199b348-1d8f-7e2f-8672-9b8356792e42', originalName: 'public.webp' },
  ] } }))
  await page.goto('/admin/media')
  const publicFile = page.getByText('public.webp', { exact: true })
  const privateFile = page.getByText('private.webp', { exact: true })
  const visibility = page.getByRole('combobox', { name: 'Видимость', exact: true })
  await expect(publicFile).toBeVisible()
  await expect(privateFile).toBeVisible()
  await expect(page.getByText('Закрытое изображение', { exact: true })).toBeVisible()
  await visibility.click()
  await page.getByRole('option', { name: 'Закрытые', exact: true }).click()
  await expect(publicFile).toHaveCount(0)
  await expect(privateFile).toBeVisible()
  await visibility.click()
  await page.getByRole('option', { name: 'Публичные', exact: true }).click()
  await expect(publicFile).toBeVisible()
  await expect(privateFile).toHaveCount(0)

  await page.goto('/admin/pages')
  await page.getByRole('row', { name: /Beta home/ }).getByRole('button', { name: 'Изменить' }).click()
  const privateImageWarning = 'Замените или уберите закрытые изображения перед публикацией.'
  const socialImage = page.getByRole('combobox', { name: 'Изображение для соцсетей', exact: true })
  await socialImage.click()
  await page.getByRole('option', { name: 'private.webp (закрытое)', exact: true }).click()
  await expect(socialImage).toHaveAccessibleDescription(privateImageWarning)
  await socialImage.click()
  await page.getByRole('option', { name: 'Без изображения', exact: true }).click()
  await expect(socialImage).not.toHaveAccessibleDescription(privateImageWarning)

  await page.getByRole('button', { name: 'Добавить изображение', exact: true }).click()
  const imageAsset = page.getByRole('combobox', { name: 'Медиафайл' })
  await expect(imageAsset).toHaveText('public.webp (640 × 480)')
  await imageAsset.click()
  await page.getByRole('option', { name: 'private.webp (закрытое) (640 × 480)', exact: true }).click()
  await expect(imageAsset).toHaveAccessibleDescription(privateImageWarning)
  await imageAsset.click()
  await page.getByRole('option', { name: 'public.webp (640 × 480)', exact: true }).click()
  await expect(imageAsset).not.toHaveAccessibleDescription(privateImageWarning)

  await page.goto('/admin/entries')
  await page.getByLabel('Тип контента').click()
  await page.getByRole('option', { name: 'Articles (articles)' }).click()
  await page.getByRole('button', { name: /Rich article/ }).click()
  const entryImage = page.getByRole('combobox', { name: 'Cover image', exact: true })
  await entryImage.click()
  await page.getByRole('option', { name: 'private.webp (закрытое)', exact: true }).click()
  await expect(entryImage).toHaveAccessibleDescription(privateImageWarning)
  await entryImage.click()
  await page.getByRole('option', { name: 'public.webp', exact: true }).click()
  await expect(entryImage).not.toHaveAccessibleDescription(privateImageWarning)

  await page.getByRole('link', { name: 'Импорт WordPress', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Импорт WordPress', exact: true })).toBeVisible()
  const sourceKey = page.getByRole('textbox', { name: 'Ключ источника', exact: true })
  await sourceKey.fill('studio-e2e')
  const wxrUpload = page.getByLabel('Файл WXR', { exact: true })
  const studioExport = {
    name: 'studio-export.xml',
    mimeType: 'application/xml',
    buffer: Buffer.from(`<rss xmlns:wp="http://wordpress.org/export/1.2/"
      xmlns:content="http://purl.org/rss/1.0/modules/content/">
      <channel><link>https://wordpress.example/</link><wp:wxr_version>1.2</wp:wxr_version>
      <wp:category><wp:category_nicename>studio-import</wp:category_nicename><wp:cat_name>Studio import</wp:cat_name></wp:category>
      <item><title>Studio import page</title><link>https://wordpress.example/legacy/studio-import-page/</link>
      <wp:post_id>99001</wp:post_id><wp:post_name>studio-import-page</wp:post_name>
      <wp:post_type>page</wp:post_type><wp:status>publish</wp:status>
      <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>99002</wp:meta_value></wp:postmeta>
      <content:encoded><![CDATA[<h1 class="wp-block-heading" id="intro">Introduction</h1><p>Imported through Studio. <a href="https://wordpress.example/legacy/studio-second-page/">Read the second page</a></p>]]></content:encoded></item>
      <item><title>Studio attachment</title><wp:post_id>99002</wp:post_id>
      <wp:post_type>attachment</wp:post_type><wp:status>inherit</wp:status>
      <wp:attachment_url>https://wordpress.example/wp-content/uploads/studio.png</wp:attachment_url></item>
      <item><title>Studio second page</title><link>https://wordpress.example/legacy/studio-second-page/</link>
      <wp:post_id>99003</wp:post_id><wp:post_name>studio-second-page</wp:post_name>
      <wp:post_type>page</wp:post_type><wp:status>publish</wp:status>
      <content:encoded><![CDATA[<p>Imported in the second batch. <a href="https://wordpress.example/?page_id=99001#intro">Read the first page</a></p>]]></content:encoded></item>
      </channel></rss>`),
  }
  await wxrUpload.setInputFiles(studioExport)
  const previewImport = page.getByRole('button', { name: 'Предпросмотр импорта', exact: true })
  const applyImport = page.getByRole('button', { name: 'Создать черновики', exact: true })
  const importResponse = () => page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/admin/imports/wordpress') && response.request().method() === 'POST',
  )
  const listDrafts = async () => {
    const response = await page.request.get('/api/v1/admin/pages')
    expect(response.ok()).toBeTruthy()
    return (await response.json() as components['schemas']['PageCollection']).items
  }
  const importedDrafts = async () => {
    return (await listDrafts())
      .map((item) => item.slug)
      .filter((slug) => ['studio-import-page', 'studio-second-page'].includes(slug))
      .sort()
  }
  const getDraft = async (slug: string) => {
    const draft = (await listDrafts()).find((item) => item.slug === slug)
    if (!draft) throw new Error(`Draft ${slug} is missing.`)

    const response = await page.request.get(`/api/v1/admin/pages/${draft.id}`)
    expect(response.ok()).toBeTruthy()
    return await response.json() as components['schemas']['Page']
  }

  const [dryRun] = await Promise.all([importResponse(), previewImport.click()])
  expect(dryRun.ok()).toBeTruthy()
  const dryRunReport = await dryRun.json()
  expect(dryRunReport).toMatchObject({ mode: 'dry-run', counts: { planned: 2 } })
  await expect(page.getByText('/studio-import-page', { exact: true })).toBeVisible()
  expect(await importedDrafts()).toHaveLength(0)

  const reportTable = page.getByRole('table', { name: 'Отчёт импорта WordPress' })
  const reportRow = reportTable.getByRole('row', { name: /Studio import page/ })
  const openDraftLabel = 'Открыть черновик в новой вкладке'
  await expect(reportRow.getByRole('link', { name: openDraftLabel })).toHaveCount(0)
  await expect(reportRow.getByText('Миниатюра отсутствует')).toBeVisible()
  await expect(reportRow.getByText('Редирект запланирован')).toBeVisible()
  const attachmentTable = page.getByRole('table', { name: 'Вложения', exact: true })
  await expect(attachmentTable.getByRole('row', { name: /99002/ })).toContainText('Нужны файлы вложений')
  await page.getByRole('combobox', { name: 'Раздел отчёта' }).click()
  await page.getByRole('option', { name: 'Термины таксономии · 1', exact: true }).click()
  const termTable = page.getByRole('table', { name: 'Термины таксономии', exact: true })
  await expect(termTable.getByRole('row', { name: /studio-import/ })).toContainText('Готово к импорту')

  const search = page.getByRole('textbox', { name: 'Поиск по заголовку, ID или пути' })
  await search.fill('studio IMPORT')
  await expect(reportRow).toBeVisible()
  const itemType = page.getByRole('combobox', { name: 'Тип материала' })
  await itemType.click()
  await page.getByRole('option', { name: 'Записи WordPress', exact: true }).click()
  await expect(page.getByText('Нет материалов по заданным фильтрам.')).toBeVisible()
  await expect(reportRow).toHaveCount(0)

  const downloadReport = async (mode: string, report: unknown) => {
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Скачать отчёт JSON' }).click(),
    ])
    expect(download.suggestedFilename()).toBe(`wordpress-import-studio-e2e-${mode}.json`)
    expect(JSON.parse(await readFile(await download.path(), 'utf8'))).toEqual(report)
  }
  await downloadReport('dry-run', dryRunReport)

  await sourceKey.fill('other-source')
  await expect(page.getByText('/studio-import-page', { exact: true })).toHaveCount(0)
  await sourceKey.fill('studio-e2e')
  await previewImport.click()
  await expect(applyImport).toBeEnabled()
  const firstSelection = reportRow.getByRole('checkbox')
  const secondSelection = reportTable.getByRole('row', { name: /Studio second page/ }).getByRole('checkbox')
  await expect(firstSelection).toBeChecked()
  await expect(secondSelection).toBeChecked()
  await search.fill('Studio second page')
  await page.getByRole('button', { name: 'Снять выбор с материалов по фильтру', exact: true }).click()
  await expect(page.getByText('Выбрано материалов: 1 из 2', { exact: true })).toBeVisible()
  await search.clear()
  await expect(firstSelection).toBeChecked()
  await expect(secondSelection).not.toBeChecked()
  const openImportHistory = page.getByRole('button', { name: 'История импорта', exact: true })
  await openImportHistory.click()
  await expect(page.getByText('Завершённых импортов пока нет.')).toBeVisible()
  await page.getByRole('button', { name: 'Вернуться к импорту', exact: true }).click()
  await expect(firstSelection).toBeChecked()
  await expect(secondSelection).not.toBeChecked()
  await firstSelection.uncheck()
  await expect(applyImport).toBeDisabled()
  await firstSelection.check()

  const [apply] = await Promise.all([importResponse(), applyImport.click()])
  expect(apply.ok()).toBeTruthy()
  const applyReport = await apply.json()
  expect(applyReport).toMatchObject({
    mode: 'apply',
    scope: 'selection',
    counts: { imported: 1 },
    items: [{ id: '99001', state: 'imported' }],
    taxonomy: { terms: [] },
  })
  await downloadReport('apply', applyReport)
  expect(await importedDrafts()).toEqual(['studio-import-page'])
  expect((await request.get('/studio-import-page')).status()).toBe(404)
  const firstDraft = await getDraft('studio-import-page')
  const originalForwardLink = 'href="https://wordpress.example/legacy/studio-second-page/"'
  const localBacklink = 'href="/studio-import-page#intro"'
  const richTextWith = (href: string) => expect.arrayContaining([expect.objectContaining({
    type: 'richText', data: { content: expect.stringContaining(href) },
  })])
  expect(firstDraft.blocks).toEqual(richTextWith(originalForwardLink))
  expect(firstDraft.blocks).toEqual(richTextWith('<h2 id="intro">Introduction</h2>'))

  const previewAgain = page.getByRole('button', { name: 'Повторить предпросмотр', exact: true })
  const [remaining] = await Promise.all([importResponse(), previewAgain.click()])
  expect(remaining.ok()).toBeTruthy()
  await expect(remaining.json()).resolves.toMatchObject({ mode: 'dry-run', counts: { already_imported: 1, planned: 1 } })
  await expect(reportRow.getByRole('checkbox')).toHaveCount(0)
  await expect(secondSelection).toBeChecked()
  const [secondApply] = await Promise.all([importResponse(), applyImport.click()])
  expect(secondApply.ok()).toBeTruthy()
  await expect(secondApply.json()).resolves.toMatchObject({ mode: 'apply', counts: { already_imported: 1, imported: 1 } })
  expect(await importedDrafts()).toEqual(['studio-import-page', 'studio-second-page'])
  const firstDraftAfterSecondImport = await getDraft('studio-import-page')
  expect(firstDraftAfterSecondImport.version).toBe(firstDraft.version)
  expect(firstDraftAfterSecondImport.blocks).toEqual(firstDraft.blocks)
  const secondDraft = await getDraft('studio-second-page')
  expect(secondDraft.blocks).toEqual(richTextWith(localBacklink))
  expect((await request.get('/studio-second-page')).status()).toBe(404)
  expect((await request.get('/api/v1/content/pages/studio-second-page')).status()).toBe(404)

  const historyTable = page.getByRole('table', { name: 'История импорта', exact: true })
  const runRows = historyTable.getByRole('row').filter({ hasText: 'studio-e2e' })
  await page.getByRole('button', { name: 'История этого источника', exact: true }).click()
  await expect(runRows).toHaveCount(2)
  await expect(sourceKey).toHaveValue('studio-e2e')
  await page.getByRole('button', { name: 'Вернуться к импорту', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Результат импорта', exact: true })).toBeVisible()
  const [replay] = await Promise.all([importResponse(), previewAgain.click()])
  expect(replay.ok()).toBeTruthy()
  await expect(replay.json()).resolves.toMatchObject({ mode: 'dry-run', counts: { already_imported: 2 } })
  expect(await importedDrafts()).toEqual(['studio-import-page', 'studio-second-page'])

  const csrf = await page.request.get('/api/v1/auth/csrf')
  expect(csrf.ok()).toBeTruthy()
  const { token } = await csrf.json() as components['schemas']['CsrfToken']
  const publish = await page.request.post(`/api/v1/admin/pages/${secondDraft.id}/publish`, {
    data: { version: secondDraft.version },
    headers: { 'X-CSRF-Token': token },
  })
  expect(publish.ok()).toBeTruthy()
  const publishTarget = await page.request.post(`/api/v1/admin/pages/${firstDraft.id}/publish`, {
    data: { version: firstDraft.version },
    headers: { 'X-CSRF-Token': token },
  })
  expect(publishTarget.ok()).toBeTruthy()
  const fragmentTarget = await request.get('/studio-import-page')
  expect(fragmentTarget.status()).toBe(200)
  expect(await fragmentTarget.text()).toContain('<h2 id="intro">Introduction</h2>')

  const publicImportedPage = await request.get('/studio-second-page')
  expect(publicImportedPage.status()).toBe(200)
  expect(await publicImportedPage.text()).toContain(localBacklink)
  const headlessImportedPage = await request.get('/api/v1/content/pages/studio-second-page')
  expect(headlessImportedPage.status()).toBe(200)
  await expect(headlessImportedPage.json()).resolves.toMatchObject({ blocks: richTextWith(localBacklink) })

  await page.reload()
  await openImportHistory.click()
  await expect(runRows).toHaveCount(2)
  await expect(runRows.first()).toContainText('Весь источник')
  await expect(runRows.getByRole('cell', { name: 'Файл WXR', exact: true })).toHaveCount(2)
  const selectiveRun = runRows.filter({ hasText: 'Выбранные материалы' })
  await expect(selectiveRun).toContainText('Импортировано как черновик')
  await selectiveRun.getByRole('button', { name: 'Открыть отчёт', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Отчёт завершённого импорта', exact: true })).toBeVisible()
  await expect(reportRow).toBeVisible()
  await downloadReport('apply', applyReport)
  const [pageEditor] = await Promise.all([
    page.context().waitForEvent('page'),
    reportRow.getByRole('link', { name: openDraftLabel }).click(),
  ])
  await expect(pageEditor).toHaveURL(new RegExp(`/admin/pages\\?draft=${firstDraft.id}$`))
  await expect(pageEditor.getByRole('heading', { name: 'Изменение страницы', exact: true })).toBeVisible()
  await expect(pageEditor.locator('input[value="Studio import page"]')).toBeVisible()
  await pageEditor.reload()
  await expect(pageEditor.locator('input[value="Studio import page"]')).toBeVisible()
  const importedHeading = pageEditor.locator('.mw-content h2', { hasText: 'Introduction' })
  await expect(importedHeading).toHaveAttribute('data-morfira-heading-anchor', 'intro')
  await importedHeading.click()
  await pageEditor.keyboard.press('End')
  await pageEditor.keyboard.insertText(' updated')
  await pageEditor.getByRole('button', { name: 'Сохранить ревизию', exact: true }).click()
  await expect.poll(async () => (await getDraft('studio-import-page')).blocks).toEqual(
    richTextWith('<h2 id="intro">Introduction updated</h2>'),
  )
  await pageEditor.close()
  await expect(reportRow).toBeVisible()
  await page.getByRole('button', { name: 'Вернуться к истории', exact: true }).click()
  await expect(runRows).toHaveCount(2)

  await selectiveRun.getByRole('button', { name: 'Открыть отчёт', exact: true }).click()
  await page.getByRole('button', { name: 'Продолжить импорт из этого источника', exact: true }).click()
  await expect(sourceKey).toHaveValue('studio-e2e')
  await expect(previewImport).toBeDisabled()
  await expect(applyImport).toHaveCount(0)
  await wxrUpload.setInputFiles(studioExport)
  const [resumed] = await Promise.all([importResponse(), previewImport.click()])
  expect(resumed.ok()).toBeTruthy()
  await expect(resumed.json()).resolves.toMatchObject({ mode: 'dry-run', counts: { already_imported: 2 } })
  expect(await importedDrafts()).toEqual(['studio-import-page', 'studio-second-page'])

  await sourceKey.fill('studio-e2e-post')
  await page.getByLabel('Файл WXR', { exact: true }).setInputFiles({
    name: 'studio-post.xml',
    mimeType: 'application/xml',
    buffer: Buffer.from(`<rss xmlns:wp="http://wordpress.org/export/1.2/"><channel><wp:wxr_version>1.2</wp:wxr_version>
      <item><title>Studio imported post</title><wp:post_id>99004</wp:post_id><wp:post_name>studio-imported-post</wp:post_name>
      <wp:post_type>post</wp:post_type><wp:status>draft</wp:status></item></channel></rss>`),
  })
  const [postPlan] = await Promise.all([importResponse(), previewImport.click()])
  expect(postPlan.ok()).toBeTruthy()
  await expect(postPlan.json()).resolves.toMatchObject({ mode: 'dry-run', counts: { planned: 1 } })
  const [postApply] = await Promise.all([importResponse(), applyImport.click()])
  expect(postApply.ok()).toBeTruthy()
  const postReport = await postApply.json() as components['schemas']['WordPressImportReport']
  const postDraft = postReport.items[0]?.draft
  if (postDraft?.kind !== 'entry') throw new Error('The imported post did not return an entry draft reference.')
  const [entryEditor] = await Promise.all([
    page.context().waitForEvent('page'),
    reportTable.getByRole('row', { name: /Studio imported post/ }).getByRole('link', { name: openDraftLabel }).click(),
  ])
  await expect(entryEditor).toHaveURL(new RegExp(`/admin/entries\\?draft=${postDraft.id}&type=${postDraft.contentTypeId}$`))
  await expect(entryEditor.getByRole('heading', { name: 'Изменение материала', exact: true })).toBeVisible()
  await expect(entryEditor.getByRole('textbox', { name: 'Заголовок', exact: true })).toHaveValue('Studio imported post')
  await entryEditor.reload()
  await expect(entryEditor.getByRole('textbox', { name: 'Заголовок', exact: true })).toHaveValue('Studio imported post')
  await entryEditor.close()
})
