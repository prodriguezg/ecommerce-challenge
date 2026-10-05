import { Alert, Box, Button, CircularProgress, Typography } from '@mui/material'
import { ApiProblem } from '../lib/api'
import { Stack } from './Stack'

export function Loading({ label = 'Loading' }: { label?: string }) {
  return <Stack alignItems="center" role="status" sx={{ py: 10 }}><CircularProgress /><Typography sx={{ mt: 2 }}>{label}…</Typography></Stack>
}

export function Empty({ title, detail }: { title: string; detail: string }) {
  return <Box sx={{ borderBlock: 1, borderColor: 'divider', py: 8, textAlign: 'center' }}><Typography variant="h3">{title}</Typography><Typography color="text.secondary" sx={{ mt: 1 }}>{detail}</Typography></Box>
}

export function ErrorNotice({ error, retry }: { error: unknown; retry?: () => void }) {
  const message = error instanceof Error ? error.message : 'Something went wrong.'
  return <Alert severity="error" action={retry ? <Button onClick={retry}>Try again</Button> : undefined}><strong>We couldn’t complete that request.</strong> {message}</Alert>
}

export function ValidationSummary({ error }: { error: unknown }) {
  if (!(error instanceof ApiProblem) || !Object.keys(error.errors).length) return null
  return <Alert severity="error" tabIndex={-1}><Typography sx={{ fontWeight: 700 }}>Please correct the following fields:</Typography><ul>{Object.entries(error.errors).flatMap(([field, messages]) => messages.map((message) => <li key={`${field}-${message}`}>{field.replaceAll('_', ' ')}: {message}</li>))}</ul></Alert>
}

export function StatusText({ children, tone = 'neutral' }: { children: React.ReactNode; tone?: 'neutral' | 'success' | 'danger' }) {
  const color = tone === 'success' ? 'success.main' : tone === 'danger' ? 'error.main' : 'text.secondary'
  return <Stack component="span" direction="row" alignItems="center" gap={1} sx={{ color }}><Box component="span" aria-hidden sx={{ bgcolor: 'currentColor', borderRadius: '50%', height: 8, width: 8 }} />{children}</Stack>
}
