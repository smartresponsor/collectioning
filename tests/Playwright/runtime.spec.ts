import { expect, test } from '@playwright/test';

test('standalone Collectioning runtime boots through Symfony', async ({ request }) => {
  const response = await request.get('/');

  expect(response.status()).toBe(404);
});
