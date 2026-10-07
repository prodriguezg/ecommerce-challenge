import { AppBar, Badge, Box, Button, Container, Drawer, IconButton, Link as MuiLink, Toolbar, Typography, useMediaQuery } from '@mui/material'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useState } from 'react'
import { useAuth } from '../lib/auth'
import { useCart } from '../lib/cart'
import { Stack } from './Stack'

export function Mark() {
  return <Box aria-hidden sx={{ color: 'primary.main', fontSize: 28, fontWeight: 900, letterSpacing: '-.2em', mr: 1 }}>✦</Box>
}

export function StoreShell() {
  const { principal, logout } = useAuth()
  const { count } = useCart()
  const navigate = useNavigate()
  return <>
    <a className="skip-link" href="#main-content">Skip to content</a>
    <AppBar color="inherit" position="sticky" sx={{ borderBottom: 1, borderColor: 'divider' }}>
      <Container maxWidth="xl"><Toolbar disableGutters sx={{ gap: { xs: .5, sm: 1, md: 3 }, minHeight: 68 }}>
        <MuiLink component={Link} to="/" color="inherit" underline="none" sx={{ alignItems: 'center', display: 'flex', mr: 'auto' }}>
          <Mark /><Typography sx={{ fontSize: { xs: 16, sm: 22 }, fontWeight: 800, lineHeight: 1.1 }}>Northstar Supply</Typography>
        </MuiLink>
        <Button component={NavLink} to="/" color="inherit" sx={{ minWidth: 0, px: { xs: .75, sm: 1.5 }, whiteSpace: 'nowrap' }}>Shop</Button>
        {principal?.role === 'customer' ? <Button component={NavLink} to="/orders" color="inherit" sx={{ minWidth: 0, px: { xs: .75, sm: 1.5 }, whiteSpace: 'nowrap' }}>Orders</Button> : null}
        <Button component={Link} to="/cart" variant="outlined" sx={{ minWidth: 0, px: { xs: 1, sm: 2 }, whiteSpace: 'nowrap' }}><Badge badgeContent={count} color="primary" sx={{ pr: count ? 1 : 0 }}>Cart</Badge></Button>
        {principal ? <Button onClick={() => void logout().then(() => navigate('/'))} sx={{ minWidth: 0, px: { xs: .75, sm: 1.5 }, whiteSpace: 'nowrap' }}>Sign out</Button> : <Button component={Link} to="/login" sx={{ minWidth: 0, px: { xs: .75, sm: 1.5 }, whiteSpace: 'nowrap' }}>Sign in</Button>}
      </Toolbar></Container>
    </AppBar>
    <Box component="main" id="main-content"><Outlet /></Box>
  </>
}

const adminItems = [
  ['Products', '/admin/products'], ['Categories', '/admin/categories'], ['Taxes', '/admin/taxes'],
  ['Shipping', '/admin/shipping'], ['Imports', '/admin/imports'],
  ['Settings', '/admin/settings'], ['Orders', '/admin/orders'], ['Reviews', '/admin/reviews'], ['Audit log', '/admin/audit'],
]

export function AdminShell() {
  const { principal, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const mobile = useMediaQuery('(max-width:900px)')
  const [open, setOpen] = useState(false)
  const nav = <Stack component="nav" aria-label="Administrator" sx={{ height: '100%', p: 2 }}>
    <Stack direction="row" alignItems="center" sx={{ mb: 3 }}><Mark /><Box><Typography sx={{ fontWeight: 800 }}>Northstar Supply</Typography><Typography variant="caption">Administrator</Typography></Box></Stack>
    {adminItems.map(([label, path]) => <Button key={path} component={NavLink} to={path} onClick={() => setOpen(false)} color="inherit" sx={{ bgcolor: location.pathname === path ? '#eaf0ff' : 'transparent', justifyContent: 'flex-start', mb: .5 }}>{label}</Button>)}
    <Box sx={{ mt: 'auto', pt: 3, borderTop: 1, borderColor: 'divider' }}><Typography sx={{ fontWeight: 700 }}>{principal?.name}</Typography><Typography color="text.secondary" variant="caption">{principal?.email}</Typography><Button fullWidth onClick={() => void logout().then(() => navigate('/login'))} sx={{ justifyContent: 'flex-start', mt: 1 }}>Log out</Button></Box>
  </Stack>
  return <Box sx={{ display: 'flex', minHeight: '100vh' }}>
    {mobile ? <><AppBar color="inherit"><Toolbar><IconButton aria-label="Open admin navigation" onClick={() => setOpen(true)}>☰</IconButton><Typography sx={{ fontWeight: 800 }}>Northstar Admin</Typography></Toolbar></AppBar><Toolbar /><Drawer open={open} onClose={() => setOpen(false)} slotProps={{ paper: { sx: { width: 260 } } }}>{nav}</Drawer></> : <Box component="aside" sx={{ borderRight: 1, borderColor: 'divider', flexShrink: 0, width: 240 }}>{nav}</Box>}
    <Box component="main" id="main-content" sx={{ flex: 1, minWidth: 0, p: { xs: 2, md: 4 } }}><Outlet /></Box>
  </Box>
}
