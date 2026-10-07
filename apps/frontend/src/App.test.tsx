import '@testing-library/jest-dom/vitest'
import { CssBaseline, ThemeProvider } from '@mui/material'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { AuthProvider } from './lib/auth'
import { CartProvider } from './lib/cart'
import { theme } from './theme'

const product = { id: '01TESTPRODUCT00000000000000', sku: 'DESK-1', name: 'Riverside Desk', description: 'Solid oak desk', price: '249.00', weight_kg: '12.5000', price_excludes_tax: true, currency: 'USD', category: null, tax_id: '01ACTIVETAX0000000000000000', image_url: '/missing-product.jpg', in_stock: true, version: 1, created_at: '2026-10-01T00:00:00Z', updated_at: '2026-10-01T00:00:00Z' }
const categories = [
  { id: '01ACTIVECATEGORY00000000000', name: 'Trail gear', slug: 'trail-gear', active: true, version: 1 },
  { id: '01INACTIVECATEGORY000000000', name: 'Retired gear', slug: 'retired-gear', active: false, version: 2 },
]
const taxes = [
  { id: '01ACTIVETAX0000000000000000', name: 'Standard tax', rate: '10', active: true, version: 1 },
  { id: '01INACTIVETAX00000000000000', name: 'Retired tax', rate: '5', active: false, version: 2 },
]

function response(body: unknown, status = 200) {
  return Promise.resolve(new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }))
}

function mockApi(principal: unknown = null, setupAvailable = false) {
  vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method?.toUpperCase() ?? 'GET'
    if (url.endsWith('/sanctum/csrf-cookie')) return response(null, 204)
    if (url.includes('/setup/status')) return response({ available: setupAvailable })
    if (url.includes('/auth/me')) return principal ? response(principal) : response({ detail: 'Unauthenticated' }, 401)
    if (url.endsWith(`/admin/products/${product.id}`) && method === 'PUT') return response({ ...product, ...JSON.parse(String(init?.body)), version: 2 })
    if (url.endsWith(`/admin/products/${product.id}`) && method === 'DELETE') return response(null, 204)
    if (url.endsWith('/admin/products') && method === 'POST') return response({ ...product, ...JSON.parse(String(init?.body)) }, 201)
    if (url.endsWith('/admin/product-imports') && method === 'POST') return response({ id: '01IMPORT', status: 'completed', original_filename: 'products.csv', mode: 'create_only', unknown_category_policy: 'reject', stock_override: false, stock_override_confirmed_at: null, total_rows: 1, accepted_rows: 1, warning_rows: 0, rejected_rows: 0, created_at: '2026-10-06T00:00:00Z', updated_at: '2026-10-06T00:00:00Z' })
    if (url.endsWith('/admin/shipping-methods') && method === 'POST') return response({ id: '01SHIPPING', name: 'Courier', amount: '5.00', currency: 'USD', active: true, version: 1 }, 201)
    if (url.endsWith('/admin/shipping-methods')) return response([])
    if (url.includes('/products?')) return response({ items: [product], pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 } })
    if (url.endsWith('/admin/categories')) return response(categories)
    if (url.endsWith('/categories')) return response([])
    if (url.endsWith('/admin/taxes')) return response(taxes)
    if (url.includes('/admin/orders')) return response({ items: [], pagination: { page: 1, per_page: 50, total: 0, total_pages: 0 } })
    if (url.includes('/cart')) return response({ lines: [], subtotal: '0.00', product_tax: '0.00', shipping_tax: '0.00', tax: '0.00', shipping: '0.00', total: '0.00', currency: 'USD', requires_confirmation: false })
    return response({}, 404)
  }))
}

function renderApp(route = '/') {
  return render(<ThemeProvider theme={theme}><CssBaseline /><MemoryRouter initialEntries={[route]}><AuthProvider><CartProvider><App /></CartProvider></AuthProvider></MemoryRouter></ThemeProvider>)
}

afterEach(() => { cleanup(); vi.unstubAllGlobals(); localStorage.clear() })

describe('application routing and critical interactions', () => {
  it('renders searchable products with tax-exclusive pricing and an accessible cart action', async () => {
    mockApi(); renderApp()
    expect(await screen.findByRole('heading', { name: 'Products' })).toBeInTheDocument()
    expect(await screen.findByText('Riverside Desk')).toBeInTheDocument()
    expect(await screen.findByText('excluding tax')).toBeInTheDocument()
    const image = screen.getByRole('img', { name: 'Riverside Desk' })
    fireEvent.error(image)
    expect(image).toHaveAttribute('src', '/images/product-placeholder.svg')
    fireEvent.click(screen.getByRole('button', { name: 'Add to cart' }))
    expect(await screen.findByText('1', { selector: '.MuiBadge-badge' })).toBeInTheDocument()
    expect(screen.getByRole('searchbox', { name: /search products/i })).toBeInTheDocument()
  })

  it('claims a paid guest order using only the password accepted by the API', async () => {
    mockApi()
    const fallback = fetch
    const order = {
      id: '01TESTORDER000000000000000',
      number: 'ORD-100001',
      status: 'paid',
      payment_status: 'succeeded',
      lines: [{ product_id: product.id, name: product.name, quantity: 1, unit_price: product.price, line_subtotal: product.price, currency: 'USD', available: true, stock_limit: 1 }],
      total: product.price,
      currency: 'USD',
      shipping_address: { name: 'Guest Buyer', line1: '1 Main Street', city: 'Montevideo', region: 'Montevideo', postal_code: '11000', country: 'UY', phone: '+598 1 234 567' },
      version: 1,
      created_at: '2026-10-06T00:00:00Z',
      updated_at: '2026-10-06T00:00:00Z',
    }
    vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      if (url.includes(`/guest-orders/${order.id}/status`)) return response({ order_id: order.id, status: 'paid', payment_status: 'succeeded', updated_at: order.updated_at })
      if (url.includes(`/guest-orders/${order.id}/register`)) return response({ id: '01CUSTOMER', name: 'Guest Buyer', email: 'guest@example.test', role: 'customer' }, 201)
      if (url.includes(`/guest-orders/${order.id}`)) return response(order)
      return fallback(input, init)
    }))

    renderApp(`/orders/${order.id}?guest_token=${'a'.repeat(64)}`)

    expect(await screen.findByRole('heading', { name: 'Create an account (optional)' })).toBeVisible()
    expect(screen.queryByRole('textbox', { name: 'Full name' })).not.toBeInTheDocument()
    fireEvent.change(screen.getByLabelText(/Create a password/), { target: { value: 'SecurePass1!' } })
    fireEvent.click(screen.getByRole('button', { name: 'Create account and claim order' }))

    await waitFor(() => expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining(`/guest-orders/${order.id}/register`),
      expect.objectContaining({ method: 'POST', body: JSON.stringify({ password: 'SecurePass1!' }) }),
    ))
  })

  it.each([
    { productTax: '0.00', shippingTax: '1.50', tax: '1.50', total: '50.99' },
    { productTax: '3.45', shippingTax: '0.00', tax: '3.45', total: '52.94' },
    { productTax: '3.45', shippingTax: '1.50', tax: '4.95', total: '54.44' },
    { productTax: '0.00', shippingTax: '0.00', tax: '0.00', total: '49.49' },
  ])('shows separate checkout tax rows for product $productTax and shipping $shippingTax', async ({ productTax, shippingTax, tax, total }) => {
    mockApi()
    const fallback = fetch
    vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      if (url.endsWith('/shipping-methods')) return response([{ id: '01SHIPPING', name: 'Ground', amount: '15.00', currency: 'USD', tax_id: null, active: true, version: 1 }])
      if (url.endsWith('/cart/quote')) return response({
        lines: [{ product_id: product.id, name: product.name, quantity: 1, unit_price: '34.49', line_subtotal: '34.49', currency: 'USD', available: true, stock_limit: 5 }],
        subtotal: '34.49', shipping: '15.00', product_tax: productTax, shipping_tax: shippingTax, tax, total, currency: 'USD', requires_confirmation: false,
      })
      return fallback(input, init)
    }))
    localStorage.setItem('northstar-cart-v1', JSON.stringify({ version: 1, lines: [{ product, quantity: 1 }] }))
    renderApp('/checkout')

    const productRow = (await screen.findByText('Product tax')).parentElement!
    const shippingRow = screen.getByText('Shipping tax').parentElement!
    await waitFor(() => expect(within(productRow).getByText(`USD ${productTax}`)).toBeVisible())
    expect(within(shippingRow).getByText(`USD ${shippingTax}`)).toBeVisible()
    expect(screen.queryByText('Tax', { exact: true })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: `USD ${total}` })).toBeVisible()
    expect(screen.getByRole('button', { name: 'Place order (simulated)' })).toBeEnabled()
  })

  it('routes the sole first-run state to administrator setup', async () => {
    mockApi(null, true); renderApp('/')
    expect(await screen.findByRole('heading', { name: 'Set up Northstar Supply' })).toBeInTheDocument()
    expect(screen.getByText(/sole administrator/i)).toBeInTheDocument()
  })

  it('keeps administrators out of the customer storefront', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/')
    expect(await screen.findByRole('heading', { name: 'Orders' })).toBeInTheDocument()
    expect(screen.getByRole('navigation', { name: 'Administrator' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add to cart' })).not.toBeInTheDocument()
  })

  it('offers only active taxes when adding a product', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/products')
    fireEvent.click(await screen.findByRole('button', { name: 'Add product' }))
    const taxSelect = screen.getByRole('combobox', { name: 'Tax (optional)' })
    fireEvent.mouseDown(taxSelect)
    expect(await screen.findByRole('option', { name: 'Standard tax (10%)' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Retired tax (5%)' })).not.toBeInTheDocument()
  })

  it('offers only active categories when adding a product', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/products')
    fireEvent.click(await screen.findByRole('button', { name: 'Add product' }))
    const categorySelect = screen.getByRole('combobox', { name: 'Category (optional)' })
    fireEvent.mouseDown(categorySelect)
    expect(await screen.findByRole('option', { name: 'Trail gear' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Retired gear' })).not.toBeInTheDocument()
  })

  it('updates a product with its current version and selected tax', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/products')
    fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
    expect(screen.getByRole('heading', { name: 'Edit product' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tax (optional)' })).toHaveTextContent('Standard tax (10%)')
    expect(screen.getByRole('spinbutton', { name: 'Weight (kg)' })).toHaveValue(12.5)
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Updated desk' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(vi.mocked(fetch).mock.calls.some(([input, init]) => String(input).endsWith(`/admin/products/${product.id}`) && init?.method === 'PUT')).toBe(true))
    const call = vi.mocked(fetch).mock.calls.find(([input, init]) => String(input).endsWith(`/admin/products/${product.id}`) && init?.method === 'PUT')
    const headers = new Headers(call?.[1]?.headers)
    const body = JSON.parse(String(call?.[1]?.body))
    expect(headers.get('If-Match-Version')).toBe('1')
    expect(body).toMatchObject({ name: 'Updated desk', weight_kg: '12.5000', tax_id: taxes[0].id })
  })

  it('creates a product with an integer price and editable weight', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/products')
    fireEvent.click(await screen.findByRole('button', { name: 'Add product' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Standing desk' } })
    fireEvent.change(screen.getByRole('textbox', { name: 'SKU' }), { target: { value: 'STAND-1' } })
    fireEvent.change(screen.getByRole('textbox', { name: 'Description' }), { target: { value: 'Adjustable desk' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Price excluding tax' }), { target: { value: '100' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Weight (kg)' }), { target: { value: '18.75' } })
    fireEvent.click(screen.getByRole('button', { name: 'Create product' }))

    await waitFor(() => expect(vi.mocked(fetch).mock.calls.some(([input, init]) => String(input).endsWith('/admin/products') && init?.method === 'POST')).toBe(true))
    const call = vi.mocked(fetch).mock.calls.find(([input, init]) => String(input).endsWith('/admin/products') && init?.method === 'POST')
    expect(JSON.parse(String(call?.[1]?.body))).toMatchObject({ price: '100', weight_kg: '18.75' })
  })

  it('confirms and deletes a product with its current version', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/products')
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }))
    expect(screen.getByRole('heading', { name: 'Delete product?' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Delete product' }))

    await waitFor(() => expect(vi.mocked(fetch).mock.calls.some(([input, init]) => String(input).endsWith(`/admin/products/${product.id}`) && init?.method === 'DELETE')).toBe(true))
    const call = vi.mocked(fetch).mock.calls.find(([input, init]) => String(input).endsWith(`/admin/products/${product.id}`) && init?.method === 'DELETE')
    expect(new Headers(call?.[1]?.headers).get('If-Match-Version')).toBe('1')
  })

  it.each([
    ['/admin/categories', 'Add category'],
    ['/admin/taxes', 'Add tax'],
  ])('uses the correct singular resource heading on %s', async (route, heading) => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp(route)
    expect(await screen.findByRole('heading', { name: heading })).toBeInTheDocument()
  })

  it('selects an active tax by name when creating a shipping method', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/shipping')
    const taxSelect = await screen.findByRole('combobox', { name: 'Tax (optional)' })
    fireEvent.mouseDown(taxSelect)
    expect(await screen.findByRole('option', { name: 'Standard tax (10%)' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Retired tax (5%)' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: 'Standard tax (10%)' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'name' }), { target: { value: 'Courier' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: 'amount' }), { target: { value: '5.00' } })
    fireEvent.click(screen.getByRole('button', { name: /^Add$/ }))

    await waitFor(() => expect(vi.mocked(fetch).mock.calls.some(([input, init]) => String(input).endsWith('/admin/shipping-methods') && init?.method === 'POST')).toBe(true))
    const call = vi.mocked(fetch).mock.calls.find(([input, init]) => String(input).endsWith('/admin/shipping-methods') && init?.method === 'POST')
    expect(JSON.parse(String(call?.[1]?.body))).toMatchObject({ name: 'Courier', amount: '5.00', tax_id: taxes[0].id })
  })

  it('submits the existing create policy from Create missing categories', async () => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/imports')
    const file = new File(['name,sku,price,stock,weight_kg\nDesk,DESK-1,249,2,10\n'], 'products.csv', { type: 'text/csv' })
    fireEvent.change(await screen.findByLabelText('Choose CSV file'), { target: { files: [file] } })
    fireEvent.mouseDown(screen.getByRole('combobox', { name: 'Unknown category policy' }))
    expect(screen.getAllByRole('option')).toHaveLength(3)
    fireEvent.click(screen.getByRole('option', { name: 'Create missing categories' }))
    fireEvent.submit(screen.getByRole('button', { name: 'Import CSV' }).closest('form')!)

    expect(await screen.findByText('Import complete')).toBeInTheDocument()
    const call = vi.mocked(fetch).mock.calls.find(([input, init]) => String(input).endsWith('/admin/product-imports') && init?.method === 'POST')
    const body = call?.[1]?.body as FormData
    expect(body.get('unknown_category_policy')).toBe('create')
  })

  it.each([
    { override: false, expectedOverride: '0', expectedConfirmation: null },
    { override: true, expectedOverride: '1', expectedConfirmation: '1' },
  ])('submits the product import stock override contract when override is $override', async ({ override, expectedOverride, expectedConfirmation }) => {
    mockApi({ id: '01ADMIN', name: 'Admin', email: 'admin@example.com', role: 'admin' }); renderApp('/admin/imports')
    const file = new File(['name,sku,price,stock,weight_kg\nDesk,DESK-1,249.00,2,10\n'], 'products.csv', { type: 'text/csv' })
    fireEvent.change(await screen.findByLabelText('Choose CSV file'), { target: { files: [file] } })
    if (override) fireEvent.click(screen.getByRole('switch', { name: 'Override stock for existing products' }))
    fireEvent.submit(screen.getByRole('button', { name: 'Import CSV' }).closest('form')!)

    await waitFor(() => expect(vi.mocked(fetch).mock.calls.some(([input, init]) => String(input).endsWith('/admin/product-imports') && init?.method === 'POST')).toBe(true))
    const call = vi.mocked(fetch).mock.calls.find(([input, init]) => String(input).endsWith('/admin/product-imports') && init?.method === 'POST')
    const body = call?.[1]?.body as FormData
    expect(body.get('override_stock')).toBe(expectedOverride)
    expect(body.get('confirm_stock_override')).toBe(expectedConfirmation)
    expect(body.has('stock_override')).toBe(false)
    expect(body.has('stock_override_confirmed')).toBe(false)
    expect(await screen.findByText('Import complete')).toBeInTheDocument()
  })

  it('guards customer order history behind sign in', async () => {
    mockApi(); renderApp('/orders')
    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  })

  it('opens mobile-friendly filters from a keyboard-operable button', async () => {
    mockApi()
    vi.stubGlobal('matchMedia', vi.fn().mockImplementation((query) => ({ matches: query.includes('800'), media: query, onchange: null, addListener: vi.fn(), removeListener: vi.fn(), addEventListener: vi.fn(), removeEventListener: vi.fn(), dispatchEvent: vi.fn() })))
    renderApp()
    const filters = await screen.findByRole('button', { name: 'Filters' })
    fireEvent.click(filters)
    expect(await screen.findByRole('heading', { name: 'Filters' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'In-stock items only' })).toBeInTheDocument()
  })
})
