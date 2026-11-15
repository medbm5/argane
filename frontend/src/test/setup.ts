import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// jsdom does not implement scrolling; ScrollRestoration calls it on navigation.
window.scrollTo = () => undefined

// jsdom lacks the pointer-capture and scrollIntoView APIs Radix Select relies on.
Element.prototype.hasPointerCapture = () => false
Element.prototype.releasePointerCapture = () => undefined
Element.prototype.scrollIntoView = () => undefined

afterEach(() => {
  cleanup()
})
