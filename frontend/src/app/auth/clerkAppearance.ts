/**
 * Clerk appearance matching the mural design system, so sign-in feels native:
 * ink outlines, hard offset shadows, Anton headings, Space Grotesk text.
 * Hex values mirror tokens.css (Clerk needs concrete colors to derive its scales);
 * the element rules mirror fieldClasses and the Button primitive.
 */
const ink = '#15121e' // --ink-900
const paper = '#fbf8f1' // --paper-50
const paperAlt = '#ece5d6' // --paper-200
const muted = '#4a4556' // --sepia-800
const red = '#c22f1c' // --red-text
const popRed = '#e8432e' // --pop-red
const blue = '#2f4fe0' // --pop-blue
const yellow = '#ffc21a' // --pop-yellow
const display = "'Anton', 'Arial Narrow', Impact, sans-serif"
const text = "'Space Grotesk', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif"

/**
 * Clerk draws its inputs and buttons with `[data-variant]` selectors, which outrank a
 * plain element rule. Repeating the class (`&&&`) lifts ours above them.
 */
function strong<T extends object>(rules: T): { '&&&': T } {
  return { '&&&': rules }
}

/** Same look as fieldClasses: 2px ink outline, hard shadow on hover and focus. */
const field = {
  border: `2px solid ${ink}`,
  borderRadius: '2px',
  backgroundColor: paper,
  color: ink,
  boxShadow: 'none',
  transition: 'box-shadow 200ms, background-color 200ms',
  '&:hover': { boxShadow: `3px 3px 0 ${ink}` },
  '&:focus, &:focus-visible, &:focus-within, &[data-focus-within="true"]': {
    boxShadow: `4px 4px 0 ${blue}`,
    borderColor: ink,
    outline: 'none',
  },
  '&[aria-invalid="true"], &[data-feedback="error"]': {
    borderColor: red,
    boxShadow: `3px 3px 0 ${popRed}`,
  },
}

/** Same press-in motion as the Button primitive. */
const press = {
  transition: 'transform 200ms, box-shadow 200ms',
  '&:hover': { transform: 'translate(2px, 2px)' },
}

export const clerkAppearance = {
  variables: {
    colorPrimary: ink,
    colorPrimaryForeground: paper,
    colorDanger: red,
    colorSuccess: '#2fb36b',
    colorWarning: yellow,
    colorForeground: ink,
    colorMutedForeground: muted,
    colorBackground: paper,
    colorInput: paper,
    colorInputForeground: ink,
    colorNeutral: ink,
    colorBorder: ink,
    colorRing: blue,
    colorShadow: 'transparent',
    fontFamily: text,
    fontFamilyButtons: display,
    fontSize: '1rem',
    borderRadius: '2px',
  },
  elements: {
    cardBox: {
      border: `2px solid ${ink}`,
      borderRadius: '2px',
      boxShadow: `6px 6px 0 ${yellow}`,
    },
    card: { boxShadow: 'none', border: 'none', borderRadius: 0 },
    footer: { background: paperAlt, borderTop: `2px solid ${ink}` },
    headerTitle: {
      fontFamily: display,
      fontSize: '2rem',
      fontWeight: 400,
      letterSpacing: '0.01em',
      textTransform: 'uppercase',
    },
    headerSubtitle: { color: muted },
    formFieldLabel: { fontWeight: 500, color: ink },
    formFieldInput: strong({
      ...field,
      height: '2.75rem',
      maxHeight: 'none',
      padding: '0 0.75rem',
      fontSize: '1rem',
    }),
    otpCodeFieldInput: strong(field),
    formButtonPrimary: strong({
      ...press,
      '&::after': { display: 'none' },
      fontFamily: display,
      fontWeight: 400,
      fontSize: '1rem',
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      height: '2.75rem',
      backgroundColor: ink,
      color: paper,
      border: `2px solid ${ink}`,
      borderRadius: '2px',
      boxShadow: `4px 4px 0 ${popRed}`,
      '&:hover': {
        transform: 'translate(2px, 2px)',
        backgroundColor: ink,
        boxShadow: `2px 2px 0 ${popRed}`,
      },
      '&:focus-visible': { outline: `2px solid ${blue}`, outlineOffset: '2px' },
    }),
    socialButtonsBlockButton: strong({
      ...press,
      border: `2px solid ${ink}`,
      borderRadius: '2px',
      backgroundColor: paper,
      boxShadow: `3px 3px 0 ${ink}`,
      '&:hover': {
        transform: 'translate(2px, 2px)',
        backgroundColor: paper,
        boxShadow: `1px 1px 0 ${ink}`,
      },
    }),
    socialButtonsBlockButtonText: { color: ink, letterSpacing: '0.04em' },
    dividerLine: { backgroundColor: ink, height: '2px' },
    dividerText: {
      color: muted,
      textTransform: 'uppercase',
      letterSpacing: '0.12em',
      fontSize: '0.75rem',
    },
    identityPreview: { border: `2px solid ${ink}`, borderRadius: '2px', backgroundColor: paperAlt },
    identityPreviewEditButton: { color: red },
    formFieldAction: { color: red, fontWeight: 600 },
    footerActionLink: { color: red, fontWeight: 600, '&:hover': { color: ink } },
    alert: { border: `2px solid ${red}`, borderRadius: '2px' },
  },
} as const
