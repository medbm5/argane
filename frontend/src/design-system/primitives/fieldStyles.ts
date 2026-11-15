/** Shared look for text inputs, selects and date fields: ink outline, hard shadow on focus. */
export const fieldClasses = [
  'h-11 w-full rounded-sm border-2 border-fg bg-surface px-3 text-base text-fg',
  'placeholder:text-muted/70 transition-[box-shadow,transform,background-color] duration-200',
  'hover:shadow-[3px_3px_0_var(--c-text)]',
  'focus-visible:shadow-[4px_4px_0_var(--pop-blue)] focus-visible:outline-none',
  'disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none',
  'aria-invalid:border-danger aria-invalid:shadow-[3px_3px_0_var(--pop-red)]',
].join(' ')
