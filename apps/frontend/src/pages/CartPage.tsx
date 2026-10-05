import { Alert, Box, Button, Container, Divider, TextField, Typography } from '@mui/material'
import { Link } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import { useCart } from '../lib/cart'
import { Empty } from '../components/Feedback'
import { Stack } from '../components/Stack'

export function CartPage() {
  const { principal } = useAuth()
  const { lines, serverCart, setQuantity, remove } = useCart()
  const visible = principal?.role === 'customer'
    ? serverCart?.lines.map((line) => ({ id: line.product_id, name: line.name, quantity: line.quantity, price: line.unit_price, total: line.line_subtotal, product: null })) ?? []
    : lines.map((line) => ({ id: line.product.id, name: line.product.name, quantity: line.quantity, price: line.product.price, total: (Number(line.product.price) * line.quantity).toFixed(2), product: line.product }))
  const total = principal?.role === 'customer' ? serverCart?.subtotal ?? '0.00' : visible.reduce((sum, line) => sum + Number(line.total), 0).toFixed(2)
  return <Container maxWidth="md" sx={{ py: 5 }}><Typography variant="h1">Your cart</Typography>{visible.length === 0 ? <Empty title="Your cart is empty" detail="Browse the catalog to add a product." /> : <Stack gap={2} sx={{ mt: 4 }}>{visible.map((line) => <Box key={line.id}><Stack direction={{ xs: 'column', sm: 'row' }} alignItems={{ sm: 'center' }} gap={2}><Box sx={{ flex: 1 }}><Typography sx={{ fontWeight: 700 }}>{line.name}</Typography><Typography color="text.secondary">USD {line.price} each, excluding tax</Typography></Box><TextField label={`Quantity for ${line.name}`} type="number" value={line.quantity} slotProps={{ htmlInput: { min: 1 } }} onChange={(event) => line.product && void setQuantity(line.product, Number(event.target.value))} disabled={!line.product} sx={{ width: 130 }} /><Typography sx={{ fontWeight: 700 }}>USD {line.total}</Typography>{line.product ? <Button color="error" onClick={() => void remove(line.product!)}>Remove</Button> : null}</Stack><Divider sx={{ mt: 2 }} /></Box>)}<Stack direction="row" justifyContent="space-between"><Typography variant="h3">Subtotal</Typography><Typography variant="h3">USD {total}</Typography></Stack><Typography color="text.secondary">Shipping and tax are calculated during checkout.</Typography>{principal?.role === 'admin' ? <Alert severity="warning">Administrators cannot shop or place orders.</Alert> : <Button component={Link} to="/checkout" variant="contained" size="large" sx={{ alignSelf: 'flex-end' }}>Review checkout</Button>}</Stack>}</Container>
}
