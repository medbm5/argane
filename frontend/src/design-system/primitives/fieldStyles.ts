/** Shared look for text inputs, selects and date fields. */
export const fieldClasses = [
  'h-10 w-full rounded-sm border border-line bg-surface px-3 text-base text-fg',
  'placeholder:text-muted/70 transition-colors duration-200',
  'hover:border-sepia-400 focus-visible:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/30',
  'disabled:cursor-not-allowed disabled:opacity-60',
  'aria-invalid:border-danger aria-invalid:ring-danger/20',
].join(' ')
