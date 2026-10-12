import { Alert, Card, CardContent, Chip, Stack, Typography } from '@mui/material'

export type ServiceState = 'loading' | 'online' | 'offline'

interface DashboardCardProps {
  serviceState: ServiceState
}

const labels: Record<ServiceState, string> = {
  loading: 'Checking',
  online: 'Online',
  offline: 'Offline',
}

export function DashboardCard({ serviceState }: DashboardCardProps) {
  return (
    <Stack spacing={3}>
      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
        <div>
          <Typography component="h1" variant="h1">
            Dashboard
          </Typography>
          <Typography color="text.secondary" sx={{ mt: 0.75 }}>
            The administration foundation is ready for the first content vertical slice.
          </Typography>
        </div>
        <Chip
          label={labels[serviceState]}
          color={serviceState === 'online' ? 'success' : serviceState === 'offline' ? 'error' : 'default'}
          variant={serviceState === 'loading' ? 'outlined' : 'filled'}
        />
      </Stack>

      {serviceState === 'offline' && (
        <Alert severity="warning">The CMS backend is unavailable. Start the Docker app service and retry.</Alert>
      )}

      <Card variant="outlined">
        <CardContent>
          <Typography variant="overline" color="text.secondary">
            Next module
          </Typography>
          <Typography variant="h6" sx={{ mt: 0.5 }}>
            Content schema
          </Typography>
          <Typography color="text.secondary" sx={{ mt: 1 }}>
            Define the first Page content type without coupling domain rules to Symfony or MUI.
          </Typography>
        </CardContent>
      </Card>
    </Stack>
  )
}
