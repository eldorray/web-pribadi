# Design System Strategy: The Editorial Architect

## 1. Overview & Creative North Star
The Creative North Star for this design system is **"The Editorial Architect."** 

Standard portfolios often feel like rigid templates—a series of disconnected boxes. This system rejects that "boxed-in" aesthetic in favor of high-end editorial layouts found in premium architectural journals and luxury digital lookbooks. We achieve this through **intentional asymmetry**, where large-scale typography acts as a structural element, and **layered tonal depth**, where the interface feels like physical sheets of fine-milled paper and frosted glass resting atop one another. 

By prioritizing "breathing room" (negative space) as a functional tool rather than a void, we communicate a brand personality that is both authoritative (trustworthy) and boundary-pushing (creative).

---

## 2. Colors: Tonal Atmosphere
This palette moves away from high-contrast black-and-white to a sophisticated "Off-White and Ink" spectrum. It uses the Material Design tokens to create a soft, natural environment where the vibrant primary blue acts as a surgical strike of intent.

### The "No-Line" Rule
**Strict Mandate:** Designers are prohibited from using 1px solid borders for sectioning or containment. 
Boundaries must be defined solely through:
- **Background Color Shifts:** A `surface-container-low` (#f6f3f2) section sitting on a `surface` (#fcf9f8) background.
- **Tonal Transitions:** Using the Spacing Scale (e.g., `16` or `20` units) to create "islands" of content that are defined by their isolation rather than their enclosure.

### Surface Hierarchy & Nesting
Treat the UI as a physical stack.
- **Base Layer:** `surface` (#fcf9f8).
- **Secondary Content/Cards:** `surface-container-lowest` (#ffffff) to provide a "lifted" feel against the warmer background.
- **Inset Details:** Use `surface-container-high` (#ebe7e7) for search bars or secondary UI elements that should feel "recessed" into the page.

### The "Glass & Gradient" Rule
To escape the "flat UI" trap, use **Glassmorphism** for navigation bars and floating action elements. Apply `surface` with 80% opacity and a `backdrop-blur` of 20px. 
For primary CTAs, do not use a flat hex. Apply a subtle linear gradient from `primary` (#003ec7) to `primary_container` (#0052ff) at a 135-degree angle to give the element "soul" and a tactile, curved appearance.

---

## 3. Typography: The Narrative Voice
Typography is the primary visual driver of this system. We pair the geometric authority of **Epilogue** (Headings) with the Swiss-style precision of **Inter** (Body).

*   **Display & Headlines (Epilogue):** Used for "Hero" moments. Use `display-lg` (3.5rem) with a negative letter-spacing of `-0.02em` to create a dense, high-fashion impact. These elements should often break the grid—try overlapping a headline over a photo edge.
*   **Body & Titles (Inter):** Focused on absolute legibility. Use `body-lg` (1rem) with a generous line-height (1.6) to ensure the "Editorial" feel persists in long-form text.
*   **Labels:** Use `label-md` in all-caps with `+0.05em` letter-spacing for category tags to denote professional detail-orientation.

---

## 4. Elevation & Depth: Tonal Layering
We do not use structural lines to define hierarchy. We use **Tonal Layering**.

### The Layering Principle
Depth is achieved by "stacking" the surface-container tiers. Place a `surface-container-lowest` card on a `surface-container-low` section. This creates a soft, natural lift that mimics heavy paper stock.

### Ambient Shadows
When a "floating" effect is required (e.g., a primary project card):
- **Blur:** 40px - 60px.
- **Opacity:** 4% - 8%.
- **Color:** Use a tinted version of `on_surface` (#1c1b1b) rather than pure black. This creates an "ambient occlusion" look rather than a dated drop shadow.

### The "Ghost Border" Fallback
If a border is required for accessibility (e.g., an input field), use a **Ghost Border**: The `outline_variant` token at **20% opacity**. 100% opaque borders are strictly forbidden as they interrupt the visual flow.

---

## 5. Components

### Buttons
- **Primary:** Gradient (`primary` to `primary_container`), `xl` (1.5rem) roundedness. No border. White text.
- **Secondary:** `surface_container_high` background with `on_surface` text. Feels integrated, not loud.
- **Tertiary:** Text only in `primary` color with a `2px` underline that appears only on hover.

### Cards & Lists
- **The Rule of Zero Dividers:** Never use a horizontal line to separate list items. Use spacing (`spacing-4` or `spacing-6`) and `surface` color shifts.
- **Project Cards:** Use `surface-container-lowest` with an `xl` corner radius. Images within cards should have a `md` radius to create a nested, professional look.

### Input Fields
- **Styling:** Use a `surface-container-low` fill. No bottom line or border. 
- **States:** On focus, transition the background to `surface-container-lowest` and add a 2px `primary` "Ghost Border" at 30% opacity.

### Navigation (The Floating Bar)
- Use a pill-shaped container with `full` roundedness. 
- Apply the **Glassmorphism** rule (80% opacity `surface` + backdrop-blur). 
- Center it at the bottom of the viewport for mobile or top-center for desktop to break the traditional "pinned to top" layout.

---

## 6. Do's and Don'ts

### Do:
- **Do** use asymmetrical margins. If the left margin is `20`, try a right margin of `24` for a custom, bespoke feel.
- **Do** use "oversized" whitespace. If you think there is enough space, add 20% more.
- **Do** use the `tertiary` (#952200) palette for "Human" moments—success messages, warm highlights, or creative callouts—to balance the coldness of the blue.

### Don't:
- **Don't** use 1px dividers. Ever.
- **Don't** use pure #000000 for text. Always use `on_surface` (#1c1b1b) to maintain the sophisticated tonal range.
- **Don't** crowd elements. If two components feel like they are touching, increase the spacing using the `spacing-8` (2.75rem) token or higher.
- **Don't** use sharp corners. Everything must adhere to the **Roundedness Scale** (defaulting to `0.5rem` or higher) to maintain the "friendly yet professional" personality.