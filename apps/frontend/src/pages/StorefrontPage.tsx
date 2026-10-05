import { Alert, Box, Button, Checkbox, Container, Drawer, FormControl, FormControlLabel, InputLabel, MenuItem, Pagination, Select, Skeleton, TextField, Typography, useMediaQuery } from '@mui/material'
import { Link, useSearchParams } from 'react-router-dom'
import { useDeferredValue, useEffect, useState } from 'react'
import { api, queryString, type Category, type Product, type ProductPage } from '../lib/api'
import { useCart } from '../lib/cart'
import { Empty, ErrorNotice, StatusText } from '../components/Feedback'
import { Stack } from '../components/Stack'

function ProductCard({ product }: { product: Product }) {
  const { add } = useCart()
  return <Box component="article" sx={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
    <Box component={Link} to={`/products/${product.id}`} sx={{ bgcolor: '#f3f6f9', display: 'block', mb: 1.5, overflow: 'hidden', aspectRatio: '4/3' }}>
      <Box component="img" src={product.image_url} alt="" loading="lazy" sx={{ height: '100%', objectFit: 'cover', transition: 'transform .2s', width: '100%', '&:hover': { transform: 'scale(1.02)' } }} />
    </Box>
    <Typography component={Link} to={`/products/${product.id}`} color="inherit" sx={{ fontWeight: 700, textDecoration: 'none' }}>{product.name}</Typography>
    <Typography sx={{ fontSize: 18, fontWeight: 750, mt: .5 }}>{product.currency} {product.price} <Typography component="span" color="text.secondary" variant="body2">excluding tax</Typography></Typography>
    <StatusText tone={product.in_stock ? 'success' : 'neutral'}>{product.in_stock ? 'In stock' : 'Out of stock'}</StatusText>
    <Button disabled={!product.in_stock} onClick={() => void add(product)} variant="contained" sx={{ mt: 1.5, alignSelf: 'flex-start' }}>Add to cart</Button>
  </Box>
}

function Filters({ categories, params, setParam }: { categories: Category[]; params: URLSearchParams; setParam: (name: string, value: string) => void }) {
  return <Stack gap={2.5} sx={{ minWidth: 220 }}>
    <Stack direction="row" justifyContent="space-between" alignItems="center"><Typography variant="h3">Filters</Typography><Button onClick={() => ['category', 'min_price', 'max_price', 'in_stock'].forEach((name) => setParam(name, ''))}>Clear</Button></Stack>
    <FormControl fullWidth><InputLabel id="category-label">Category</InputLabel><Select labelId="category-label" label="Category" value={params.get('category') ?? ''} onChange={(event) => setParam('category', event.target.value)}><MenuItem value="">All categories</MenuItem>{categories.map((category) => <MenuItem value={category.id} key={category.id}>{category.name}</MenuItem>)}</Select></FormControl>
    <Stack direction="row" gap={1}><TextField label="Minimum price" slotProps={{ htmlInput: { inputMode: 'decimal' } }} value={params.get('min_price') ?? ''} onChange={(event) => setParam('min_price', event.target.value)} /><TextField label="Maximum price" slotProps={{ htmlInput: { inputMode: 'decimal' } }} value={params.get('max_price') ?? ''} onChange={(event) => setParam('max_price', event.target.value)} /></Stack>
    <FormControlLabel control={<Checkbox checked={params.get('in_stock') === 'true'} onChange={(event) => setParam('in_stock', event.target.checked ? 'true' : '')} />} label="In-stock items only" />
  </Stack>
}

export function StorefrontPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('q') ?? '')
  const deferredSearch = useDeferredValue(search)
  const [products, setProducts] = useState<ProductPage | null>(null)
  const [categories, setCategories] = useState<Category[]>([])
  const [error, setError] = useState<unknown>(null)
  const [filtersOpen, setFiltersOpen] = useState(false)
  const mobile = useMediaQuery('(max-width:800px)')
  const { notice } = useCart()

  const setParam = (name: string, value: string) => setSearchParams((current) => {
    const next = new URLSearchParams(current)
    if (value) next.set(name, value)
    else next.delete(name)
    if (name !== 'page') next.set('page', '1')
    return next
  })

  useEffect(() => {
    const handle = window.setTimeout(() => setParam('q', deferredSearch), 250)
    return () => window.clearTimeout(handle)
  }, [deferredSearch])

  const load = async () => {
    setError(null)
    try {
      const [page, categoryList] = await Promise.all([
        api<ProductPage>(`/products${queryString({ q: searchParams.get('q') ?? undefined, category: searchParams.get('category') ?? undefined, min_price: searchParams.get('min_price') ?? undefined, max_price: searchParams.get('max_price') ?? undefined, in_stock: searchParams.get('in_stock') ?? undefined, sort: searchParams.get('sort') ?? 'name', direction: searchParams.get('direction') ?? 'asc', page: searchParams.get('page') ?? 1, per_page: searchParams.get('per_page') ?? 20 })}`),
        api<Category[]>('/categories'),
      ])
      setProducts(page); setCategories(categoryList)
    } catch (requestError) { setError(requestError) }
  }

  useEffect(() => { queueMicrotask(() => void load()) }, [searchParams])

  const filters = <Filters categories={categories} params={searchParams} setParam={setParam} />
  return <Container maxWidth="xl" sx={{ py: { xs: 3, md: 5 } }}>
    {notice ? <Alert severity="info" sx={{ mb: 3 }}>{notice}</Alert> : null}
    <Stack direction={{ xs: 'column', md: 'row' }} gap={4}>
      {mobile ? <><Button variant="outlined" onClick={() => setFiltersOpen(true)} sx={{ alignSelf: 'flex-start' }}>Filters</Button><Drawer anchor="bottom" open={filtersOpen} onClose={() => setFiltersOpen(false)} slotProps={{ paper: { sx: { borderRadius: '16px 16px 0 0', p: 3 } } }}>{filters}<Button onClick={() => setFiltersOpen(false)} variant="contained">Apply filters</Button></Drawer></> : <Box component="aside" sx={{ borderRight: 1, borderColor: 'divider', pr: 4 }}>{filters}</Box>}
      <Box sx={{ flex: 1, minWidth: 0 }}>
        <Typography variant="h1">Products</Typography>
        <Stack direction={{ xs: 'column', sm: 'row' }} gap={2} sx={{ my: 3 }}>
          <TextField fullWidth label="Search products, SKUs, or categories" type="search" value={search} onChange={(event) => setSearch(event.target.value)} />
          <FormControl sx={{ minWidth: 180 }}><InputLabel id="sort-label">Sort by</InputLabel><Select labelId="sort-label" label="Sort by" value={`${searchParams.get('sort') ?? 'name'}:${searchParams.get('direction') ?? 'asc'}`} onChange={(event) => { const [sort, direction] = event.target.value.split(':'); setParam('sort', sort); setParam('direction', direction) }}><MenuItem value="name:asc">Name A–Z</MenuItem><MenuItem value="name:desc">Name Z–A</MenuItem><MenuItem value="price:asc">Price low to high</MenuItem><MenuItem value="price:desc">Price high to low</MenuItem></Select></FormControl>
          <FormControl sx={{ minWidth: 130 }}><InputLabel id="size-label">Show</InputLabel><Select labelId="size-label" label="Show" value={searchParams.get('per_page') ?? '20'} onChange={(event) => setParam('per_page', event.target.value)}>{[10, 20, 50].map((value) => <MenuItem value={String(value)} key={value}>{value}</MenuItem>)}</Select></FormControl>
        </Stack>
        {error ? <ErrorNotice error={error} retry={() => void load()} /> : null}
        {!products && !error ? <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(210px,1fr))', gap: 3 }}>{Array.from({ length: 8 }, (_, index) => <Skeleton key={index} height={300} variant="rounded" />)}</Box> : null}
        {products?.items.length === 0 ? <Empty title="No products found" detail="Try clearing a filter or searching with a different term." /> : null}
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(210px,1fr))', gap: { xs: 3, md: 4 } }}>{products?.items.map((product) => <ProductCard key={product.id} product={product} />)}</Box>
        {products && products.pagination.total_pages > 1 ? <Pagination aria-label="Product pages" page={products.pagination.page} count={products.pagination.total_pages} onChange={(_, page) => setParam('page', String(page))} sx={{ mt: 5 }} /> : null}
      </Box>
    </Stack>
  </Container>
}
