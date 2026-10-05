import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import { AdminShell, StoreShell } from './components/AppShell'
import { Loading } from './components/Feedback'
import { useAuth } from './lib/auth'

const StorefrontPage = lazy(() => import('./pages/StorefrontPage').then((module) => ({ default: module.StorefrontPage })))
const ProductPage = lazy(() => import('./pages/ProductPage').then((module) => ({ default: module.ProductPage })))
const CartPage = lazy(() => import('./pages/CartPage').then((module) => ({ default: module.CartPage })))
const CheckoutPage = lazy(() => import('./pages/CheckoutPage').then((module) => ({ default: module.CheckoutPage })))
const LoginPage = lazy(() => import('./pages/AuthPages').then((module) => ({ default: module.LoginPage })))
const RegisterPage = lazy(() => import('./pages/AuthPages').then((module) => ({ default: module.RegisterPage })))
const SetupPage = lazy(() => import('./pages/AuthPages').then((module) => ({ default: module.SetupPage })))
const OrderDetailPage = lazy(() => import('./pages/OrdersPage').then((module) => ({ default: module.OrderDetailPage })))
const OrdersPage = lazy(() => import('./pages/OrdersPage').then((module) => ({ default: module.OrdersPage })))
const AdminPages = lazy(() => import('./pages/AdminPages').then((module) => ({ default: () => <AdminRoutes module={module} /> })))

function AdminRoutes({ module }: { module: typeof import('./pages/AdminPages') }) {
  return <Routes>
    <Route index element={<Navigate to="orders" replace />} />
    <Route path="products" element={<module.AdminProductsPage />} />
    <Route path="categories" element={<module.AdminResourcePage kind="categories" />} />
    <Route path="taxes" element={<module.AdminResourcePage kind="taxes" />} />
    <Route path="shipping" element={<module.AdminResourcePage kind="shipping" />} />
    <Route path="inventory" element={<module.AdminInventoryPage />} />
    <Route path="imports" element={<module.AdminImportsPage />} />
    <Route path="settings" element={<module.AdminSettingsPage />} />
    <Route path="orders" element={<module.AdminOrdersPage />} />
    <Route path="reviews" element={<module.AdminReviewsPage />} />
    <Route path="audit" element={<module.AdminAuditPage />} />
  </Routes>
}

function StoreOnly() {
  const { principal } = useAuth()
  return principal?.role === 'admin' ? <Navigate to="/admin/orders" replace /> : <Outlet />
}

function CustomerOnly() {
  const { principal } = useAuth()
  return principal?.role === 'customer' ? <Outlet /> : <Navigate to="/login" replace />
}

function AdminOnly() {
  const { principal } = useAuth()
  return principal?.role === 'admin' ? <Outlet /> : <Navigate to="/login" replace />
}

export default function App() {
  const { loading, setupAvailable } = useAuth()
  const location = useLocation()
  if (loading) return <Loading label="Starting Northstar Supply" />
  if (setupAvailable && location.pathname !== '/setup') return <Navigate to="/setup" replace />
  return <Suspense fallback={<Loading label="Loading page" />}><Routes>
    <Route path="/setup" element={<SetupPage />} />
    <Route element={<StoreOnly />}>
      <Route element={<StoreShell />}>
        <Route index element={<StorefrontPage />} />
        <Route path="products/:productId" element={<ProductPage />} />
        <Route path="cart" element={<CartPage />} />
        <Route path="checkout" element={<CheckoutPage />} />
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="orders/:orderId" element={<OrderDetailPage />} />
        <Route element={<CustomerOnly />}><Route path="orders" element={<OrdersPage />} /></Route>
      </Route>
    </Route>
    <Route element={<AdminOnly />}>
      <Route path="admin/*" element={<AdminShell />}><Route path="*" element={<AdminPages />} /></Route>
    </Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></Suspense>
}
