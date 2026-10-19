# Public portfolio redesign implementation plan

**Goal:** Apply the supplied PDF's visual language to all four public portfolio pages.

**Architecture:** Keep the Laravel controllers and data contracts. Use a stylesheet scoped to the public layout, reusable Blade project cards/contact banners, and the existing Livewire contact component. Keep admin styles isolated.

**Tech stack:** Laravel, Blade, Livewire 4, Tailwind 4, Vite, self-hosted Satoshi.

- [x] Establish baseline with `php artisan test --compact`; add behavior coverage for dynamic project cards, missing destinations, category totals, mobile navigation and preserved contact fields.
- [x] Replace `resources/views/components/layouts/frontend.blade.php`; introduce `resources/css/portfolio.css` imported by `app.css`. Keep theme switching in the footer, use native details for a mobile menu, and preserve visitor counting.
- [x] Add `resources/views/components/project-card.blade.php` and `contact-banner.blade.php`; rebuild `resources/views/frontend/home.blade.php` and `projects.blade.php` around the reference's sections. Keep database records and manual ordering intact.
- [x] Restyle `resources/views/components/github-heatmap.blade.php` with a full-year desktop grid and last-26-weeks phone grid, preserving cached fetch/failure handling.
- [x] Rebuild `resources/views/frontend/about.blade.php`, `contact.blade.php`, and `resources/views/livewire/contact-form.blade.php`; retain settings, skills, experiences and Livewire form behavior.
- [x] Run `php artisan test --compact`, `npm run build`, `php artisan view:cache`, and `git diff --check`. Inspect all routes in the browser at 1280px and 390px; check 320px overflow, category navigation, mobile menu, theme and invalid form submission. Fix material issues and record results.

## Verification results

- Baseline: 21 tests, 80 assertions passed before changes.
- Final: 26 tests, 160 assertions passed; Vite production build, Blade view compilation and diff whitespace check succeeded. Font URLs produce Vite runtime-resolution warnings, and the existing self-hosted files were confirmed available over HTTP and loaded in the browser.
- Browser: all four pages at 320, 768 and 1440px have one H1 and no horizontal overflow; screenshots reviewed at 1280px desktop and 390px phone widths.
- Web App category filter shows two of six records and the correct active chip. Mobile menu opens/closes with Escape. Theme persists across routes. GitHub displays the configured username, real cached total, and 26 recent weeks on mobile.
- Browser contact tests checked native validation and four Livewire field errors in Indonesian; no valid message was submitted to the local database. Success/reset behavior was tested in the isolated PHPUnit database.
- Independent source review found two biography data-binding regressions; both were restored and covered with assertions.
- Database content, featured project selection, manual order, admin screens and authentication remain unchanged.
