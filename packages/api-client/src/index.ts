import createClient from 'openapi-fetch'

import type { paths } from './generated'

export type { components, operations, paths } from './generated'

export const commerceApi = createClient<paths>({
  baseUrl: '/api/v1',
  credentials: 'include',
})
