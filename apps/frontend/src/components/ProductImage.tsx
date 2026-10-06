import { Box, type SxProps, type Theme } from '@mui/material'

const PRODUCT_PLACEHOLDER = '/images/product-placeholder.svg'

export function ProductImage({ name, src, loading, sx }: { name: string; src?: string | null; loading?: 'eager' | 'lazy'; sx?: SxProps<Theme> }) {
  return <Box
    component="img"
    src={src || PRODUCT_PLACEHOLDER}
    alt={name}
    loading={loading}
    onError={(event) => {
      const image = event.currentTarget
      image.onerror = null
      image.src = PRODUCT_PLACEHOLDER
    }}
    sx={sx}
  />
}
