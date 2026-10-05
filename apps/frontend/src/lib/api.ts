import type { components } from '@ecommerce/api-client'

export type Principal = components['schemas']['Principal']
export type Product = components['schemas']['Product']
export type ProductPage = components['schemas']['ProductPage']
export type Category = components['schemas']['Category']
export type Tax = components['schemas']['Tax']
export type ShippingMethod = components['schemas']['ShippingMethod']
export type Cart = components['schemas']['Cart']
export type CartLineInput = components['schemas']['CartLineInput']
export type CheckoutInput = components['schemas']['CheckoutInput']
export type CheckoutResult = components['schemas']['CheckoutResult']
export type Order = components['schemas']['Order']
export type OrderPage = components['schemas']['OrderPage']
export type OrderStatusView = components['schemas']['OrderStatusView']
export type Setting = components['schemas']['Setting']
export type AuditLogPage = components['schemas']['AuditLogPage']
export type Inventory = components['schemas']['Inventory']
export type InventoryAdjustmentPage = components['schemas']['InventoryAdjustmentPage']
export type ProductImport = components['schemas']['ProductImport']

export class ApiProblem extends Error {
  status: number
  code?: string
  errors: Record<string, string[]>

  constructor(status: number, payload: unknown) {
    const problem = (payload ?? {}) as { detail?: string; title?: string; code?: string; errors?: Record<string, string[]> }
    super(problem.detail ?? problem.title ?? `Request failed (${status})`)
    this.name = 'ApiProblem'
    this.status = status
    this.code = problem.code
    this.errors = problem.errors ?? {}
  }
}

const readCookie = (name: string) =>
  document.cookie
    .split('; ')
    .find((entry) => entry.startsWith(`${name}=`))
    ?.slice(name.length + 1)

async function ensureCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = init.method?.toUpperCase() ?? 'GET'
  if (!['GET', 'HEAD'].includes(method)) await ensureCsrf()

  const headers = new Headers(init.headers)
  if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  const xsrf = readCookie('XSRF-TOKEN')
  if (xsrf) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf))
  headers.set('Accept', 'application/json')

  const response = await fetch(`/api/v1${path}`, { ...init, credentials: 'include', headers })
  if (!response.ok) {
    const payload = await response.json().catch(() => null)
    throw new ApiProblem(response.status, payload)
  }
  if (response.status === 204) return undefined as T
  return response.json() as Promise<T>
}

export const queryString = (values: Record<string, string | number | boolean | undefined>) => {
  const query = new URLSearchParams()
  Object.entries(values).forEach(([key, value]) => {
    if (value !== undefined && value !== '') query.set(key, String(value))
  })
  const text = query.toString()
  return text ? `?${text}` : ''
}
