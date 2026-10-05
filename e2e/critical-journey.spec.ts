import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const administrator = {
  email: 'e2e-admin@example.test',
  name: 'E2E Administrator',
  password: 'Synthetic-Admin-15!',
}

async function seriousAccessibilityViolations(page: Page) {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .analyze()

  return results.violations.filter(({ impact }) => impact === 'serious' || impact === 'critical')
}

test.describe.serial('critical commerce journey', () => {
  test.setTimeout(60_000)

  test('first-run setup closes and an administrator creates a sellable catalog', async ({ page }) => {
    await page.goto('/setup')
    await page.getByLabel('Administrator name').fill(administrator.name)
    await page.getByLabel('Administrator email').fill(administrator.email)
    await page.getByLabel('Password').fill(administrator.password)
    await page.getByRole('button', { name: 'Create administrator' }).click()
    await expect(page).toHaveURL(/\/admin\/orders$/)

    await page.goto('/admin/categories')
    await page.getByRole('textbox', { name: /^name/i }).fill('Trail gear')
    await page.getByRole('textbox', { name: /^slug/i }).fill('trail-gear')
    await page.getByRole('button', { name: 'Add', exact: true }).click()
    await expect(page.getByRole('cell', { name: 'Trail gear' })).toBeVisible()

    await page.goto('/admin/taxes')
    await page.getByRole('textbox', { name: /^name/i }).fill('Standard tax')
    await page.getByRole('spinbutton', { name: /^rate/i }).fill('10')
    await page.getByRole('button', { name: 'Add', exact: true }).click()
    await expect(page.getByRole('cell', { name: 'Standard tax' })).toBeVisible()

    const taxId = await page.evaluate(async () => {
      const response = await fetch('/api/v1/admin/taxes', { credentials: 'include' })
      const taxes = await response.json() as Array<{ id: string; name: string }>
      return taxes.find(({ name }) => name === 'Standard tax')?.id
    })
    expect(taxId).toBeTruthy()

    await page.goto('/admin/shipping')
    await page.getByRole('textbox', { name: /^name/i }).fill('Courier')
    await page.getByRole('spinbutton', { name: /^amount/i }).fill('5.00')
    await page.getByRole('textbox', { name: /^tax id/i }).fill(taxId!)
    await page.getByRole('button', { name: 'Add', exact: true }).click()
    await expect(page.getByRole('cell', { name: 'Courier' })).toBeVisible()

    const categoryId = await page.evaluate(async () => {
      const response = await fetch('/api/v1/admin/categories', { credentials: 'include' })
      const categories = await response.json() as Array<{ id: string; name: string }>
      return categories.find(({ name }) => name === 'Trail gear')?.id
    })

    await page.goto('/admin/products')
    await page.getByRole('button', { name: 'Add product' }).click()
    await page.getByRole('textbox', { name: /^Name/i }).fill('All-weather lantern')
    await page.getByRole('textbox', { name: /^SKU/i }).fill('E2E-LANTERN-15')
    await page.getByRole('textbox', { name: /^Description/i }).fill('A deterministic browser-test product.')
    await page.getByRole('spinbutton', { name: /^Price excluding tax/i }).fill('20.00')
    await page.getByRole('spinbutton', { name: /^Initial stock on hand/i }).fill('5')
    await page.getByRole('textbox', { name: /^Category ID/i }).fill(categoryId!)
    await page.getByRole('textbox', { name: /^Tax ID/i }).fill(taxId!)
    await page.getByRole('button', { name: 'Create product' }).click()
    await expect(page.getByRole('cell', { name: /All-weather lantern/ })).toBeVisible()

    await page.getByRole('button', { name: 'Log out' }).click()
    await expect(page).toHaveURL(/\/login$/)
    await page.goto('/setup')
    await expect(page).toHaveURL(/\/login$/)
  })

  test('a guest searches, checks out, and sees the payment complete', async ({ page }) => {
    await page.goto('/')
    await page.getByLabel('Search products, SKUs, or categories').fill('lantern')
    await expect(page.getByText('All-weather lantern', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Add to cart' }).click()
    await page.getByRole('link', { name: /Cart/ }).click()
    await page.getByRole('link', { name: 'Review checkout' }).click()

    await page.getByLabel('Email address').fill('guest-e2e@example.test')
    await page.getByLabel('Recipient name').fill('Guest E2E')
    await page.getByLabel('Address line 1').fill('15 Test Avenue')
    await page.getByLabel('City').fill('Montevideo')
    await page.getByLabel('State or region').fill('Montevideo')
    await page.getByLabel('Postal code').fill('11000')
    await page.getByLabel('Country code').fill('UY')
    await page.getByLabel('Phone').fill('+598 0000 0015')
    await page.getByLabel('Test card number').fill('4000000000010001')
    await page.getByRole('button', { name: 'Place order (simulated)' }).click()

    await expect(page).toHaveURL(/\/orders\//)
    await expect(page.getByRole('heading', { name: /^Order ORD-/ })).toBeVisible()
    await expect(page.getByText('paid', { exact: true }).first()).toBeVisible({ timeout: 15_000 })
  })

  test('the storefront has no serious or critical automated WCAG violations', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByText('All-weather lantern', { exact: true })).toBeVisible()
    expect(await seriousAccessibilityViolations(page)).toEqual([])
  })
})
