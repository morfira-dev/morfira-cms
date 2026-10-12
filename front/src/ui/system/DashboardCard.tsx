import { Alert, Card, CardContent, Chip, Stack, Typography } from '@mui/material'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

export type ServiceState = 'loading' | 'online' | 'offline'

interface DashboardCardProps {
  serviceState: ServiceState
}

export function DashboardCard({ serviceState }: DashboardCardProps) {
  const { t } = useAdminLocale()

  return (
    <Stack spacing={3}>
      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
        <div>
          <Typography component="h1" variant="h1">
            {t('Dashboard')}
          </Typography>
          <Typography color="text.secondary" sx={{ mt: 0.75 }}>
            {t('The administration foundation is ready for the first content vertical slice.')}
          </Typography>
        </div>
        <Chip
          label={t(serviceState === 'loading' ? 'Checking' : serviceState === 'online' ? 'Online' : 'Offline')}
          color={serviceState === 'online' ? 'success' : serviceState === 'offline' ? 'error' : 'default'}
          variant={serviceState === 'loading' ? 'outlined' : 'filled'}
        />
      </Stack>

      {serviceState === 'offline' && (
        <Alert severity="warning">{t('The CMS backend is unavailable. Start the Docker app service and retry.')}</Alert>
      )}

      <Card variant="outlined">
        <CardContent>
          <Typography variant="overline" color="text.secondary">
            {t('Next module')}
          </Typography>
          <Typography variant="h6" sx={{ mt: 0.5 }}>
            {t('Content schema')}
          </Typography>
          <Typography color="text.secondary" sx={{ mt: 1 }}>
            {t('Define the first Page content type without coupling domain rules to Symfony or MUI.')}
          </Typography>
        </CardContent>
      </Card>
    </Stack>
  )
}
