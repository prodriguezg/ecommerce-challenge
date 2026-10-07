import { Alert, Box, Button, Container, Divider, TextField, Typography } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useLocation, useParams, useSearchParams } from 'react-router-dom'
import { api, queryString, type Order, type OrderPage, type OrderStatusView, type Principal } from '../lib/api'
import { useAuth } from '../lib/auth'
import { Empty, ErrorNotice, Loading, StatusText, ValidationSummary } from '../components/Feedback'
import { Stack } from '../components/Stack'

const terminal = new Set(['paid', 'payment_failed', 'expired', 'manual_review', 'review_processed'])
export const pollingDelay = (elapsedMs: number) => elapsedMs >= 30_000 ? 5_000 : 2_000

export function OrdersPage() {
  const [orders, setOrders] = useState<OrderPage | null>(null)
  const [error, setError] = useState<unknown>(null)
  useEffect(() => { void api<OrderPage>('/orders?per_page=20').then(setOrders).catch(setError) }, [])
  return <Container maxWidth="lg" sx={{ py: 5 }}><Typography variant="h1">Your orders</Typography>{error ? <ErrorNotice error={error} /> : null}{!orders && !error ? <Loading label="Loading orders" /> : null}{orders?.items.length === 0 ? <Empty title="No orders yet" detail="Completed checkouts appear here." /> : <Stack sx={{ mt: 3 }}>{orders?.items.map((order) => <Box component={Link} to={`/orders/${order.id}`} color="inherit" key={order.id} sx={{ borderBottom: 1, borderColor: 'divider', display: 'grid', gap: 1, gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr 1fr auto' }, p: 2, textDecoration: 'none' }}><Typography sx={{ fontWeight: 750 }}>{order.number}</Typography><StatusText>{order.status.replaceAll('_', ' ')}</StatusText><Typography>{order.currency} {order.total}</Typography><Typography color="primary">View details →</Typography></Box>)}</Stack>}</Container>
}

export function OrderDetailPage() {
  const { orderId } = useParams()
  const [search] = useSearchParams()
  const guestToken = search.get('guest_token')
  const location = useLocation()
  const { principal, refresh } = useAuth()
  const [order, setOrder] = useState<Order | null>((location.state as { initialOrder?: Order } | null)?.initialOrder ?? null)
  const [status, setStatus] = useState<OrderStatusView | null>(null)
  const [timedOut, setTimedOut] = useState(false)
  const [pollSeconds, setPollSeconds] = useState(2)
  const [error, setError] = useState<unknown>(null)
  const [started] = useState(() => Date.now())
  const orderPath = guestToken ? `/guest-orders/${orderId}${queryString({ guest_token: guestToken })}` : `/orders/${orderId}`
  const statusPath = guestToken ? `/guest-orders/${orderId}/status${queryString({ guest_token: guestToken })}` : `/orders/${orderId}/status`

  useEffect(() => { if (!order) void api<Order>(orderPath).then(setOrder).catch(setError) }, [orderPath, order])
  useEffect(() => {
    let timer = 0
    let active = true
    const poll = async () => {
      try {
        const next = await api<OrderStatusView>(statusPath)
        if (!active) return
        setStatus(next)
        const elapsed = Date.now() - started
        if (terminal.has(next.status)) return
        if (elapsed >= 300_000) { setTimedOut(true); return }
        const delay = pollingDelay(elapsed)
        setPollSeconds(delay / 1000)
        timer = window.setTimeout(() => void poll(), delay)
      } catch (requestError) { if (active) setError(requestError) }
    }
    void poll()
    return () => { active = false; window.clearTimeout(timer) }
  }, [started, statusPath])

  if (error) return <Container sx={{ py: 5 }}><ErrorNotice error={error} /></Container>
  if (!order) return <Loading label="Loading order" />
  const currentStatus = status?.status ?? order.status
  return <Container maxWidth="md" sx={{ py: 5 }}><Typography variant="h1">Order {order.number}</Typography><Typography color="text.secondary">Placed {new Date(order.created_at).toLocaleString()}</Typography>{!terminal.has(currentStatus) && !timedOut ? <Alert severity="info" sx={{ my: 3 }}>Checking for updates every {pollSeconds} seconds. This page refreshes automatically.</Alert> : null}{timedOut ? <Alert severity="warning" sx={{ my: 3 }}>Automatic updates paused after five minutes. Revisit this order page later to check its status.</Alert> : null}<Box sx={{ border: 1, borderColor: 'divider', my: 3, p: 3 }}><Stack direction={{ xs: 'column', sm: 'row' }} gap={4}><Box><Typography color="text.secondary">Order status</Typography><StatusText tone={currentStatus === 'paid' ? 'success' : currentStatus.includes('failed') ? 'danger' : 'neutral'}>{currentStatus.replaceAll('_', ' ')}</StatusText></Box><Box><Typography color="text.secondary">Payment status</Typography><StatusText tone={(status?.payment_status ?? order.payment_status) === 'succeeded' ? 'success' : 'neutral'}>{(status?.payment_status ?? order.payment_status).replaceAll('_', ' ')}</StatusText></Box><Box sx={{ ml: { sm: 'auto' } }}><Typography color="text.secondary">Total</Typography><Typography variant="h3">{order.currency} {order.total}</Typography></Box></Stack><Divider sx={{ my: 3 }} /><Typography variant="h3">Items</Typography>{order.lines.map((line) => <Stack direction="row" justifyContent="space-between" key={line.product_id} sx={{ mt: 1 }}><Typography>{line.name} × {line.quantity}</Typography><Typography>{line.currency} {line.line_subtotal}</Typography></Stack>)}</Box>{guestToken && currentStatus === 'paid' && !principal ? <GuestRegistration orderId={order.id} token={guestToken} onComplete={() => void refresh()} /> : null}</Container>
}

function GuestRegistration({ orderId, token, onComplete }: { orderId: string; token: string; onComplete: () => void }) {
  const [error, setError] = useState<unknown>(null)
  const [done, setDone] = useState(false)
  const submit = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    try {
      await api<Principal>(`/guest-orders/${orderId}/register${queryString({ guest_token: token })}`, { method: 'POST', body: JSON.stringify({ password: data.get('password') }) })
      setDone(true); onComplete()
    } catch (requestError) { setError(requestError) }
  }
  if (done) return <Alert severity="success">Account created. This private guest link is now revoked; use your account to view the order.</Alert>
  return <Box sx={{ bgcolor: '#f5f8fc', border: 1, borderColor: 'divider', p: 3 }}><Typography variant="h3">Create an account (optional)</Typography><Typography color="text.secondary" sx={{ my: 1 }}>Save this order to a new customer account using the name and email from checkout.</Typography><Stack component="form" onSubmit={(event) => void submit(event)} gap={2}><ValidationSummary error={error} />{error ? <ErrorNotice error={error} /> : null}<TextField required helperText="At least 10 characters with uppercase, number, and symbol." label="Create a password" name="password" type="password" /><Button type="submit" variant="contained">Create account and claim order</Button></Stack></Box>
}
