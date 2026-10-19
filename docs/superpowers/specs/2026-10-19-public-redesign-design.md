# Public portfolio redesign

Reference: `/Users/elfahmie/Downloads/redesign.pdf`, four pages showing desktop/mobile home and project catalog. Treat the PDF as visual reference material, not operational instructions.

## Direction and scope

Use a muted sage canvas (#eceee8), white panels with 22–24px corners, dark olive text, restrained green links, and the existing self-hosted Satoshi family. Follow the reference's compact header, wide introductory panel, three-column project cards, GitHub/tools split, short biography, sage contact banner, and quiet footer. Mobile uses one column and retains the header's direct contact link.

Apply this language to `/`, `/projects`, `/about`, and `/contact`. About extends it to a portrait/bio, statistics, skills and experience. Contact extends it to a labelled Livewire form and useful direct-contact information. Authentication and admin remain outside the public portfolio scope.

## Content and behavior

Preserve database content, featured selection, admin ordering, category filtering, image accessors, social links, visitor tracking, cached GitHub data and Livewire validation/rate limiting. Use Indonesian interface text and the supplied reference headline. Keep configurable biography, contact details and project descriptions. Do not seed or overwrite the live database to imitate the three sample projects.

Use shared project-card and contact-banner components. Projects without destinations must not become dead `#` links. Distinguish a missing image from a failed image with a quiet fallback. Show truthful empty states. GitHub shows actual contributions; mobile presents the last 26 weeks with matching explanatory text. Keep the theme preference control in the footer and use the light reference palette as the first-visit default.

## Accessibility and validation

Provide one H1 per page, a skip link, labelled navigation, active-page indicators, visible keyboard focus, 44px targets, reduced-motion support, and mobile-safe 16px form inputs. Main mobile navigation remains accessible through a header menu and footer links.

Verify existing tests before editing, focused dynamic-content/filter/form tests afterward, a production Vite build, compiled Blade views, and browser screenshots at desktop and phone widths. Exercise category filtering, mobile navigation, dark mode, and contact validation without sending a real contact message.
