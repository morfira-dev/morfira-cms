import {
  AppBar,
  Box,
  Button,
  Container,
  List,
  ListItemButton,
  ListItemText,
  Toolbar,
  Typography,
} from '@mui/material'
import type { PropsWithChildren } from 'react'

interface NavigationItem {
  id: string
  label: string
  path: string
  active?: boolean
}

interface AdminShellProps extends PropsWithChildren {
  navigation: NavigationItem[]
  userEmail: string
  onNavigate: (path: string) => void
  onLogout: () => void
}

const sidebarWidth = 240

export function AdminShell({ navigation, userEmail, onNavigate, onLogout, children }: AdminShellProps) {
  return (
    <Box
      sx={{
        display: 'grid',
        gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: `${sidebarWidth}px minmax(0, 1fr)` },
        minHeight: '100vh',
      }}
    >
      <Box
        component="aside"
        sx={{
          bgcolor: '#111827',
          color: '#fff',
          p: { xs: 1, md: 2 },
          position: { md: 'sticky' },
          top: 0,
          height: { md: '100vh' },
          overflowY: { md: 'auto' },
        }}
      >
        <Typography variant="h6" sx={{ display: { xs: 'none', md: 'block' }, px: 1, py: 1.5, fontWeight: 800 }}>
          CMS
        </Typography>

        <List
          component="nav"
          aria-label="Main navigation"
          sx={{ display: { xs: 'flex', md: 'block' }, gap: { xs: 0.5, md: 0 }, overflowX: 'auto' }}
        >
          {navigation.map((item) => (
            <ListItemButton
              component="a"
              href={item.path}
              key={item.id}
              selected={item.active}
              onClick={(event) => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
                event.preventDefault()
                onNavigate(item.path)
              }}
              sx={{
                borderRadius: 1.5,
                mb: { md: 0.5 },
                minWidth: 'max-content',
                '&.Mui-selected': { bgcolor: 'rgba(255, 255, 255, 0.14)' },
                '&.Mui-selected:hover': { bgcolor: 'rgba(255, 255, 255, 0.2)' },
              }}
            >
              <ListItemText primary={item.label} />
            </ListItemButton>
          ))}
        </List>
      </Box>

      <Box sx={{ minWidth: 0 }}>
        <AppBar position="static" color="inherit" elevation={0} sx={{ borderBottom: 1, borderColor: 'divider' }}>
          <Toolbar sx={{ justifyContent: 'space-between', gap: 2 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
              Admin Studio
            </Typography>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
              <Typography variant="body2" color="text.secondary">{userEmail}</Typography>
              <Button size="small" onClick={onLogout}>Sign out</Button>
            </Box>
          </Toolbar>
        </AppBar>

        <Container component="main" maxWidth="xl" sx={{ py: 4 }}>
          {children}
        </Container>
      </Box>
    </Box>
  )
}
