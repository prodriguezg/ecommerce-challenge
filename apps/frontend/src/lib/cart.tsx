import { createContext, useCallback, useContext, useEffect, useMemo, useState, type PropsWithChildren } from 'react'
import { api, type Cart, type CartLineInput, type Product } from './api'
import { useAuth } from './auth'

export type GuestLine = { product: Product; quantity: number }
type CartContextValue = {
  lines: GuestLine[]
  serverCart: Cart | null
  notice: string
  count: number
  add: (product: Product) => Promise<void>
  setQuantity: (product: Product, quantity: number) => Promise<void>
  remove: (product: Product) => Promise<void>
  mergeGuestCart: () => Promise<void>
  clearAfterCheckout: () => void
}

const STORAGE_KEY = 'northstar-cart-v1'
const CartContext = createContext<CartContextValue | null>(null)

function readGuestLines(): GuestLine[] {
  try {
    const value = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') as { version?: number; lines?: GuestLine[] }
    return value.version === 1 && Array.isArray(value.lines) ? value.lines : []
  } catch {
    return []
  }
}

export function CartProvider({ children }: PropsWithChildren) {
  const { principal } = useAuth()
  const [lines, setLines] = useState<GuestLine[]>(readGuestLines)
  const [serverCart, setServerCart] = useState<Cart | null>(null)
  const [notice, setNotice] = useState('')

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ version: 1, lines }))
  }, [lines])

  useEffect(() => {
    if (principal?.role === 'customer') void api<Cart>('/cart').then(setServerCart).catch(() => setServerCart(null))
    else queueMicrotask(() => setServerCart(null))
  }, [principal])

  const setQuantity = useCallback(async (product: Product, quantity: number) => {
    if (quantity <= 0) {
      if (principal?.role === 'customer') setServerCart(await api<Cart>(`/cart/items/${product.id}`, { method: 'DELETE' }))
      else setLines((current) => current.filter((line) => line.product.id !== product.id))
      return
    }
    if (principal?.role === 'customer') {
      setServerCart(await api<Cart>(`/cart/items/${product.id}`, { method: 'PUT', body: JSON.stringify({ quantity }) }))
    } else {
      setLines((current) => {
        const found = current.find((line) => line.product.id === product.id)
        return found
          ? current.map((line) => line.product.id === product.id ? { ...line, quantity } : line)
          : [...current, { product, quantity }]
      })
    }
  }, [principal])

  const mergeGuestCart = useCallback(async () => {
    const guestLines: CartLineInput[] = lines.map(({ product, quantity }) => ({ product_id: product.id, quantity }))
    if (!guestLines.length) return
    const merged = await api<Cart>('/cart/merge', { method: 'POST', body: JSON.stringify({ lines: guestLines }) })
    setServerCart(merged)
    setLines([])
    setNotice(merged.lines.some((line) => line.adjustment)
      ? 'Your guest cart was merged. Some quantities were adjusted to available stock.'
      : 'Your guest cart was merged successfully.')
  }, [lines])

  const count = principal?.role === 'customer'
    ? serverCart?.lines.reduce((total, line) => total + line.quantity, 0) ?? 0
    : lines.reduce((total, line) => total + line.quantity, 0)

  const value = useMemo<CartContextValue>(() => ({
    lines,
    serverCart,
    notice,
    count,
    add: async (product) => setQuantity(product, (lines.find((line) => line.product.id === product.id)?.quantity ?? 0) + 1),
    setQuantity,
    remove: async (product) => setQuantity(product, 0),
    mergeGuestCart,
    clearAfterCheckout: () => setLines([]),
  }), [count, lines, mergeGuestCart, notice, serverCart, setQuantity])

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart() {
  const context = useContext(CartContext)
  if (!context) throw new Error('useCart must be used inside CartProvider')
  return context
}
