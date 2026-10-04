import { Box, Chip, Container, Typography } from '@mui/material'

export default function App() {
  return (
    <Container component="main" maxWidth="md">
      <Box sx={{ py: { xs: 6, md: 12 } }}>
        <Box sx={{ alignItems: 'flex-start', display: 'flex', flexDirection: 'column', gap: 3 }}>
          <Chip color="success" label="Foundation ready" />
          <Typography component="h1" variant="h2">
            E-commerce challenge
          </Typography>
          <Typography color="text.secondary" variant="h6">
            The React storefront and administration interface will be implemented in subsequent issues.
          </Typography>
        </Box>
      </Box>
    </Container>
  )
}
