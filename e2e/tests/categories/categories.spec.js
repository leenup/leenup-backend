// @ts-check
const { test, expect } = require('@playwright/test');

test('admin', async ({ page, browserName }) => {
  await page.goto('https://localhost/admin');
  await page.getByLabel('Create').click();
  await page.getByLabel('Title').fill('foo' + browserName);
  await page.getByLabel('Save').click();
  await expect(page).toHaveURL(/admin#\/categories$/);
  await page.getByText('foo' + browserName).first().click();
  await expect(page).toHaveURL(/show$/);
  await page.getByLabel('Edit').first().click();
  await page.getByLabel('Title').fill('bar' + browserName);
  await page.getByLabel('Save').click();
  await expect(page).toHaveURL(/admin#\/categories$/);
  await page.getByText('bar' + browserName).first().click();
});
