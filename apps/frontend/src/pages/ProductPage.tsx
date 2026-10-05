import { Alert, Box, Button, Container, Typography } from '@mui/material'
import { Link, useParams } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { api, type Product } from '../lib/api'
import { useCart } from '../lib/cart'
import { ErrorNotice, Loading, StatusText } from '../components/Feedback'
import { Stack } from '../components/Stack'

export function ProductPage() {
  const { productId } = useParams()
  const [product, setProduct] = useState<Product | null>(null)
  const [error, setError] = useState<unknown>(null)
  const { add } = useCart()
  useEffect(() => { void api<Product>(`/products/${productId}`).then(setProduct).catch(setError) }, [productId])
  if (error) return <Container sx={{ py: 5 }}><ErrorNotice error={error} /></Container>
  if (!product) return <Loading label="Loading product" />
  return <Container maxWidth="lg" sx={{ py: 5 }}><Button component={Link} to="/">← Back to products</Button><Stack direction={{ xs: 'column', md: 'row' }} gap={{ xs: 3, md: 7 }} sx={{ mt: 2 }}><Box component="img" src={product.image_url} alt="" sx={{ bgcolor: '#f3f6f9', objectFit: 'cover', width: { xs: '100%', md: '52%' }, aspectRatio: '4/3' }} /><Stack alignItems="flex-start" gap={2} sx={{ py: 2 }}><Typography variant="h1">{product.name}</Typography><Typography color="text.secondary">SKU {product.sku}</Typography><Typography sx={{ fontSize: 28, fontWeight: 800 }}>{product.currency} {product.price}</Typography><Alert severity="info" icon={false}>Price excluding tax. Taxes are calculated at checkout.</Alert><StatusText tone={product.in_stock ? 'success' : 'neutral'}>{product.in_stock ? 'In stock and ready to order' : 'Currently out of stock'}</StatusText><Typography sx={{ whiteSpace: 'pre-wrap' }}>{product.description}</Typography><Button disabled={!product.in_stock} onClick={() => void add(product)} size="large" variant="contained">Add to cart</Button></Stack></Stack></Container>
}
