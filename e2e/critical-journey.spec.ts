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

    await page.goto('/admin/shipping')
    await page.getByRole('textbox', { name: /^name/i }).fill('Courier')
    await page.getByRole('spinbutton', { name: /^amount/i }).fill('5.00')
    await page.getByRole('combobox', { name: 'Tax (optional)' }).click()
    await page.getByRole('option', { name: 'Standard tax (10%)' }).click()
    await page.getByRole('button', { name: 'Add', exact: true }).click()
    await expect(page.getByRole('cell', { name: 'Courier' })).toBeVisible()

    await page.goto('/admin/products')
    await page.getByRole('button', { name: 'Add product' }).click()
    await page.getByRole('textbox', { name: /^Name/i }).fill('All-weather lantern')
    await page.getByRole('textbox', { name: /^SKU/i }).fill('E2E-LANTERN-15')
    await page.getByRole('textbox', { name: /^Description/i }).fill('A deterministic browser-test product.')
    await page.getByRole('spinbutton', { name: /^Price excluding tax/i }).fill('20.00')
    await page.getByRole('spinbutton', { name: /^Weight \(kg\)/i }).fill('1.25')
    await page.getByRole('spinbutton', { name: /^Initial stock on hand/i }).fill('5')
    await page.getByRole('combobox', { name: 'Category (optional)' }).click()
    await page.getByRole('option', { name: 'Trail gear' }).click()
    await page.getByRole('combobox', { name: 'Tax (optional)' }).click()
    await page.getByRole('option', { name: 'Standard tax (10%)' }).click()
    await page.getByRole('button', { name: 'Create product' }).click()
    await expect(page.getByRole('cell', { name: /All-weather lantern/ })).toBeVisible()

    const productRow = page.getByRole('row', { name: /All-weather lantern/ })
    await productRow.getByRole('button', { name: 'Edit' }).click()
    await page.getByRole('textbox', { name: 'Description' }).fill('An updated deterministic browser-test product.')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expect(page.getByRole('heading', { name: 'Edit product' })).not.toBeVisible()

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

  test('the administrator can delete a product', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email address').fill(administrator.email)
    await page.getByLabel('Password').fill(administrator.password)
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).toHaveURL(/\/admin\/orders$/)
    await page.goto('/admin/products')

    const productRow = page.getByRole('row', { name: /All-weather lantern/ })
    await expect(productRow).toBeVisible()
    await productRow.getByRole('button', { name: 'Delete' }).click()
    await expect(page.getByRole('heading', { name: 'Delete product?' })).toBeVisible()
    await page.getByRole('button', { name: 'Delete product' }).click()
    await expect(productRow).not.toBeVisible()
  })
})
