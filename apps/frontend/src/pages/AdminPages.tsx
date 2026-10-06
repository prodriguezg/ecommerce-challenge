import { Alert, Box, Button, Dialog, DialogActions, DialogContent, DialogTitle, Divider, Drawer, FormControl, FormControlLabel, InputLabel, MenuItem, Radio, RadioGroup, Select, Switch, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, TextField, Typography } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, queryString, type AuditLogPage, type Category, type Inventory, type Order, type OrderPage, type Product, type ProductImport, type ProductPage, type Setting, type ShippingMethod, type Tax } from '../lib/api'
import { Empty, ErrorNotice, Loading, StatusText, ValidationSummary } from '../components/Feedback'
import { Stack } from '../components/Stack'

function PageTitle({ children, action }: { children: React.ReactNode; action?: React.ReactNode }) {
  return <Stack direction="row" alignItems="center" justifyContent="space-between" sx={{ mb: 3 }}><Typography variant="h1">{children}</Typography>{action}</Stack>
}

function ProductFields({ product, categories, taxes, includeInitialStock = false }: { product?: Product; categories: Category[]; taxes: Tax[]; includeInitialStock?: boolean }) {
  return <Stack gap={2} sx={{ pt: 1 }}>
    <TextField required defaultValue={product?.name ?? ''} label="Name" name="name" />
    <TextField required defaultValue={product?.sku ?? ''} label="SKU" name="sku" />
    <TextField defaultValue={product?.description ?? ''} multiline minRows={3} label="Description" name="description" />
    <TextField required defaultValue={product?.price ?? ''} slotProps={{ htmlInput: { min: 0, step: '.01' } }} label="Price excluding tax" name="price" type="number" />
    <TextField required defaultValue={product?.weight_kg ?? '0'} slotProps={{ htmlInput: { min: 0, step: '.0001' } }} label="Weight (kg)" name="weight_kg" type="number" />
    {includeInitialStock ? <TextField slotProps={{ htmlInput: { min: 0 } }} label="Initial stock on hand" name="initial_on_hand" type="number" defaultValue="0" /> : null}
    <TextField select label="Category (optional)" name="category_id" defaultValue={product?.category?.id ?? ''}>
      <MenuItem value="">No category</MenuItem>
      {categories.filter((category) => category.active).map((category) => <MenuItem key={category.id} value={category.id}>{category.name}</MenuItem>)}
    </TextField>
    <TextField select label="Tax (optional)" name="tax_id" defaultValue={product?.tax_id ?? ''}>
      <MenuItem value="">No tax</MenuItem>
      {taxes.filter((tax) => tax.active).map((tax) => <MenuItem key={tax.id} value={tax.id}>{tax.name} ({tax.rate}%)</MenuItem>)}
    </TextField>
  </Stack>
}

function productPayload(data: FormData) {
  return {
    sku: data.get('sku'),
    name: data.get('name'),
    description: data.get('description'),
    price: data.get('price'),
    weight_kg: data.get('weight_kg'),
    currency: 'USD',
    category_id: data.get('category_id') || null,
    tax_id: data.get('tax_id') || null,
    active: true,
  }
}

export function AdminProductsPage() {
  const [page, setPage] = useState<ProductPage | null>(null)
  const [categories, setCategories] = useState<Category[]>([])
  const [taxes, setTaxes] = useState<Tax[]>([])
  const [error, setError] = useState<unknown>(null)
  const [createOpen, setCreateOpen] = useState(false)
  const [editing, setEditing] = useState<Product | null>(null)
  const [deleting, setDeleting] = useState<Product | null>(null)
  const [busy, setBusy] = useState(false)
  const load = () => Promise.all([
    api<ProductPage>('/admin/products?per_page=50'),
    api<Category[]>('/admin/categories'),
    api<Tax[]>('/admin/taxes'),
  ]).then(([products, availableCategories, availableTaxes]) => {
    setPage(products)
    setCategories(availableCategories)
    setTaxes(availableTaxes)
    setError(null)
  }).catch(setError)
  useEffect(() => { void load() }, [])

  const create = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setBusy(true); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    try {
      await api<Product>('/admin/products', { method: 'POST', body: JSON.stringify({ ...productPayload(data), initial_on_hand: Number(data.get('initial_on_hand')) }) })
      setCreateOpen(false)
      await load()
    } catch (requestError) { setError(requestError) } finally { setBusy(false) }
  }

  const update = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault()
    if (!editing) return
    setBusy(true); setError(null)
    const data = new FormData(event.currentTarget as HTMLFormElement)
    try {
      await api<Product>(`/admin/products/${editing.id}`, { method: 'PUT', headers: { 'If-Match-Version': String(editing.version) }, body: JSON.stringify(productPayload(data)) })
      setEditing(null)
      await load()
    } catch (requestError) { setError(requestError) } finally { setBusy(false) }
  }

  const remove = async () => {
    if (!deleting) return
    setBusy(true); setError(null)
    try {
      await api<void>(`/admin/products/${deleting.id}`, { method: 'DELETE', headers: { 'If-Match-Version': String(deleting.version) } })
      setDeleting(null)
      await load()
    } catch (requestError) { setError(requestError) } finally { setBusy(false) }
  }

  return <>
    <PageTitle action={<Button onClick={() => setCreateOpen(true)} variant="contained">Add product</Button>}>Products</PageTitle>
    {error ? <ErrorNotice error={error} /> : null}
    {!page && !error ? <Loading label="Loading products" /> : <TableContainer><Table><TableHead><TableRow><TableCell>Product</TableCell><TableCell>SKU</TableCell><TableCell>Price</TableCell><TableCell>Stock</TableCell><TableCell>Updated</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead><TableBody>{page?.items.map((product) => <TableRow key={product.id}><TableCell><Stack direction="row" gap={2} alignItems="center"><Box component="img" src={product.image_url} alt="" sx={{ height: 48, objectFit: 'cover', width: 64 }} /><Typography sx={{ fontWeight: 700 }}>{product.name}</Typography></Stack></TableCell><TableCell>{product.sku}</TableCell><TableCell>{product.currency} {product.price}</TableCell><TableCell><StatusText tone={product.in_stock ? 'success' : 'neutral'}>{product.in_stock ? 'Available' : 'Out of stock'}</StatusText></TableCell><TableCell>{new Date(product.updated_at).toLocaleDateString()}</TableCell><TableCell align="right"><Stack direction="row" gap={1} justifyContent="flex-end"><Button component={Link} to={`/admin/inventory?product=${product.id}`} size="small">Inventory</Button><Button onClick={() => setEditing(product)} size="small">Edit</Button><Button color="error" onClick={() => setDeleting(product)} size="small">Delete</Button></Stack></TableCell></TableRow>)}</TableBody></Table></TableContainer>}

    <Dialog open={createOpen} onClose={() => setCreateOpen(false)} fullWidth><Stack component="form" onSubmit={(event) => void create(event)}><DialogTitle>Add product</DialogTitle><DialogContent><ValidationSummary error={error} /><ProductFields categories={categories} taxes={taxes} includeInitialStock /><Button component="label" sx={{ mt: 2 }} variant="outlined">Choose product image<input accept="image/jpeg,image/png,image/webp" hidden type="file" /></Button><Typography color="text.secondary" variant="caption" sx={{ display: 'block', mt: 1 }}>JPEG, PNG, or WebP up to 5 MB. Upload becomes available after product creation.</Typography></DialogContent><DialogActions><Button onClick={() => setCreateOpen(false)}>Cancel</Button><Button disabled={busy} type="submit" variant="contained">{busy ? 'Creating…' : 'Create product'}</Button></DialogActions></Stack></Dialog>

    {editing ? <Dialog open onClose={() => setEditing(null)} fullWidth><Stack component="form" onSubmit={(event) => void update(event)}><DialogTitle>Edit product</DialogTitle><DialogContent><ValidationSummary error={error} /><ProductFields product={editing} categories={categories} taxes={taxes} /></DialogContent><DialogActions><Button onClick={() => setEditing(null)}>Cancel</Button><Button disabled={busy} type="submit" variant="contained">{busy ? 'Saving…' : 'Save changes'}</Button></DialogActions></Stack></Dialog> : null}

    <Dialog open={Boolean(deleting)} onClose={() => setDeleting(null)} fullWidth maxWidth="xs"><DialogTitle>Delete product?</DialogTitle><DialogContent><Typography>This removes <strong>{deleting?.name}</strong> from the catalog. Historical order and reservation records remain preserved.</Typography></DialogContent><DialogActions><Button onClick={() => setDeleting(null)}>Cancel</Button><Button color="error" disabled={busy} onClick={() => void remove()} variant="contained">{busy ? 'Deleting…' : 'Delete product'}</Button></DialogActions></Dialog>
  </>
}

type Resource = Category | Tax | ShippingMethod
const resources = {
  categories: { title: 'Categories', singular: 'category', endpoint: '/admin/categories', fields: ['name', 'slug'] },
  taxes: { title: 'Taxes', singular: 'tax', endpoint: '/admin/taxes', fields: ['name', 'rate'] },
  shipping: { title: 'Shipping methods', singular: 'shipping method', endpoint: '/admin/shipping-methods', fields: ['name', 'amount', 'tax_id'] },
} as const

export function AdminResourcePage({ kind }: { kind: keyof typeof resources }) {
  const config = resources[kind]
  const [items, setItems] = useState<Resource[] | null>(null)
  const [taxes, setTaxes] = useState<Tax[]>([])
  const [error, setError] = useState<unknown>(null)
  const load = () => Promise.all([
    api<Resource[]>(config.endpoint),
    kind === 'shipping' ? api<Tax[]>('/admin/taxes') : Promise.resolve([]),
  ]).then(([resources, availableTaxes]) => {
    setItems(resources)
    setTaxes(availableTaxes)
    setError(null)
  }).catch(setError)
  useEffect(() => { void load() }, [config.endpoint])
  const create = async (event: FormEvent<HTMLElement>) => {
    event.preventDefault(); setError(null); const form = event.currentTarget as HTMLFormElement; const data = new FormData(form)
    const body = kind === 'categories' ? { name: data.get('name'), slug: data.get('slug'), active: true }
      : kind === 'taxes' ? { name: data.get('name'), rate: data.get('rate'), active: true }
      : { name: data.get('name'), amount: data.get('amount'), currency: 'USD', tax_id: data.get('tax_id') || null, active: true }
    try { await api<Resource>(config.endpoint, { method: 'POST', body: JSON.stringify(body) }); form.reset(); void load() } catch (requestError) { setError(requestError) }
  }
  return <><PageTitle>{config.title}</PageTitle><Stack direction={{ xs: 'column', lg: 'row' }} gap={4}><Stack component="form" onSubmit={(event) => void create(event)} gap={2} sx={{ border: 1, borderColor: 'divider', p: 3, width: { lg: 350 } }}><Typography variant="h3">Add {config.singular}</Typography><ValidationSummary error={error} />{error ? <ErrorNotice error={error} /> : null}{config.fields.map((field) => field === 'tax_id' ? <TextField select key={field} defaultValue="" label="Tax (optional)" name={field}><MenuItem value="">No tax</MenuItem>{taxes.filter((tax) => tax.active).map((tax) => <MenuItem key={tax.id} value={tax.id}>{tax.name} ({tax.rate}%)</MenuItem>)}</TextField> : <TextField required key={field} label={field.replaceAll('_', ' ')} name={field} type={field === 'rate' || field === 'amount' ? 'number' : 'text'} slotProps={field === 'rate' || field === 'amount' ? { htmlInput: { min: 0, step: '.01' } } : undefined} />)}<Button type="submit" variant="contained">Add</Button></Stack><TableContainer sx={{ flex: 1 }}><Table><TableHead><TableRow><TableCell>Name</TableCell><TableCell>Details</TableCell><TableCell>Status</TableCell><TableCell>Version</TableCell></TableRow></TableHead><TableBody>{items?.map((item) => <TableRow key={item.id}><TableCell>{item.name}</TableCell><TableCell>{'rate' in item ? `${item.rate}%` : 'amount' in item ? `${item.currency} ${item.amount}` : item.slug}</TableCell><TableCell><StatusText tone={item.active ? 'success' : 'neutral'}>{item.active ? 'Active' : 'Inactive'}</StatusText></TableCell><TableCell>{item.version}</TableCell></TableRow>)}</TableBody></Table></TableContainer></Stack></>
}

export function AdminInventoryPage() {
  const params = new URLSearchParams(location.search)
  const [productId, setProductId] = useState(params.get('product') ?? '')
  const [inventory, setInventory] = useState<Inventory | null>(null)
  const [error, setError] = useState<unknown>(null)
  const load = () => productId && api<Inventory>(`/admin/products/${productId}/inventory`).then(setInventory).catch(setError)
  useEffect(() => { void load() }, [productId])
  const adjust = async (event: FormEvent<HTMLElement>) => { event.preventDefault(); const data = new FormData(event.currentTarget as HTMLFormElement); try { setInventory(await api<Inventory>(`/admin/products/${productId}/inventory-adjustments`, { method: 'POST', body: JSON.stringify({ new_on_hand: Number(data.get('new_on_hand')), reason: data.get('reason'), note: data.get('note') }) })); setError(null) } catch (requestError) { setError(requestError) } }
  return <><PageTitle>Inventory</PageTitle><Stack gap={3} sx={{ maxWidth: 720 }}><TextField label="Product ID" value={productId} onChange={(event) => setProductId(event.target.value)} helperText="Select Inventory from a product row or enter a product ULID." />{error ? <ErrorNotice error={error} /> : null}{inventory ? <Stack direction={{ xs: 'column', sm: 'row' }} gap={4} sx={{ bgcolor: '#f5f8fc', p: 3 }}><Box><Typography color="text.secondary">On hand</Typography><Typography variant="h2">{inventory.on_hand}</Typography></Box><Box><Typography color="text.secondary">Reserved</Typography><Typography variant="h2">{inventory.reserved}</Typography></Box><Box><Typography color="text.secondary">Available</Typography><Typography variant="h2">{inventory.available}</Typography></Box></Stack> : null}<Stack component="form" onSubmit={(event) => void adjust(event)} gap={2}><Typography variant="h3">Record adjustment</Typography><TextField required label="New stock on hand" name="new_on_hand" type="number" /><FormControl><InputLabel id="reason-label">Reason</InputLabel><Select required defaultValue="" labelId="reason-label" label="Reason" name="reason">{['stock_received','correction','damaged_or_lost','customer_return','other'].map((reason) => <MenuItem key={reason} value={reason}>{reason.replaceAll('_', ' ')}</MenuItem>)}</Select></FormControl><TextField multiline minRows={3} label="Note (required for other)" name="note" /><Button disabled={!productId} type="submit" variant="contained">Save adjustment</Button></Stack></Stack></>
}

export function AdminImportsPage() {
  const [result, setResult] = useState<ProductImport | null>(null)
  const [error, setError] = useState<unknown>(null)
  const [override, setOverride] = useState(false)
  const submit = async (event: FormEvent<HTMLElement>) => { event.preventDefault(); setError(null); const form = new FormData(event.currentTarget as HTMLFormElement); form.set('override_stock', override ? '1' : '0'); if (override) form.set('confirm_stock_override', '1'); else form.delete('confirm_stock_override'); try { setResult(await api<ProductImport>('/admin/product-imports', { method: 'POST', body: form })) } catch (requestError) { setError(requestError) } }
  return <><PageTitle>Import products</PageTitle><Stack component="form" onSubmit={(event) => void submit(event)} gap={3} sx={{ maxWidth: 720 }}>{error ? <ErrorNotice error={error} /> : null}<Alert severity="info">Upload a UTF-8 CSV with name, SKU, price, stock, and weight columns. Valid rows commit independently.</Alert><Button component="label" variant="outlined">Choose CSV file<input accept=".csv,text/csv" hidden name="file" required type="file" /></Button><FormControl><Typography sx={{ fontWeight: 700 }}>Import mode</Typography><RadioGroup defaultValue="create_only" name="mode"><FormControlLabel value="create_only" control={<Radio />} label="Create only (safe default)" /><FormControlLabel value="update_only" control={<Radio />} label="Update only" /><FormControlLabel value="upsert" control={<Radio />} label="Upsert" /></RadioGroup></FormControl><FormControl><InputLabel id="policy-label">Unknown category policy</InputLabel><Select defaultValue="reject" labelId="policy-label" label="Unknown category policy" name="unknown_category_policy"><MenuItem value="reject">Reject row</MenuItem><MenuItem value="create">Create category</MenuItem><MenuItem value="uncategorized">Use Uncategorized</MenuItem></Select></FormControl><FormControlLabel control={<Switch checked={override} onChange={(event) => setOverride(event.target.checked)} />} label="Override stock for existing products" />{override ? <Alert severity="warning">Confirming this option can change existing stock and will create audit adjustments.</Alert> : null}<Button type="submit" variant="contained">Import CSV</Button>{result ? <Box aria-live="polite" sx={{ bgcolor: '#f5f8fc', p: 3 }}><Typography variant="h3">Import complete</Typography><Typography>{result.accepted_rows} accepted · {result.warning_rows} warnings · {result.rejected_rows} rejected</Typography>{result.rejected_rows ? <Button component="a" href={`/api/v1/admin/product-imports/${result.id}/rejections.csv`}>Download rejected rows</Button> : null}</Box> : null}</Stack></>
}

export function AdminOrdersPage() {
  const [orders, setOrders] = useState<OrderPage | null>(null)
  const [selected, setSelected] = useState<Order | null>(null)
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [error, setError] = useState<unknown>(null)
  const load = () => api<OrderPage>(`/admin/orders${queryString({ ...filters, per_page: 50 })}`).then(setOrders).catch(setError)
  useEffect(() => { void load() }, [])
  const inspect = async (id: string) => { try { setSelected(await api<Order>(`/admin/orders/${id}`)) } catch (requestError) { setError(requestError) } }
  return <><PageTitle>Orders</PageTitle><Stack component="form" direction={{ xs: 'column', md: 'row' }} gap={1.5} onSubmit={(event) => { event.preventDefault(); void load() }} sx={{ mb: 3 }}>{['status','payment_status','date_from','date_to','email','number'].map((name) => <TextField key={name} label={name.replaceAll('_', ' ')} type={name.startsWith('date') ? 'date' : 'text'} slotProps={name.startsWith('date') ? { inputLabel: { shrink: true } } : undefined} value={filters[name] ?? ''} onChange={(event) => setFilters((current) => ({ ...current, [name]: event.target.value }))} />)}<Button type="submit" variant="contained">Apply filters</Button></Stack>{error ? <ErrorNotice error={error} /> : null}<TableContainer><Table><TableHead><TableRow><TableCell>Order</TableCell><TableCell>Customer</TableCell><TableCell>Total</TableCell><TableCell>Order state</TableCell><TableCell>Payment state</TableCell><TableCell>Date</TableCell></TableRow></TableHead><TableBody>{orders?.items.map((order) => <TableRow hover key={order.id} onClick={() => void inspect(order.id)} tabIndex={0} sx={{ cursor: 'pointer' }}><TableCell><Button>{order.number}</Button></TableCell><TableCell>{order.customer_email ?? 'Guest'}</TableCell><TableCell>{order.currency} {order.total}</TableCell><TableCell><StatusText>{order.status.replaceAll('_', ' ')}</StatusText></TableCell><TableCell><StatusText>{order.payment_status.replaceAll('_', ' ')}</StatusText></TableCell><TableCell>{new Date(order.created_at).toLocaleString()}</TableCell></TableRow>)}</TableBody></Table></TableContainer><OrderDrawer order={selected} close={() => setSelected(null)} /></>
}

function OrderDrawer({ order, close }: { order: Order | null; close: () => void }) {
  return <Drawer anchor="right" open={Boolean(order)} onClose={close} slotProps={{ paper: { sx: { maxWidth: '100%', p: 3, width: 480 } } }}>{order ? <Stack gap={2}><Stack direction="row" justifyContent="space-between"><Box><Typography variant="h2">Order {order.number}</Typography><Typography color="text.secondary">{new Date(order.created_at).toLocaleString()}</Typography></Box><Button onClick={close}>Close</Button></Stack><Divider /><Typography variant="h3">Monetary snapshot</Typography>{Object.entries(order.amounts ?? { total: order.total, currency: order.currency }).map(([key, value]) => <Stack direction="row" justifyContent="space-between" key={key}><Typography color="text.secondary">{key.replaceAll('_', ' ')}</Typography><Typography>{String(value)}</Typography></Stack>)}<Typography variant="h3">Shipping address snapshot</Typography><Typography>{order.shipping_address.name}<br />{order.shipping_address.line1}<br />{order.shipping_address.city}, {order.shipping_address.region} {order.shipping_address.postal_code}<br />{order.shipping_address.country}</Typography><Typography variant="h3">Reservations</Typography><pre>{JSON.stringify(order.reservations ?? [], null, 2)}</pre><Typography variant="h3">Payment attempts</Typography><pre>{JSON.stringify(order.payment ?? {}, null, 2)}</pre><Typography variant="h3">Review history</Typography><pre>{JSON.stringify(order.manual_review ?? {}, null, 2)}</pre></Stack> : null}</Drawer>
}

export function AdminSettingsPage() {
  const [settings, setSettings] = useState<Setting[] | null>(null)
  const [error, setError] = useState<unknown>(null)
  const load = () => api<Setting[]>('/admin/settings').then(setSettings).catch(setError)
  useEffect(() => { void load() }, [])
  const save = async (event: FormEvent<HTMLElement>) => { event.preventDefault(); const data = new FormData(event.currentTarget as HTMLFormElement); try { await api<Setting>('/admin/settings/reservation_timeout_seconds', { method: 'PUT', body: JSON.stringify({ value: Number(data.get('value')) }) }); void load() } catch (requestError) { setError(requestError) } }
  const remove = async () => { try { await api<Setting>('/admin/settings/reservation_timeout_seconds', { method: 'DELETE' }); void load() } catch (requestError) { setError(requestError) } }
  const setting = settings?.find((item) => item.key === 'reservation_timeout_seconds')
  return <><PageTitle>Settings</PageTitle><Stack gap={3} sx={{ maxWidth: 620 }}>{error ? <ErrorNotice error={error} /> : null}<Alert severity="info">Database overrides affect only new reservations. Existing expiration timestamps are unchanged.</Alert><Box sx={{ border: 1, borderColor: 'divider', p: 3 }}><Typography variant="h3">Reservation timeout</Typography><Typography color="text.secondary" sx={{ my: 1 }}>Current effective value: {setting?.value ?? '—'} seconds · source: {setting?.source ?? '—'}</Typography><Stack component="form" onSubmit={(event) => void save(event)} direction={{ xs: 'column', sm: 'row' }} gap={2}><TextField required defaultValue={setting?.value ?? 120} slotProps={{ htmlInput: { min: 30, max: 3600 } }} label="Timeout in seconds" name="value" type="number" /><Button type="submit" variant="contained">Save override</Button><Button disabled={setting?.source !== 'database'} onClick={() => void remove()}>Remove override</Button></Stack></Box></Stack></>
}

export function AdminReviewsPage() {
  const [orders, setOrders] = useState<OrderPage | null>(null)
  const [selected, setSelected] = useState<Order | null>(null)
  const [note, setNote] = useState('')
  const [error, setError] = useState<unknown>(null)
  const load = () => api<OrderPage>('/admin/manual-reviews?per_page=50').then(setOrders).catch(setError)
  useEffect(() => { void load() }, [])
  const resolve = async () => {
    const review = selected?.manual_review as { id?: string; version?: number } | null
    if (!review?.id || !selected) return
    try { await api<Order>(`/admin/manual-reviews/${review.id}/resolve`, { method: 'POST', body: JSON.stringify({ note, version: selected.version }) }); setSelected(null); setNote(''); void load() } catch (requestError) { setError(requestError) }
  }
  return <><PageTitle>Manual reviews</PageTitle>{error ? <ErrorNotice error={error} /> : null}{orders?.items.length === 0 ? <Empty title="No orders need review" detail="Late payment conflicts appear here." /> : <Stack>{orders?.items.map((order) => <Button key={order.id} onClick={() => setSelected(order)} sx={{ borderBottom: 1, borderColor: 'divider', justifyContent: 'space-between', p: 2 }}><span>{order.number} · {order.customer_email ?? 'Guest'}</span><span>{order.currency} {order.total}</span></Button>)}</Stack>}<Dialog open={Boolean(selected)} onClose={() => setSelected(null)} fullWidth><DialogTitle>Mark {selected?.number} processed</DialogTitle><DialogContent><Alert severity="warning" sx={{ my: 2 }}>This records that the review was resolved externally. It does not refund, cancel, fulfill, or allocate stock.</Alert><TextField required fullWidth label="Resolution note" multiline minRows={4} value={note} onChange={(event) => setNote(event.target.value)} helperText="Required for the permanent audit history." /></DialogContent><DialogActions><Button onClick={() => setSelected(null)}>Cancel</Button><Button disabled={!note.trim()} onClick={() => void resolve()} variant="contained">Mark processed</Button></DialogActions></Dialog></>
}

export function AdminAuditPage() {
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [logs, setLogs] = useState<AuditLogPage | null>(null)
  const [error, setError] = useState<unknown>(null)
  const load = () => api<AuditLogPage>(`/admin/audit-logs${queryString({ ...filters, per_page: 50 })}`).then(setLogs).catch(setError)
  useEffect(() => { void load() }, [])
  return <><PageTitle>Audit log</PageTitle><Stack component="form" direction={{ xs: 'column', md: 'row' }} gap={1.5} onSubmit={(event) => { event.preventDefault(); void load() }} sx={{ mb: 3 }}>{['q','actor','action','target','date_from','date_to'].map((name) => <TextField key={name} label={name === 'q' ? 'Search audit log' : name.replaceAll('_', ' ')} type={name.startsWith('date') ? 'date' : 'text'} slotProps={name.startsWith('date') ? { inputLabel: { shrink: true } } : undefined} value={filters[name] ?? ''} onChange={(event) => setFilters((current) => ({ ...current, [name]: event.target.value }))} />)}<Button type="submit" variant="contained">Search</Button></Stack>{error ? <ErrorNotice error={error} /> : null}<TableContainer><Table><TableHead><TableRow><TableCell>Date</TableCell><TableCell>Actor</TableCell><TableCell>Action</TableCell><TableCell>Target</TableCell><TableCell>Details</TableCell></TableRow></TableHead><TableBody>{logs?.items.map((log) => <TableRow key={log.id}><TableCell>{new Date(log.created_at).toLocaleString()}</TableCell><TableCell>{log.actor_id}</TableCell><TableCell>{log.action}</TableCell><TableCell>{log.subject_type} · {log.subject_id}</TableCell><TableCell><Box component="details"><summary>View redacted metadata</summary><pre>{JSON.stringify(log.metadata ?? {}, null, 2)}</pre></Box></TableCell></TableRow>)}</TableBody></Table></TableContainer></>
}
