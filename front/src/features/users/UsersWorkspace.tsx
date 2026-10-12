import { type FormEvent, useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Card,
  CardContent,
  CircularProgress,
  MenuItem,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '../../ui'
import { createUser, deleteUser, getUsers, updateUserRole, type CmsUser, type UserRole } from './api'

interface UsersWorkspaceProps {
  currentUserId: string
}

export function UsersWorkspace({ currentUserId }: UsersWorkspaceProps) {
  const [users, setUsers] = useState<CmsUser[]>([])
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [role, setRole] = useState<UserRole>('editor')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    getUsers(controller.signal)
      .then(setUsers)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : 'Could not load users.')
      })
      .finally(() => setLoading(false))
    return () => controller.abort()
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setCreating(true)
    setError(null)
    try {
      const user = await createUser({ email, password, role })
      setUsers((current) => [...current, user])
      setEmail('')
      setPassword('')
      setRole('editor')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not create the user.')
    } finally {
      setCreating(false)
    }
  }

  async function changeRole(user: CmsUser, nextRole: UserRole) {
    if (user.role === nextRole) return
    setBusyId(user.id)
    setError(null)
    try {
      const updated = await updateUserRole(user.id, nextRole)
      setUsers((current) => current.map((candidate) => candidate.id === updated.id ? updated : candidate))
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not change the user role.')
    } finally {
      setBusyId(null)
    }
  }

  async function remove(user: CmsUser) {
    if (!window.confirm(`Delete ${user.email}? This account will lose CMS access immediately.`)) return
    setBusyId(user.id)
    setError(null)
    try {
      await deleteUser(user.id)
      setUsers((current) => current.filter((candidate) => candidate.id !== user.id))
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not delete the user.')
    } finally {
      setBusyId(null)
    }
  }

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">Users</Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>Invite editors and owners without exposing credentials or password hashes.</Typography>
      </div>
      {error && <Alert severity="error">{error}</Alert>}
      <Card variant="outlined">
        <CardContent>
          <Typography variant="h6">Create user</Typography>
          <Stack component="form" direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ mt: 2 }} onSubmit={submit}>
            <TextField label="Email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required fullWidth />
            <TextField label="Temporary password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} required fullWidth helperText="At least 12 characters" slotProps={{ htmlInput: { minLength: 12 } }} />
            <TextField select label="Role" value={role} onChange={(event) => setRole(event.target.value as UserRole)} sx={{ minWidth: 150 }}>
              <MenuItem value="editor">Editor</MenuItem>
              <MenuItem value="owner">Owner</MenuItem>
            </TextField>
            <Button type="submit" variant="contained" disabled={creating} sx={{ alignSelf: 'flex-start', minWidth: 130 }}>{creating ? 'Creating…' : 'Create user'}</Button>
          </Stack>
        </CardContent>
      </Card>
      <Card variant="outlined">
        {loading ? (
          <Stack sx={{ alignItems: 'center', py: 6 }}><CircularProgress size={28} /></Stack>
        ) : (
          <TableContainer>
            <Table>
              <TableHead><TableRow><TableCell>Email</TableCell><TableCell>Role</TableCell><TableCell>Created</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead>
              <TableBody>
                {users.map((user) => {
                  const self = user.id === currentUserId
                  return (
                    <TableRow key={user.id} hover>
                      <TableCell sx={{ fontWeight: 700 }}>{user.email}{self ? ' (you)' : ''}</TableCell>
                      <TableCell>
                        <TextField
                          select
                          size="small"
                          value={user.role}
                          disabled={self || busyId === user.id}
                          onChange={(event) => void changeRole(user, event.target.value as UserRole)}
                          aria-label={`Role for ${user.email}`}
                        >
                          <MenuItem value="editor">Editor</MenuItem>
                          <MenuItem value="owner">Owner</MenuItem>
                        </TextField>
                      </TableCell>
                      <TableCell>{new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(user.createdAt))}</TableCell>
                      <TableCell align="right"><Button color="error" size="small" disabled={self || busyId === user.id} onClick={() => void remove(user)}>Delete</Button></TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Card>
    </Stack>
  )
}
