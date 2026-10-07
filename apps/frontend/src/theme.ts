import { createTheme } from '@mui/material/styles'

export const theme = createTheme({
  palette: {
    mode: 'light',
    primary: { main: '#2457d6', dark: '#163ea7', contrastText: '#ffffff' },
    secondary: { main: '#17202a' },
    error: { main: '#c73b36' },
    success: { main: '#167548' },
    background: { default: '#ffffff', paper: '#ffffff' },
    text: { primary: '#17202a', secondary: '#526171' },
    divider: '#d8e0ea',
  },
  shape: { borderRadius: 8 },
  typography: {
    fontFamily: '"Segoe UI", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
    h1: { fontSize: 'clamp(2rem, 4vw, 3.25rem)', fontWeight: 760, letterSpacing: '-0.04em', lineHeight: 1.05 },
    h2: { fontSize: 'clamp(1.65rem, 3vw, 2.25rem)', fontWeight: 740, letterSpacing: '-0.03em' },
    h3: { fontSize: '1.25rem', fontWeight: 700 },
    button: { fontSize: '0.925rem', fontWeight: 700, textTransform: 'none' },
  },
  components: {
    MuiButton: { styleOverrides: { root: { minHeight: 44, boxShadow: 'none' } } },
    MuiTextField: { defaultProps: { size: 'small' } },
    MuiFormControl: { defaultProps: { size: 'small' } },
    MuiPaper: { defaultProps: { elevation: 0 }, styleOverrides: { root: { backgroundImage: 'none' } } },
    MuiLink: { defaultProps: { underline: 'hover' } },
    MuiCssBaseline: { styleOverrides: { '::selection': { background: '#c9d8ff' }, ':focus-visible': { outline: '3px solid #2457d6', outlineOffset: '2px' } } },
  },
})
