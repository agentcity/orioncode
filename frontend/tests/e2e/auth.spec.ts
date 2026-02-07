import { test, expect } from '@playwright/test';

test.describe('Авторизация и Главная страница', () => {

    test('Пользователь может войти в систему', async ({ page }) => {
        await page.goto('http://localhost:3001/login');

        // 1. Ждем, пока исчезнет любой элемент с текстом "Loading" или индикатор
        // Если у тебя есть спиннер с классом .loading-spinner:
        // await page.waitForSelector('.loading-spinner', { state: 'hidden', timeout: 10000 });

        // Или просто ждем появления инпута (это и есть знак, что загрузка ушла)
        const emailInput = page.locator('input[type="email"]');
        await emailInput.waitFor({ state: 'visible', timeout: 15000 });

        await emailInput.fill('a@a.ru');
        await page.locator('input[type="password"]').fill('111111');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL('/dashboard', { timeout: 10000 });
    });

    test('Кнопка Logout работает корректно', async ({ page }) => {
        // Сначала логинимся (или используем куки)
        await page.goto('/login');
        const emailInput = page.locator('input[type="email"]');
        await emailInput.waitFor({ state: 'visible', timeout: 15000 });

        await emailInput.fill('a@a.ru');
        await page.locator('input[type="password"]').fill('111111');
        await page.click('button[type="submit"]');

        // Находим кнопку выхода
        const logoutBtn = page.locator('button').filter({ hasText: /Выйти|Logout/i })
            .or(page.getByRole('button', { name: /logout|выйти/i }))
            .or(page.locator('button[title*="ыйти"]')); // поиск по части title
        await logoutBtn.click();

        // ПРОВЕРКА: мы снова на странице логина (благодаря setUser(null))
        await expect(page).toHaveURL('/login');
    });
});
