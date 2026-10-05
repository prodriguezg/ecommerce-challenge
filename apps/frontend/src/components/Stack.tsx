import { Box, type BoxProps } from '@mui/material'
import type { ResponsiveStyleValue } from '@mui/system'
import type { CSSProperties } from 'react'

type StackProps = BoxProps & {
  direction?: ResponsiveStyleValue<CSSProperties['flexDirection']>
  spacing?: ResponsiveStyleValue<number | string>
  gap?: ResponsiveStyleValue<number | string>
  alignItems?: ResponsiveStyleValue<CSSProperties['alignItems']>
  justifyContent?: ResponsiveStyleValue<CSSProperties['justifyContent']>
}

export function Stack({ direction = 'column', spacing, gap, alignItems, justifyContent, sx, ...props }: StackProps) {
  return <Box {...props} sx={[{ alignItems, display: 'flex', flexDirection: direction, gap: gap ?? spacing, justifyContent }, ...(Array.isArray(sx) ? sx : [sx])]} />
}
