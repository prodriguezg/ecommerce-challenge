import { describe, expect, it } from 'vitest'
import { pollingDelay } from './OrdersPage'

describe('payment-status polling cadence', () => {
  it('polls every two seconds for the first thirty seconds', () => {
    expect(pollingDelay(0)).toBe(2_000)
    expect(pollingDelay(29_999)).toBe(2_000)
  })

  it('slows to five seconds at thirty seconds', () => {
    expect(pollingDelay(30_000)).toBe(5_000)
    expect(pollingDelay(120_000)).toBe(5_000)
  })
})
