#!/usr/bin/env node
/**
 * Regenerates src/lib/api/schema.d.ts from the backend OpenAPI document.
 *
 *   npm run api:types                       # from http://localhost:8080/api/doc.json
 *   npm run api:types -- http://host/api/doc.json
 *   npm run api:types -- ../backend/openapi.json
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import openapiTS, { astToString } from 'openapi-typescript'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const source = process.argv[2] ?? 'http://localhost:8080/api/doc.json'
const target = join(root, 'src', 'lib', 'api', 'schema.d.ts')

const input = /^https?:/.test(source)
  ? new URL(source)
  : existsSync(source)
    ? pathToFileURL(resolve(source))
    : null
if (!input) {
  console.error(`OpenAPI source not found: ${source}`)
  process.exit(1)
}

const ast = await openapiTS(
  input.protocol === 'file:' ? JSON.parse(readFileSync(input, 'utf8')) : input,
)
const banner = `/**\n * Generated from the Symfony OpenAPI document by scripts/api-types.mjs.\n * Do not edit by hand: run \`npm run api:types\`.\n */\n\n`
writeFileSync(target, banner + astToString(ast), 'utf8')
console.log(`Wrote ${target}`)
