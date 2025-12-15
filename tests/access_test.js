const puppeteer = require('puppeteer');

(async () => {
    // Launch browser
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    // Test Configuration
    const COURSE_URL = 'http://localhost:8080/courses/test-course-ui/'; // The course ID 16 created via UI
    const LOGIN_URL = 'http://localhost:8080/my-account/'; // Using my-account for login/logout

    // Test Users
    const USER_WITH_ACCESS = { user: 'testuser', pass: 'TestPass123' };
    const USER_WITHOUT_ACCESS = { user: 'noaccessuser', pass: 'NoAccess123' };

    try {
        console.log('--- Starting Access Control E2E Test ---');

        // --- SCENARIO 1: User WITHOUT Access ---
        console.log('\n[Scenario 1] Testing User WITHOUT Access (noaccessuser)...');
        let page = await browser.newPage();

        // Log in
        console.log('Logging in as noaccessuser...');
        await page.goto(LOGIN_URL);
        await page.type('#username', USER_WITHOUT_ACCESS.user);
        await page.type('#password', USER_WITHOUT_ACCESS.pass);
        await Promise.all([
            page.click('button[name="login"]'),
            page.waitForNavigation()
        ]);

        // Attempt to access course
        console.log(`Navigating to course: ${COURSE_URL}`);
        await page.goto(COURSE_URL);

        // Verification: Should be redirected to the product page
        const currentUrl = page.url();
        console.log(`Current URL after navigation: ${currentUrl}`);

        if (currentUrl.includes('/product/')) {
            console.log('[PASS] noaccessuser redirected to product page.');
        } else {
            // Debug content if not redirected
            const content = await page.content();
            if (content.includes('Welcome to the Sample Course')) {
                throw new Error('[FAIL] noaccessuser accessed the course content!');
            }
            console.log('Page content preview:', content.substring(0, 200));
            throw new Error(`[FAIL] noaccessuser was not redirected to product page. Current URL: ${currentUrl}`);
        }
        await page.close();


        // --- SCENARIO 2: User WITH Access ---
        console.log('\n[Scenario 2] Testing User WITH Access (testuser)...');
        page = await browser.newPage();

        // Log in (fresh page/session context needed if cookies persist, but newPage shares context)
        // Better to clearer cookies or logout. Using incognito context or similar is cleaner,
        // but for now let's just logout.
        console.log('Logging in as testuser...');
        // First logout logic if session shared? Puppeteer pages share browser context.
        await page.goto(LOGIN_URL);

        // Check if logged in and logout
        const logoutLink = await page.$('a[href*="logout"]');
        if (logoutLink) {
            console.log('Logging out previous user...');
            await Promise.all([
                logoutLink.click(),
                page.waitForNavigation()
            ]);
        }

        // Now Login
        await page.goto(LOGIN_URL);
        await page.type('#username', USER_WITH_ACCESS.user);
        await page.type('#password', USER_WITH_ACCESS.pass);
        await Promise.all([
            page.click('button[name="login"]'),
            page.waitForNavigation()
        ]);

        // Attempt to access course
        console.log(`Navigating to course: ${COURSE_URL}`);
        await page.goto(COURSE_URL);

        // Verification: SHOULD see "Welcome to the Sample Course"
        const contentAccess = await page.content();
        if (contentAccess.includes('Welcome to the Sample Course')) {
            console.log('[PASS] testuser successfully accessed course content.');
        } else {
            // Debug failure
            console.log('Page content preview:', contentAccess.substring(0, 500));
            throw new Error('[FAIL] testuser could NOT access the course content!');
        }
        await page.close();

        console.log('\n--- All Access Tests Passed ---');

    } catch (error) {
        console.error('Test Failed:', error);
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
