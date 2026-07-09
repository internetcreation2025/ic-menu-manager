# IC Menu Manager — Project Specs

## What it does & who uses it
A WordPress admin plugin that lets a site administrator **remove access** to parts of wp-admin for chosen users, in two places:
1. The **wp-admin left sidebar menu** (Posts, Pages, Plugins, Settings, submenus…)
2. The **top admin bar** (WordPress logo, updates icon, plugin nodes, etc.)

The admin builds reusable **Menu Groups** — a named **block-list** of items to hide **and block**. The admin then assigns a group to **individual users and/or whole roles**. A user's own assignment overrides their role's.

Model: client users are given the full **Administrator** role (so they can do their job), then a group is assigned that *subtracts* the areas they shouldn't touch. Ticked = removed from the menu **and** access-blocked.

**Users:** site administrators. **Affected:** any logged-in user who has a group assigned (including admin-role users).

## Tech stack
- WordPress 7.0 / PHP 8.1 plugin — no framework, no custom DB tables.
- Storage: WordPress **Options API** (catalog + groups + role assignments) and **user meta** (per-user assignment).
- UI: single top-level admin page, native WordPress admin styling + light vanilla JS (no build step).
- Dev/test site: https://malcolmduffin.temp-dns.com (Novamira). Dev loop = build locally → zip → upload → `wp plugin install --force` → activate.
- Releases: GitHub `internetcreation2025/ic-menu-manager`.

## The "catalog" (how we know what items exist)
On every wp-admin load the plugin captures the live `$menu`, `$submenu`, and admin-bar nodes into option `icmm_catalog` (cheap overwrite). The builder UI reads this catalog, so the checklist always reflects the site's real menus (including plugin-added items). Items are identified by:
- Sidebar: WordPress **menu slug** (`plugins.php`, `options-general.php`, `theme-editor.php`, or plugin slugs like `edit.php?post_type=acf-field-group`).
- Admin bar: **node id** (`wp-logo`, `updates`, …).

## Pages & flows (wp-admin, admin-only)
Top-level menu **Menu Manager** (dashicons-menu-alt), two tabs:

**1. Groups**
- List of groups (name, # items blocked, # users/roles assigned).
- Add / Edit → the **builder**:
  - Group name.
  - Panel A — **Admin Sidebar**: every top-level item + nested submenus as checkboxes. **Tick = block/hide.** "Select all/none" per top-level. Ticking a top-level cascades to block its submenus.
  - Panel B — **Admin Bar**: flat checkbox list of admin-bar nodes. Tick = block/hide.
  - Save / Delete.

**2. Assignments**
- **Roles** table: each role → group dropdown ("— None —" default). Warning shown when assigning a group to *Administrator*.
- **Users** table: search a user → group dropdown. Per-user assignment overrides role.

## Data models
- `option: icmm_catalog` → live `{ menu:[…], submenu:{…}, adminbar:[…] }` snapshot for the builder.
- `option: icmm_groups` → `{ group_id: { name, block_menu:[slug…], block_submenu:{parent:[slug…]}, block_adminbar:[node_id…] } }`
- `option: icmm_role_groups` → `{ role_key: group_id }`
- `user meta: icmm_group` → `group_id` (per-user override)

## Runtime behaviour — hide **and** block
- `admin_menu` (pri 999): for the current user's effective group, `remove_menu_page()` / `remove_submenu_page()` every blocked item. Blocking a top-level also removes its submenus.
- `admin_init` (pri 1) **access guard**: compute the requested admin page (core `$pagenow`, else `?page=` slug). If it's blocked → redirect to Dashboard (or profile.php if Dashboard is also blocked) with a "no access" notice. This is what makes blocking real — a pasted URL is stopped, not just hidden.
- `admin_bar_menu` (pri 999): `remove_node()` for every blocked admin-bar node.
- **Effective group:** per-user assignment > first matching role assignment > none.

## Seeded group — "IC Client" (created on activation)
A ready-made group blocking (each "if present" item resolved against the live catalog by title, skipped if absent):

**Sidebar:** Plugins (`plugins.php`), Settings (`options-general.php` + submenus), Appearance → Theme File Editor (`theme-editor.php`), Novamira*, HustleWP Pro*, ACF*.
**Admin bar:** WordPress logo + its dropdown (`wp-logo`), Updates icon (`updates`), Novamira*.

*Resolved by title match against the catalog; if the plugin isn't installed the item is simply omitted. Because resolution needs the live catalog, the seed is applied/repaired on the first admin load after activation.

## Safety (so nobody gets locked out permanently)
- **No group assigned = fully unrestricted.** The master admin simply never assigns themselves a group.
- **Kill-switch:** `define('ICMM_SAFE_MODE', true);` in `wp-config.php` disables all restrictions for everyone — documented escape hatch.
- The **Menu Manager page** is only shown/reachable to users **without** an active group (i.e. unrestricted admins); a restricted client can't reach the plugin's own settings.
- Assigning a group to the **Administrator role** shows a clear warning (it would restrict *every* admin, including you).
- Uninstall leaves data in place (no automatic destructive cleanup in v1).

## "Done" looks like
- Installs/activates cleanly on the dev site (no PHP 8.1 errors).
- "IC Client" group exists on activation with the correct items blocked (resolved from the live catalog).
- Admin can create/edit groups and assign them to a role and a user.
- Logged in as a test user with IC Client: Plugins/Settings/Theme File Editor/Novamira/HustleWP/ACF are gone from the sidebar **and** return "no access" if their URLs are pasted; the WP logo, updates icon and Novamira node are gone from the admin bar. The master admin is unaffected.
- Verified by loading wp-admin as that test user (temporary admin-access link) and confirming both the menus and the URL blocks.

## Out of scope for v1
- Front-end (public site) navigation menus — wp-admin only.
- Multisite network admin.
- Import/export of groups.
