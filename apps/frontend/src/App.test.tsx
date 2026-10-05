import '@testing-library/jest-dom/vitest'
import { CssBaseline, ThemeProvider } from '@mui/material'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { AuthProvider } from './lib/auth'
import { CartProvider } from './lib/cart'
import { theme } from './theme'

const product = { id: '01TESTPRODUCT00000000000000', sku: 'DESK-1', name: 'Riverside Desk', description: 'Solid oak desk', price: '249.00', price_excludes_tax: true, currency: 'USD', category: null, image_url: '/placeholder.svg', in_stock: true, version: 1, created_at: '2026-10-01T00:00:00Z', updated_at: '2026-10-01T00:00:00Z' }

function response(body: unknown, status = 200) {
  return Promise.resolve(new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }))
}

function mockApi(principal: unknown = null, setupAvailable = false) {
  vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/setup/status')) return response({ available: setupAvailable })
    if (url.includes('/auth/me')) return principal ? response(principal) : response({ detail: 'Unauthenticated' }, 401)
    if (url.includes('/products?')) return response({ items: [product], pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 } })
    if (url.endsWith('/categories')) return response([])
    if (url.includes('/admin/orders')) return response({ items: [], pagination: { page: 1, per_page: 50, total: 0, total_pages: 0 } })
    if (url.includes('/cart')) return response({ lines: [], subtotal: '0.00', tax: '0.00', shipping: '0.00', total: '0.00', currency: 'USD', requires_confirmation: false })
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
    fireEvent.click(screen.getByRole('button', { name: 'Add to cart' }))
    expect(await screen.findByText('1', { selector: '.MuiBadge-badge' })).toBeInTheDocument()
    expect(screen.getByRole('searchbox', { name: /search products/i })).toBeInTheDocument()
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
