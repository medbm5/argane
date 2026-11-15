/**
 * Clerk appearance matching our sepia tokens, so sign-in feels native.
 * Hex values mirror tokens.css (Clerk needs concrete colors to derive its scales).
 */
export const clerkAppearance = {
  variables: {
    colorPrimary: '#2B1A10',
    colorPrimaryForeground: '#FBF6EC',
    colorDanger: '#9A3A26',
    colorSuccess: '#5B6B3A',
    colorWarning: '#D4A657',
    colorForeground: '#2B1A10',
    colorMutedForeground: '#704214',
    colorBackground: '#FBF6EC',
    colorInput: '#FBF6EC',
    colorInputForeground: '#2B1A10',
    colorNeutral: '#2B1A10',
    colorBorder: '#D3BF9C',
    colorRing: '#704214',
    colorShadow: 'rgba(43, 26, 16, 0.14)',
    fontFamily: '"Instrument Sans", system-ui, sans-serif',
    borderRadius: '2px',
  },
  elements: {
    card: {
      boxShadow: '0 10px 30px -12px rgba(43, 26, 16, 0.25)',
      border: '1px solid #D3BF9C',
    },
    headerTitle: {
      fontFamily: '"Cormorant Garamond", Georgia, serif',
      fontSize: '1.953rem',
      fontWeight: 600,
      letterSpacing: '-0.01em',
    },
    formButtonPrimary: { textTransform: 'none', fontWeight: 500 },
    footerActionLink: { color: '#704214' },
  },
} as const
