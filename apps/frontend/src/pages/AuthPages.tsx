import { Alert, Box, Button, Container, Link as MuiLink, TextField, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { api, type Principal } from '../lib/api'
import { useAuth } from '../lib/auth'
import { useCart } from '../lib/cart'
import { ErrorNotice, ValidationSummary } from '../components/Feedback'
import { Stack } from '../components/Stack'

function AuthFrame({ title, children }: { title: string; children: React.ReactNode }) {
  return <Container maxWidth="sm" sx={{ py: { xs: 4, md: 9 } }}><Box sx={{ border: 1, borderColor: 'divider', p: { xs: 3, sm: 5 } }}><Typography variant="h1">{title}</Typography><Box sx={{ mt: 3 }}>{children}</Box></Box></Container>
}

export function LoginPage() {
  const { login, principal } = useAuth()
  const { mergeGuestCart } = useCart()
  const navigate = useNavigate()
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)
  if (principal) return <Navigate to={principal.role === 'admin' ? '/admin/orders' : '/'} replace />
  const submit = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setBusy(true); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    try {
      const current = await login(String(data.get('email')), String(data.get('password')))
      if (current.role === 'customer') await mergeGuestCart()
      navigate(current.role === 'admin' ? '/admin/orders' : '/')
    } catch (requestError) { setError(requestError) } finally { setBusy(false) }
  }
  return <AuthFrame title="Sign in"><Stack component="form" onSubmit={(event) => void submit(event)} gap={2}>{error ? <ErrorNotice error={error} /> : null}<TextField required autoComplete="email" label="Email address" name="email" type="email" /><TextField required autoComplete="current-password" label="Password" name="password" type="password" /><Button disabled={busy} type="submit" variant="contained">{busy ? 'Signing in…' : 'Sign in'}</Button><Typography>New customer? <MuiLink component={Link} to="/register">Create an account</MuiLink></Typography></Stack></AuthFrame>
}

const passwordHelp = 'At least 10 characters with an uppercase letter, a number, and a symbol.'

export function RegisterPage() {
  const { refresh } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<unknown>(null)
  const submit = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    try {
      await api<Principal>('/customers/register', { method: 'POST', body: JSON.stringify({ name: data.get('name'), email: data.get('email'), password: data.get('password'), address: addressFrom(data) }) })
      await refresh(); navigate('/')
    } catch (requestError) { setError(requestError) }
  }
  return <AuthFrame title="Create your account"><Stack component="form" onSubmit={(event) => void submit(event)} gap={2}><ValidationSummary error={error} />{error ? <ErrorNotice error={error} /> : null}<TextField required label="Full name" name="name" /><TextField required label="Email address" name="email" type="email" /><TextField required helperText={passwordHelp} label="Password" name="password" type="password" /><AddressFields /><Button type="submit" variant="contained">Create account</Button></Stack></AuthFrame>
}

export function SetupPage() {
  const { setupAvailable, refresh } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<unknown>(null)
  if (!setupAvailable) return <Navigate to="/login" replace />
  const submit = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    try {
      await api<Principal>('/setup/admin', { method: 'POST', body: JSON.stringify({ name: data.get('name'), email: data.get('email'), password: data.get('password') }) })
      await refresh(); navigate('/admin/orders')
    } catch (requestError) { setError(requestError) }
  }
  return <AuthFrame title="Set up Northstar Supply"><Stack component="form" onSubmit={(event) => void submit(event)} gap={2}><Alert severity="info">Create the sole administrator account. This page closes after setup.</Alert><ValidationSummary error={error} />{error ? <ErrorNotice error={error} /> : null}<TextField required label="Administrator name" name="name" /><TextField required label="Administrator email" name="email" type="email" /><TextField required helperText={passwordHelp} label="Password" name="password" type="password" /><Button type="submit" variant="contained">Create administrator</Button></Stack></AuthFrame>
}

export function AddressFields() {
  return <><Typography component="h2" variant="h3" sx={{ mt: 2 }}>Default shipping address</Typography><TextField required label="Recipient name" name="address_name" /><TextField required label="Address line 1" name="line1" /><TextField label="Address line 2 (optional)" name="line2" /><Stack direction={{ xs: 'column', sm: 'row' }} gap={2}><TextField required fullWidth label="City" name="city" /><TextField required fullWidth label="State or region" name="region" /></Stack><Stack direction={{ xs: 'column', sm: 'row' }} gap={2}><TextField required fullWidth label="Postal code" name="postal_code" /><TextField required fullWidth slotProps={{ htmlInput: { maxLength: 2 } }} label="Country code" name="country" placeholder="US" /></Stack><TextField required label="Phone" name="phone" type="tel" /></>
}

export function addressFrom(data: FormData) {
  return { name: String(data.get('address_name')), line1: String(data.get('line1')), line2: String(data.get('line2') ?? ''), city: String(data.get('city')), region: String(data.get('region')), postal_code: String(data.get('postal_code')), country: String(data.get('country')).toUpperCase(), phone: String(data.get('phone')) }
}
