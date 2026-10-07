import { readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'

import { parse } from 'yaml'

const commerceInventory = {
  '/health/live': ['get'],
  '/health/ready': ['get'],
  '/setup/status': ['get'],
  '/setup/admin': ['post'],
  '/products': ['get'],
  '/products/{product}': ['get'],
  '/products/{product}/image': ['get'],
  '/categories': ['get'],
  '/shipping-methods': ['get'],
  '/auth/login': ['post'],
  '/auth/logout': ['post'],
  '/auth/me': ['get'],
  '/customers/register': ['post'],
  '/guest-orders/{order}/register': ['post'],
  '/cart': ['get'],
  '/cart/items/{product}': ['put', 'delete'],
  '/cart/shipping-method': ['put'],
  '/cart/merge': ['post'],
  '/cart/quote': ['post'],
  '/checkouts': ['post'],
  '/orders': ['get'],
  '/orders/{order}': ['get'],
  '/orders/{order}/status': ['get'],
  '/guest-orders/{order}': ['get'],
  '/guest-orders/{order}/status': ['get'],
  '/payments/webhooks/mock': ['post'],
  '/admin/products': ['get', 'post'],
  '/admin/products/{product}': ['get', 'put', 'delete'],
  '/admin/products/{product}/image': ['post', 'delete'],
  '/admin/categories': ['get', 'post'],
  '/admin/categories/{category}': ['get', 'put', 'delete'],
  '/admin/taxes': ['get', 'post'],
  '/admin/taxes/{tax}': ['get', 'put', 'delete'],
  '/admin/shipping-methods': ['get', 'post'],
  '/admin/shipping-methods/{shippingMethod}': ['get', 'put', 'delete'],
  '/admin/products/{product}/inventory': ['get'],
  '/admin/products/{product}/inventory-adjustments': ['get', 'post'],
  '/admin/product-imports': ['post'],
  '/admin/product-imports/{import}': ['get'],
  '/admin/product-imports/{import}/rejections.csv': ['get'],
  '/admin/orders': ['get'],
  '/admin/orders/{order}': ['get'],
  '/admin/manual-reviews': ['get'],
  '/admin/manual-reviews/{review}/resolve': ['post'],
  '/admin/settings': ['get'],
  '/admin/settings/{key}': ['put', 'delete'],
  '/admin/audit-logs': ['get'],
}

const paymentInventory = {
  '/health/live': ['get'],
  '/health/ready': ['get'],
  '/api/v1/payments': ['post'],
}

const httpMethods = new Set(['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'])
const mutationMethods = new Set(['post', 'put', 'patch'])

function validateDocumentationContentSecurityPolicy() {
  const documentation = readFileSync('services/commerce-api/public/api-docs.html', 'utf8')
  const nginxConfiguration = readFileSync('docker/frontend/nginx.conf', 'utf8')
  const requiredSources = [
    ...documentation.matchAll(/<(?:script|link)[^>]+(?:src|href)="(https:\/\/[^/"]+)/g),
  ].map((match) => match[1])

  requiredSources.push('https://fonts.gstatic.com', 'https://cdn.redoc.ly')

  for (const source of new Set(requiredSources)) {
    if (!nginxConfiguration.includes(source)) {
      throw new Error(`The API documentation CSP does not allow its required source: ${source}`)
    }
  }

  if (!nginxConfiguration.includes('worker-src blob:')) {
    throw new Error('The API documentation CSP does not allow the Redoc web worker.')
  }
}

function resolveReference(document, value) {
  let resolved = value
  const visited = new Set()

  while (resolved?.$ref) {
    if (!resolved.$ref.startsWith('#/') || visited.has(resolved.$ref)) {
      throw new Error(`Invalid reference chain: ${resolved.$ref}`)
    }
    visited.add(resolved.$ref)
    resolved = resolved.$ref
      .slice(2)
      .split('/')
      .reduce((current, segment) => current[segment.replaceAll('~1', '/').replaceAll('~0', '~')], document)
  }

  return resolved
}

function assertStrictObject(document, schema, context, visited = new Set()) {
  const resolved = resolveReference(document, schema)
  if (!resolved || visited.has(resolved)) {
    return
  }

  visited.add(resolved)

  if (resolved.type === 'object' && resolved.additionalProperties !== false) {
    throw new Error(`${context} must reject unknown properties`)
  }

  for (const child of Object.values(resolved.properties ?? {})) {
    assertStrictObject(document, child, context, visited)
  }

  if (resolved.items) {
    assertStrictObject(document, resolved.items, context, visited)
  }

  for (const branch of [...(resolved.allOf ?? []), ...(resolved.anyOf ?? []), ...(resolved.oneOf ?? [])]) {
    assertStrictObject(document, branch, context, visited)
  }
}

function normalizedInventory(entries) {
  return Object.fromEntries(
    Object.entries(entries)
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([path, methods]) => [path, [...methods].sort()]),
  )
}

function inventoryDifference(expected, actual) {
  const expectedEntries = normalizedInventory(expected)
  const actualEntries = normalizedInventory(actual)
  const keys = [...new Set([...Object.keys(expectedEntries), ...Object.keys(actualEntries)])].sort()
  return keys
    .filter((path) => JSON.stringify(expectedEntries[path]) !== JSON.stringify(actualEntries[path]))
    .map((path) => `${path}: documented=${JSON.stringify(expectedEntries[path] ?? [])}, routes=${JSON.stringify(actualEntries[path] ?? [])}`)
    .join('; ')
}

function assertRouteContract(file, service, documentedInventory) {
  const routes = JSON.parse(execFileSync('php', [`services/${service}/artisan`, 'route:list', '--json'], {
    encoding: 'utf8',
  }))
  const actual = {}

  for (const route of routes) {
    let path = `/${route.uri}`
    if (service === 'commerce-api') {
      if (!path.startsWith('/api/v1/')) continue
      path = path.slice('/api/v1'.length)
    } else if (!path.startsWith('/api/v1/') && !path.startsWith('/health/')) {
      continue
    }

    const methods = route.method.toLowerCase().split('|').filter((method) => method !== 'head')
    actual[path] = [...new Set([...(actual[path] ?? []), ...methods])]
  }

  if (JSON.stringify(normalizedInventory(actual)) !== JSON.stringify(normalizedInventory(documentedInventory))) {
    throw new Error(`${file} endpoint inventory differs from the live ${service} Laravel routes: ${inventoryDifference(documentedInventory, actual)}`)
  }
}

function validateDocument(file, expectedInventory, service) {
  const document = parse(readFileSync(file, 'utf8'))
  const actualInventory = Object.fromEntries(
    Object.entries(document.paths).map(([path, pathItem]) => [
      path,
      Object.keys(pathItem).filter((key) => httpMethods.has(key)).sort(),
    ]),
  )

  if (JSON.stringify(normalizedInventory(actualInventory)) !== JSON.stringify(normalizedInventory(expectedInventory))) {
    throw new Error(`${file} endpoint inventory differs from docs/api.md`)
  }

  assertRouteContract(file, service, actualInventory)

  const operationIds = new Set()
  for (const [path, pathItem] of Object.entries(document.paths)) {
    for (const [method, operation] of Object.entries(pathItem)) {
      if (!httpMethods.has(method)) {
        continue
      }

      if (!operation.operationId || operationIds.has(operation.operationId)) {
        throw new Error(`${method.toUpperCase()} ${path} must have a unique operationId`)
      }
      operationIds.add(operation.operationId)

      if (!Object.keys(operation.responses ?? {}).some((status) => /^2\d\d$/.test(status))) {
        throw new Error(`${method.toUpperCase()} ${path} must document a success response`)
      }

      for (const [status, responseReference] of Object.entries(operation.responses ?? {})) {
        if (!/^[45]\d\d$/.test(status)) {
          continue
        }

        const response = resolveReference(document, responseReference)
        if (!response?.content?.['application/problem+json']) {
          throw new Error(`${method.toUpperCase()} ${path} ${status} must use application/problem+json`)
        }
      }

      if (mutationMethods.has(method) && operation.requestBody) {
        const requestBody = resolveReference(document, operation.requestBody)
        for (const mediaType of Object.values(requestBody.content ?? {})) {
          assertStrictObject(document, mediaType.schema, `${method.toUpperCase()} ${path}`)
        }
      }
    }
  }
}

validateDocument('openapi/commerce-api.yaml', commerceInventory, 'commerce-api')
validateDocument('openapi/payment-api.yaml', paymentInventory, 'payment-api')
validateDocumentationContentSecurityPolicy()
console.log('OpenAPI inventories match Laravel routes; errors and mutation schemas are valid.')
