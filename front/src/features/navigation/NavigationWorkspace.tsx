import { type FormEvent, useEffect, useRef, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Divider,
  FormControlLabel,
  Stack,
  Switch,
  TextField,
  Typography,
} from '../../ui'
import {
  createNavigationMenu,
  getNavigationMenu,
  getNavigationMenus,
  type NavigationItem,
  type NavigationMenu,
  publishNavigationMenu,
  updateNavigationMenu,
} from './api'
import {
  addNavigationItem,
  countNavigationItems,
  createNavigationItem,
  moveNavigationItem,
  type NavigationItemField,
  removeNavigationItem,
  updateNavigationItem,
} from './tree'

export function NavigationWorkspace() {
  const [menus, setMenus] = useState<NavigationMenu[]>([])
  const [editing, setEditing] = useState<NavigationMenu | null>(null)
  const [dirty, setDirty] = useState(false)
  const [loading, setLoading] = useState(true)
  const [loadingMenu, setLoadingMenu] = useState(false)
  const [creating, setCreating] = useState(false)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const selectionRequest = useRef<AbortController | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    getNavigationMenus(controller.signal)
      .then((loaded) => {
        setMenus(loaded)
        if (loaded[0]) void selectMenu(loaded[0].id)
      })
      .catch((reason: unknown) => {
        if (!isAbortError(reason)) setError(messageFrom(reason, 'Could not load navigation menus.'))
      })
      .finally(() => setLoading(false))

    return () => {
      controller.abort()
      selectionRequest.current?.abort()
    }
  }, [])

  async function selectMenu(id: string) {
    selectionRequest.current?.abort()
    const controller = new AbortController()
    selectionRequest.current = controller
    setLoadingMenu(true)
    setError(null)
    setSuccess(null)

    try {
      const menu = await getNavigationMenu(id, controller.signal)
      setEditing(structuredClone(menu))
      setDirty(false)
    } catch (reason) {
      if (!isAbortError(reason)) setError(messageFrom(reason, 'Could not load this navigation menu.'))
    } finally {
      if (selectionRequest.current === controller) setLoadingMenu(false)
    }
  }

  async function createMenu(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    if (!name.trim() || !isValidCode(code)) {
      setError('Enter a name and a lowercase code containing letters, numbers and hyphens.')
      return
    }

    setCreating(true)
    try {
      const created = await createNavigationMenu({ name: name.trim(), code, items: [] })
      setMenus((current) => [created, ...current])
      setEditing(structuredClone(created))
      setDirty(false)
      setName('')
      setCode('')
      setSuccess(`Navigation “${created.name}” was created.`)
    } catch (reason) {
      setError(messageFrom(reason, 'Could not create the navigation menu.'))
    } finally {
      setCreating(false)
    }
  }

  function changeMenu(field: 'name' | 'code', value: string) {
    if (!editing) return
    setEditing({ ...editing, [field]: value })
    setDirty(true)
    setSuccess(null)
  }

  function changeItems(items: NavigationItem[]) {
    if (!editing) return
    setEditing({ ...editing, items })
    setDirty(true)
    setSuccess(null)
  }

  async function saveMenu(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!editing) return

    const validationError = validateMenu(editing)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    setError(null)
    setSuccess(null)

    try {
      const saved = await updateNavigationMenu(editing.id, {
        name: editing.name.trim(),
        code: editing.code,
        items: editing.items,
        version: editing.version,
      })
      setEditing(structuredClone(saved))
      setMenus((current) => current.map((menu) => menu.id === saved.id ? saved : menu))
      setDirty(false)
      setSuccess(`Draft version ${saved.version} was saved.`)
    } catch (reason) {
      setError(`${messageFrom(reason, 'Could not save this navigation menu.')} Reload the menu if another editor changed it.`)
    } finally {
      setSaving(false)
    }
  }

  async function publishMenu() {
    if (!editing || dirty) return
    setPublishing(true)
    setError(null)
    setSuccess(null)

    try {
      const publication = await publishNavigationMenu(editing.id, editing.version)
      setSuccess(`Version ${publication.sourceVersion} is published as “${publication.code}”.`)
    } catch (reason) {
      setError(messageFrom(reason, 'Could not publish this navigation menu.'))
    } finally {
      setPublishing(false)
    }
  }

  const itemCount = editing ? countNavigationItems(editing.items) : 0

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">Navigation</Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>
          Build reusable menus visually, save versioned drafts and publish an exact revision.
        </Typography>
      </div>

      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Stack component="form" direction={{ xs: 'column', md: 'row' }} spacing={2} onSubmit={createMenu}>
            <TextField
              label="Menu name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 120 } }}
            />
            <TextField
              label="Code"
              value={code}
              onChange={(event) => setCode(event.target.value.toLowerCase())}
              required
              fullWidth
              helperText="For example: main or footer-links"
              slotProps={{ htmlInput: { maxLength: 120, pattern: '[a-z0-9]+(?:-[a-z0-9]+)*' } }}
            />
            <Button type="submit" variant="contained" disabled={creating} sx={{ minWidth: 150, alignSelf: 'flex-start' }}>
              {creating ? 'Creating…' : 'Create menu'}
            </Button>
          </Stack>
        </CardContent>
      </Card>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: '280px minmax(0, 1fr)' }, gap: 3 }}>
        <Card variant="outlined" sx={{ alignSelf: 'start' }}>
          <CardContent>
            <Typography variant="h6">Menus</Typography>
            {loading ? (
              <Stack sx={{ py: 4, alignItems: 'center' }}><CircularProgress size={26} /></Stack>
            ) : menus.length === 0 ? (
              <Typography color="text.secondary" sx={{ mt: 2 }}>No menus yet.</Typography>
            ) : (
              <Stack spacing={1} sx={{ mt: 2 }}>
                {menus.map((menu) => (
                  <Button
                    key={menu.id}
                    variant={editing?.id === menu.id ? 'contained' : 'outlined'}
                    onClick={() => void selectMenu(menu.id)}
                    sx={{ justifyContent: 'space-between', textAlign: 'left' }}
                  >
                    <span>{menu.name}</span>
                    <Chip label={`v${menu.version}`} size="small" color="default" />
                  </Button>
                ))}
              </Stack>
            )}
          </CardContent>
        </Card>

        {loadingMenu ? (
          <Stack sx={{ minHeight: 260, alignItems: 'center', justifyContent: 'center' }}><CircularProgress size={28} /></Stack>
        ) : !editing ? (
          <Alert severity="info">Create or select a navigation menu to edit its items.</Alert>
        ) : (
          <Card variant="outlined">
            <CardContent component="form" onSubmit={saveMenu}>
              <Stack spacing={3}>
                <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 2 }}>
                  <div>
                    <Typography variant="h5">Edit menu</Typography>
                    <Typography color="text.secondary">Draft version {editing.version} · {itemCount}/100 items</Typography>
                  </div>
                  <Stack direction="row" spacing={1}>
                    <Button type="submit" variant="contained" disabled={!dirty || saving}>
                      {saving ? 'Saving…' : 'Save revision'}
                    </Button>
                    <Button color="success" variant="contained" disabled={dirty || publishing} onClick={() => void publishMenu()}>
                      {publishing ? 'Publishing…' : 'Publish'}
                    </Button>
                  </Stack>
                </Stack>

                {dirty && <Alert severity="warning">Save this revision before publishing it.</Alert>}

                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField
                    label="Menu name"
                    value={editing.name}
                    onChange={(event) => changeMenu('name', event.target.value)}
                    required
                    fullWidth
                    slotProps={{ htmlInput: { maxLength: 120 } }}
                  />
                  <TextField
                    label="Code"
                    value={editing.code}
                    onChange={(event) => changeMenu('code', event.target.value.toLowerCase())}
                    required
                    fullWidth
                    slotProps={{ htmlInput: { maxLength: 120, pattern: '[a-z0-9]+(?:-[a-z0-9]+)*' } }}
                  />
                </Stack>

                <Divider />

                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="h6">Menu items</Typography>
                  <Button
                    variant="outlined"
                    disabled={itemCount >= 100}
                    onClick={() => changeItems(addNavigationItem(editing.items, createNavigationItem(crypto.randomUUID())))}
                  >
                    Add root item
                  </Button>
                </Stack>

                {editing.items.length === 0 ? (
                  <Alert severity="info">This menu has no links. Add the first root item.</Alert>
                ) : (
                  <Stack spacing={2}>
                    {editing.items.map((item, index) => (
                      <NavigationItemEditor
                        key={item.id}
                        item={item}
                        depth={0}
                        index={index}
                        siblingCount={editing.items.length}
                        totalCount={itemCount}
                        onChange={(id, field, value) => changeItems(updateNavigationItem(editing.items, id, field, value))}
                        onAddChild={(parentId) => changeItems(addNavigationItem(editing.items, createNavigationItem(crypto.randomUUID()), parentId))}
                        onRemove={(id) => changeItems(removeNavigationItem(editing.items, id))}
                        onMove={(id, offset) => changeItems(moveNavigationItem(editing.items, id, offset))}
                      />
                    ))}
                  </Stack>
                )}
              </Stack>
            </CardContent>
          </Card>
        )}
      </Box>
    </Stack>
  )
}

interface NavigationItemEditorProps {
  item: NavigationItem
  depth: number
  index: number
  siblingCount: number
  totalCount: number
  onChange: (id: string, field: NavigationItemField, value: string | boolean) => void
  onAddChild: (parentId: string) => void
  onRemove: (id: string) => void
  onMove: (id: string, offset: -1 | 1) => void
}

function NavigationItemEditor({
  item,
  depth,
  index,
  siblingCount,
  totalCount,
  onChange,
  onAddChild,
  onRemove,
  onMove,
}: NavigationItemEditorProps) {
  return (
    <Card variant="outlined" sx={{ ml: depth * 2, bgcolor: depth === 0 ? 'background.paper' : 'grey.50' }}>
      <CardContent>
        <Stack spacing={2}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between' }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
              Level {depth + 1} · {item.label || 'Untitled item'}
            </Typography>
            <Stack direction="row" spacing={0.5} sx={{ flexWrap: 'wrap' }}>
              <Button size="small" disabled={index === 0} onClick={() => onMove(item.id, -1)}>Move up</Button>
              <Button size="small" disabled={index === siblingCount - 1} onClick={() => onMove(item.id, 1)}>Move down</Button>
              <Button size="small" disabled={depth >= 2 || totalCount >= 100} onClick={() => onAddChild(item.id)}>Add child</Button>
              <Button size="small" color="error" onClick={() => onRemove(item.id)}>Remove</Button>
            </Stack>
          </Stack>

          <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
            <TextField
              label="Label"
              value={item.label}
              onChange={(event) => onChange(item.id, 'label', event.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 120 } }}
            />
            <TextField
              label="Link"
              value={item.href}
              onChange={(event) => onChange(item.id, 'href', event.target.value)}
              required
              fullWidth
              helperText="Use /page or an absolute https:// URL"
              slotProps={{ htmlInput: { maxLength: 2048 } }}
            />
            <FormControlLabel
              control={(
                <Switch
                  checked={item.openInNewTab}
                  onChange={(event) => onChange(item.id, 'openInNewTab', event.target.checked)}
                />
              )}
              label="New tab"
              sx={{ minWidth: 120 }}
            />
          </Stack>

          {item.children.length > 0 && (
            <Stack spacing={2}>
              {item.children.map((child, childIndex) => (
                <NavigationItemEditor
                  key={child.id}
                  item={child}
                  depth={depth + 1}
                  index={childIndex}
                  siblingCount={item.children.length}
                  totalCount={totalCount}
                  onChange={onChange}
                  onAddChild={onAddChild}
                  onRemove={onRemove}
                  onMove={onMove}
                />
              ))}
            </Stack>
          )}
        </Stack>
      </CardContent>
    </Card>
  )
}

function validateMenu(menu: NavigationMenu): string | null {
  if (!menu.name.trim()) return 'Menu name is required.'
  if (!isValidCode(menu.code)) return 'Code must use lowercase letters, numbers and single hyphens.'
  if (countNavigationItems(menu.items) > 100) return 'A navigation menu can contain at most 100 items.'

  const invalidItem = findInvalidItem(menu.items)
  if (invalidItem) return invalidItem

  return null
}

function findInvalidItem(items: NavigationItem[], depth = 1): string | null {
  if (depth > 3) return 'Navigation items can be nested at most three levels deep.'

  for (const item of items) {
    if (!item.label.trim()) return 'Every navigation item needs a label.'
    if (!isValidHref(item.href)) return `“${item.label}” needs a root-relative or absolute HTTP(S) link.`
    const childError = findInvalidItem(item.children, depth + 1)
    if (childError) return childError
  }

  return null
}

function isValidCode(value: string): boolean {
  return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value)
}

function isValidHref(value: string): boolean {
  if (value.startsWith('/') && !value.startsWith('//')) return true

  try {
    const url = new URL(value)
    return url.protocol === 'http:' || url.protocol === 'https:'
  } catch {
    return false
  }
}

function isAbortError(reason: unknown): boolean {
  return reason instanceof DOMException && reason.name === 'AbortError'
}

function messageFrom(reason: unknown, fallback: string): string {
  return reason instanceof Error ? reason.message : fallback
}
