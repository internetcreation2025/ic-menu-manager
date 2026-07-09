# IC Menu Manager

Control what users can **see and access** in the WordPress admin — both the left **sidebar menu** and the top **admin bar**. Build reusable **Menu Groups** (block-lists) and assign them to individual users and/or whole roles.

Built by [Internet Creation](https://internetcreation.net).

## How it works

- Users keep their normal role (typically **Administrator**). A group then *subtracts* the areas they shouldn't touch.
- A **group** is a block-list: you tick the sidebar items and admin-bar nodes to remove. Anything unticked stays visible.
- Blocking is real: a blocked page is removed from the menu **and** direct-URL access is redirected away (not merely hidden).
- **Assignments:** a per-user assignment overrides the role-level assignment. Effective group = user → first matching role → none.

## Seeded "IC Client" group

On activation the plugin creates an **IC Client** group that blocks (items marked * only if that plugin is installed):

- **Sidebar:** Plugins · Settings · Appearance → Theme File Editor · Novamira* · HustleWP Pro* · ACF*
- **Admin bar:** WordPress logo (+ dropdown) · Updates icon · Novamira*

Plugin-specific items are resolved against the site's live menu, so a missing plugin is simply skipped. The seed is applied on the first wp-admin load after activation (when the live menu is available).

## Safety

Three layers stop you locking yourself out:

1. **No group assigned = fully unrestricted.** Never assign your own admin account a group.
2. **Kill-switch:** add `define( 'ICMM_SAFE_MODE', true );` to `wp-config.php` to disable all restrictions instantly.
3. A **restricted user cannot reach the Menu Manager page itself**, and assigning a group to the whole **Administrator** role shows a warning.

## Data

Stored via the Options API + user meta — no custom tables:

- `icmm_catalog` — live snapshot of the sidebar/admin-bar items (for the builder).
- `icmm_groups` — the groups.
- `icmm_role_groups` — role → group map.
- user meta `icmm_group` — per-user assignment.

Uninstall is conservative and keeps your data by default (see `uninstall.php`).

## Requirements

WordPress 6.0+, PHP 7.4+.
