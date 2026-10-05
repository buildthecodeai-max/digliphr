# UI and Design Guidelines

## Design direction

EMS uses a calm enterprise SaaS interface: navy surfaces, a selectable accent color, white or dark elevated cards, restrained shadows, clear page hierarchy, and readable data tables.

## Theme behavior

The appearance picker supports light, dark, and system modes plus teal, navy, forest, and indigo accents. The selected accent should control:

- Sidebar background and active navigation
- Primary buttons and top-bar actions
- Links, tabs, filters, and progress indicators
- Form focus rings
- Chat highlights and active states
- Charts and data visualization accents

Brand artwork may retain its own logo colors; functional UI controls must follow the selected appearance.

## Page structure

Use this order on new pages:

1. Workspace breadcrumb or section label
2. Page title and one-line description
3. Primary action and secondary actions
4. Filters or saved views
5. Main card, table, or workflow content
6. Contextual empty state or pagination

## Components

### Buttons

- Primary: one important action per section
- Secondary: navigation, filters, import, export, and neutral actions
- Danger: delete, reject, revoke, or irreversible actions

Do not use multiple unrelated colors for ordinary actions. Keep button text and icons high contrast.

### Spacing

Use the shared spacing scale: 4, 8, 12, 16, 20, 24, and 32 pixels. Keep card padding and page gutters consistent between workspaces.

### Tables

Use clear column headers, sticky headers for long lists, status badges, row actions at the end, and a useful empty state. On small screens, preserve readable column widths with horizontal scrolling or convert rows to stacked cards.

### Forms

Group related fields, mark required fields, validate inline, explain payroll/attendance fields, and show errors next to the relevant input. Do not rely on color alone to communicate an error.

### Empty states

Explain what is missing and provide the next useful action. Example: “No attendance records yet. Add a manual entry or wait for employee check-ins.”

## Accessibility

- Use semantic labels and headings.
- Provide visible keyboard focus.
- Maintain readable contrast in light and dark modes.
- Give icon-only buttons accessible labels and tooltips.
- Respect `prefers-reduced-motion`.
- Keep touch targets at least 40–44 pixels where practical.
