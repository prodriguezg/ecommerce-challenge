import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('mobile filters remain keyboard operable and pass the automated WCAG smoke scan', async ({ page }) => {
  await page.goto('/')
  const filters = page.getByRole('button', { name: 'Filters', exact: true })
  await expect(filters).toBeVisible()

  await filters.focus()
  await expect(filters).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('heading', { name: 'Filters' })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('heading', { name: 'Filters' })).toBeHidden()

  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .analyze()
  expect(results.violations.filter(({ impact }) => impact === 'serious' || impact === 'critical')).toEqual([])
})
