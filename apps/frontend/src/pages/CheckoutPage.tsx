import { Alert, Box, Button, Container, Divider, FormControl, FormControlLabel, FormHelperText, Radio, RadioGroup, TextField, Typography } from '@mui/material'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { addressFrom } from './AuthPages'
import { api, type Cart, type CartLineInput, type CheckoutInput, type CheckoutResult, type ShippingMethod } from '../lib/api'
import { useAuth } from '../lib/auth'
import { useCart } from '../lib/cart'
import { ErrorNotice, ValidationSummary } from '../components/Feedback'
import { Stack } from '../components/Stack'

const TEST_CARDS = [
  ['4000000000010001', 'Success after 2–8 seconds'],
  ['4000000000000002', 'Decline after 2–8 seconds'],
  ['4000000000090003', 'Provider error after 2–8 seconds'],
  ['4000000000080004', 'Late success after the configured delay'],
] as const

export function CheckoutPage() {
  const { principal } = useAuth()
  const defaultAddress = principal?.role === 'customer' ? principal.default_address : undefined
  const { lines, serverCart, clearAfterCheckout } = useCart()
  const navigate = useNavigate()
  const [shippingMethods, setShippingMethods] = useState<ShippingMethod[]>([])
  const [shippingMethod, setShippingMethod] = useState('')
  const [quote, setQuote] = useState<Cart | null>(serverCart)
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)
  const cartLines = useMemo<CartLineInput[]>(() => principal?.role === 'customer'
    ? serverCart?.lines.map((line) => ({ product_id: line.product_id, quantity: line.quantity })) ?? []
    : lines.map((line) => ({ product_id: line.product.id, quantity: line.quantity })), [lines, principal, serverCart])

  useEffect(() => { void api<ShippingMethod[]>('/shipping-methods').then((methods) => { setShippingMethods(methods); setShippingMethod(methods[0]?.id ?? '') }).catch(setError) }, [])
  useEffect(() => {
    if (!cartLines.length || !shippingMethod) return
    void api<Cart>('/cart/quote', { method: 'POST', body: JSON.stringify({ lines: cartLines, shipping_method_id: shippingMethod }) }).then(setQuote).catch(setError)
  }, [shippingMethod, cartLines])

  const submit = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setBusy(true); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    const input: CheckoutInput = { email: String(data.get('email')), shipping_address: addressFrom(data), shipping_method_id: shippingMethod, lines: cartLines, payment_test_number: String(data.get('payment_test_number')) as CheckoutInput['payment_test_number'] }
    try {
      const result = await api<CheckoutResult>('/checkouts', { method: 'POST', headers: { 'Idempotency-Key': `web-${crypto.randomUUID()}` }, body: JSON.stringify(input) })
      clearAfterCheckout()
      const guestUrl = result.guest_order_url ? new URL(result.guest_order_url, window.location.origin) : null
      const token = guestUrl?.searchParams.get('guest_token')
      navigate(`/orders/${result.order.id}${token ? `?guest_token=${encodeURIComponent(token)}` : ''}`, { state: { initialOrder: result.order } })
    } catch (requestError) { setError(requestError) } finally { setBusy(false) }
  }

  if (!cartLines.length) return <Container sx={{ py: 6 }}><Alert severity="info">Your cart is empty. Add a product before checking out.</Alert></Container>
  return <Container maxWidth="lg" sx={{ py: 5 }}><Typography variant="h1">Checkout</Typography><Typography color="text.secondary" sx={{ mt: 1 }}>Complete your shipping details and simulated payment.</Typography><Stack component="form" onSubmit={(event) => void submit(event)} direction={{ xs: 'column', md: 'row' }} gap={3} sx={{ mt: 4 }}>
    <Stack gap={2} sx={{ flex: 1 }}><Typography variant="h3">1. Contact and shipping information</Typography><ValidationSummary error={error} />{error ? <ErrorNotice error={error} /> : null}<TextField required defaultValue={principal?.email ?? ''} label="Email address" name="email" type="email" /><TextField required defaultValue={defaultAddress?.name ?? ''} label="Recipient name" name="address_name" /><TextField required defaultValue={defaultAddress?.line1 ?? ''} label="Address line 1" name="line1" /><TextField defaultValue={defaultAddress?.line2 ?? ''} label="Address line 2 (optional)" name="line2" /><Stack direction={{ xs: 'column', sm: 'row' }} gap={2}><TextField required fullWidth defaultValue={defaultAddress?.city ?? ''} label="City" name="city" /><TextField required fullWidth defaultValue={defaultAddress?.region ?? ''} label="State or region" name="region" /></Stack><Stack direction={{ xs: 'column', sm: 'row' }} gap={2}><TextField required fullWidth defaultValue={defaultAddress?.postal_code ?? ''} label="Postal code" name="postal_code" /><TextField required fullWidth defaultValue={defaultAddress?.country ?? ''} slotProps={{ htmlInput: { maxLength: 2 } }} label="Country code" name="country" placeholder="US" /></Stack><TextField required defaultValue={defaultAddress?.phone ?? ''} label="Phone" name="phone" type="tel" /><Typography variant="h3" sx={{ mt: 2 }}>2. Shipping method</Typography><FormControl required><RadioGroup value={shippingMethod} onChange={(event) => setShippingMethod(event.target.value)}>{shippingMethods.map((method) => <FormControlLabel key={method.id} value={method.id} control={<Radio />} label={`${method.name} — ${method.currency} ${method.amount}`} />)}</RadioGroup><FormHelperText>Select one shipping method</FormHelperText></FormControl></Stack>
    <Stack gap={2} sx={{ flex: 1 }}><Box sx={{ bgcolor: '#f5f8fc', border: 1, borderColor: 'divider', p: 3 }}><Typography variant="h3">3. Order summary</Typography>{quote?.lines.map((line) => <Stack direction="row" justifyContent="space-between" key={line.product_id} sx={{ mt: 1.5 }}><Typography>{line.name} × {line.quantity}</Typography><Typography>{line.currency} {line.line_subtotal}</Typography></Stack>)}<Divider sx={{ my: 2 }} />{[['Subtotal', quote?.subtotal], ['Shipping', quote?.shipping], ['Product tax', quote?.product_tax], ['Shipping tax', quote?.shipping_tax]].map(([label, value]) => <Stack direction="row" justifyContent="space-between" key={label}><Typography color="text.secondary">{label}</Typography><Typography>{quote?.currency ?? 'USD'} {value ?? '—'}</Typography></Stack>)}<Stack direction="row" justifyContent="space-between" sx={{ mt: 1 }}><Typography variant="h3">Total</Typography><Typography variant="h3">{quote?.currency ?? 'USD'} {quote?.total ?? '—'}</Typography></Stack>{quote?.requires_confirmation ? <Alert severity="warning" sx={{ mt: 2 }}>Catalog, tax, stock, or shipping details changed. Review the updated itemized total before placing your order.</Alert> : null}</Box>
    <Box sx={{ border: 1, borderColor: 'divider', p: 3 }}><Typography variant="h3">4. Payment (simulated)</Typography><Alert severity="error" sx={{ my: 2 }}><strong>Payment is simulated. Never enter real card information.</strong></Alert><TextField required fullWidth label="Test card number" name="payment_test_number" slotProps={{ htmlInput: { inputMode: 'numeric', pattern: TEST_CARDS.map(([card]) => card).join('|') } }} /><Stack direction="row" gap={2} sx={{ my: 2 }}><TextField fullWidth disabled label="Expiry" value="MM / YY" helperText="Simulated field; cannot be edited." /><TextField fullWidth disabled label="Security code" value="CVC" helperText="Simulated field; cannot be edited." /></Stack><Box component="ul" sx={{ bgcolor: '#eef3ff', m: 0, p: 2, pl: 4 }}>{TEST_CARDS.map(([card, result]) => <li key={card}><code>{card}</code> — {result}</li>)}</Box><Button disabled={busy || !shippingMethod} fullWidth size="large" sx={{ mt: 2 }} type="submit" variant="contained">{busy ? 'Placing order…' : 'Place order (simulated)'}</Button></Box></Stack>
  </Stack></Container>
}
