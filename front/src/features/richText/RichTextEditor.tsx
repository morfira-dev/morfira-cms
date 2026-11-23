import '@morfira-dev/wysiwyg-core/style.css'
import '@morfira-dev/wysiwyg-plugins/checklists.css'
import '@morfira-dev/wysiwyg-plugins/suggestions.css'
import '@morfira-dev/wysiwyg-plugins/tables.css'
import '@morfira-dev/wysiwyg-plugins/ui/editor.css'
import '@morfira-dev/wysiwyg-plugins/ui/forms.css'
import '@morfira-dev/wysiwyg-plugins/ui/search-highlights.css'
import type { EditorPlugin } from '@morfira-dev/wysiwyg-core'
import { checklists, checklistCommands } from '@morfira-dev/wysiwyg-plugins/checklists'
import { horizontalRuleCommands } from '@morfira-dev/wysiwyg-plugins/horizontal-rule'
import { createExtendedPreset } from '@morfira-dev/wysiwyg-plugins/presets/extended'
import { createSlashActions } from '@morfira-dev/wysiwyg-plugins/slash'
import { tableCommands } from '@morfira-dev/wysiwyg-plugins/tables'
import { createExtendedEditorUI } from '@morfira-dev/wysiwyg-plugins/ui/extended'
import { createSearchHighlights } from '@morfira-dev/wysiwyg-plugins/ui/search-highlights'
import { MorfiraEditor, type MorfiraEditorHandle } from '@morfira-dev/wysiwyg-react'
import { useLayoutEffect, useMemo, useRef } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import { Stack, Typography } from '../../ui'
import { uploadMedia } from '../media/api'
import { getPages } from '../pages/api'

interface RichTextEditorProps {
  value: string
  onChange: (html: string) => void
  label?: string
  required?: boolean
}

const slashOptions = [
  ['paragraph', 'Paragraph'], ['heading1', 'Heading 1'], ['heading2', 'Heading 2'],
  ['heading3', 'Heading 3'], ['heading4', 'Heading 4'], ['heading5', 'Heading 5'],
  ['heading6', 'Heading 6'], ['bulletList', 'Bullet list'], ['orderedList', 'Numbered list'],
  ['blockquote', 'Quote'], ['codeBlock', 'Code block'], ['table', 'Table'],
  ['checklist', 'Checklist'], ['horizontalRule', 'Horizontal rule'],
] as const

const headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as const
const headingAnchorAttribute = 'data-morfira-heading-anchor'
const headingAnchors: EditorPlugin = {
  name: 'morfira-heading-anchors',
  schema: headings.map((tag) => ({
    name: `morfira:heading-anchor:${tag}`,
    kind: 'attributes',
    tag,
    attributes: { [headingAnchorAttribute]: { type: 'text', maxLength: 256, singleLine: true } },
  })),
  setup() {},
}

export function RichTextEditor({ value, onChange, label, required = false }: RichTextEditorProps) {
  const { locale, t } = useAdminLocale()
  const translate = useRef(t)
  translate.current = t
  const editorRef = useRef<MorfiraEditorHandle>(null)
  const toolbarRef = useRef<HTMLDivElement>(null)
  const accessibleLabel = label ?? t('Rich text content')
  const placeholder = t('Start writing useful content…')
  const html = useMemo(() => convertHeadingAnchors(normalizeLegacyLinks(value), 'id', headingAnchorAttribute), [value])
  const preset = useMemo(() => createExtendedPreset({
    images: {
      maxFileSize: 10 * 1024 * 1024,
      upload: async (file, { signal }) => {
        const asset = await uploadMedia(file, file.name.slice(0, 300), signal)
        const variant = asset.variants.reduce((largest, item) => item.width > largest.width ? item : largest)
        return { src: variant.url, alt: asset.defaultAlt }
      },
    },
    markdown: { blocks: true, inline: true },
    autolink: { enabled: true },
    mentions: {
      persistent: true,
      provide: async ({ query, limit, signal }) => {
        const search = query.toLowerCase()
        const pages = await getPages(signal)
        return pages.filter((page) => page.title.toLowerCase().includes(search) || page.slug.includes(search))
          .slice(0, limit)
          .map((page) => ({ id: page.id, label: page.title, description: page.slug }))
      },
    },
    slash: {
      actions: {
        ...createSlashActions(),
        table: (editor) => tableCommands(editor).insert(3, 3),
        checklist: (editor) => checklistCommands(editor).insert(),
        horizontalRule: (editor) => horizontalRuleCommands(editor).insert(),
      },
      provide: ({ query, limit }) => {
        const search = query.toLowerCase()
        return slashOptions.map(([id, key]) => ({ id, label: translate.current(key), action: id }))
          .filter(({ id, label }) => id.toLowerCase().includes(search) || label.toLowerCase().includes(search))
          .slice(0, limit)
      },
    },
    plugins: [checklists(), headingAnchors],
  }), [])

  useLayoutEffect(() => {
    const editor = editorRef.current?.getEditor()
    const host = toolbarRef.current
    if (!editor || !host) return
    const ui = createExtendedEditorUI(host, editor, { overflow: 'wrap' })
    const highlights = createSearchHighlights(editor)

    return () => {
      highlights.destroy()
      ui.destroy()
    }
  }, [])

  useLayoutEffect(() => {
    const surface = editorRef.current?.getEditor()?.surface
    if (!surface) return
    surface.setAttribute('aria-label', accessibleLabel)
    surface.setAttribute('aria-required', String(required))
    surface.dataset.placeholder = placeholder
  }, [accessibleLabel, placeholder, required])

  return (
    <Stack spacing={0.75}>
      {label && (
        <Typography component="span" variant="body2" sx={{ color: 'text.secondary', fontWeight: 500 }}>
          {label}{required ? ' *' : ''}
        </Typography>
      )}
      <div ref={toolbarRef} />
      <MorfiraEditor
        ref={editorRef}
        plugins={preset.plugins}
        value={html}
        label={accessibleLabel}
        locale={locale}
        placeholder={placeholder}
        onChange={({ html: nextHtml }) => onChange(convertHeadingAnchors(nextHtml, headingAnchorAttribute, 'id'))}
      />
    </Stack>
  )
}

function convertHeadingAnchors(html: string, from: string, to: string): string {
  if (!html.includes(` ${from}=`)) return html

  const template = document.createElement('template')
  template.innerHTML = html
  const selector = headings.map((tag) => `${tag}[${from}]`).join(',')
  for (const heading of template.content.querySelectorAll(selector)) {
    const fragment = heading.getAttribute(from) ?? ''
    heading.removeAttribute(from)
    if (/^[\p{L}\p{N}_-][\p{L}\p{N}_.:-]{0,127}$/u.test(fragment)) heading.setAttribute(to, fragment)
  }

  return template.innerHTML
}

function normalizeLegacyLinks(html: string): string {
  if (!html.includes('href="//')) return html

  const template = document.createElement('template')
  template.innerHTML = html
  for (const anchor of template.content.querySelectorAll('a[href^="//"]')) {
    const href = anchor.getAttribute('href')
    if (href && /^\/\/[^/\\]/.test(href)) anchor.setAttribute('href', window.location.protocol + href)
  }

  return template.innerHTML
}
