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
Top-level menu **Menu Manager** (dashicons-menu-alt), three tabs:

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

**3. Menu Order** (added v1.2.0)
- A drag-and-drop (plus ↑/↓ buttons) list of the **top-level** sidebar items from the live catalog.
- Sets **one global order** applied site-wide to everyone. Save Order / Reset to default.
- Composes with groups: items a group hides simply don't appear; newly-installed plugins are appended at the end until moved. Submenu order is left to WordPress (out of scope for v1.2.0).

## Data models
- `option: icmm_catalog` → live `{ menu:[…], submenu:{…}, adminbar:[…] }` snapshot for the builder.
- `option: icmm_groups` → `{ group_id: { name, block_menu:[slug…], block_submenu:{parent:[slug…]}, block_adminbar:[node_id…] } }`
- `option: icmm_role_groups` → `{ role_key: group_id }`
- `user meta: icmm_group` → `group_id` (per-user override)
- `option: icmm_menu_order` → `[ slug, … ]` global top-level sidebar order (v1.2.0)

## Runtime behaviour — hide **and** block
- `admin_menu` (pri 999): for the current user's effective group, `remove_menu_page()` / `remove_submenu_page()` every blocked item. Blocking a top-level also removes its submenus.
- `admin_init` (pri 1) **access guard**: compute the requested admin page (core `$pagenow`, else `?page=` slug). If it's blocked → redirect to Dashboard (or profile.php if Dashboard is also blocked) with a "no access" notice. This is what makes blocking real — a pasted URL is stopped, not just hidden.
- `admin_bar_menu` (pri 999): `remove_node()` for every blocked admin-bar node.
- **Effective group:** per-user assignment > first matching role assignment > none.
- **Menu order (v1.2.0):** `custom_menu_order` returns true only when a saved order exists; `menu_order` reorders top-level slugs — saved order first (those still present), then separators / new items in their original order. Applies to everyone; whitelisted against the live catalog on save so only real slugs persist.

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

## Client Dashboard + quiet admin (v1.3.0, APPROVED 22 Oct 2026)

### What it does & who uses it
Gives restricted clients a clean, useful wp-admin. Both parts are switched on **per group** (so a client group gets them; IC's own unrestricted logins never do):
1. **Hide admin notices:** the banners that plugins and WordPress add (update nags, "rate us", licence reminders, upsells) are hidden for users in that group, on every wp-admin screen.
2. **Shortcut dashboard:** the default Dashboard (widgets, Welcome panel) is replaced with a greeting header and a grid of tiles, one per **main sidebar section** the admin has ticked for that group.

### Pages & flows
**Group builder, new panel C "Dashboard"** under the Sidebar and Admin Bar panels:
- Tickbox: **Hide admin notices** for this group.
- Tickbox: **Use the shortcut dashboard** for this group.
- **Dashboard tiles:** every top-level sidebar section from the catalog (except Dashboard itself) with a tickbox. Ticked = shown as a tile. A section ticked as *blocked* in panel A has its tile tickbox disabled, since the client can't reach it.

**What the client sees on Dashboard:** greeting ("Good morning, Angela") with the site name and a "View website" button, then the tiles. Each tile uses the section's own sidebar icon and name and links to the same place the sidebar does. Tiles appear in the site's Menu Order. Native WordPress admin look, cards with subtle hover lift, responsive to phone width. No emoji, no gradients.

### Data model
Three new keys on each group in `icmm_groups` (existing keys untouched):
```
hide_notices:    bool
dashboard:       bool
dashboard_tiles: [ top-level menu slug, ... ]
```
`ICMM_Groups::normalise()` carries them (default off / empty), so v1.2.0 groups behave exactly as before.

### Runtime behaviour
New class `includes/class-icmm-dashboard.php`, acting only when the current user's effective group has the feature on (respects `ICMM_SAFE_MODE` and "no group = unrestricted").
- **Notices:** at `in_admin_header` (runs just before notices print) remove every callback on `admin_notices`, `all_admin_notices`, `user_admin_notices`, `network_admin_notices`, then re-add ICMM's own "no access" notice. On the Dashboard a CSS rule also hides notices injected later by JavaScript.
- **Kept:** feedback WordPress prints inside the page itself ("Post updated", "Moved to Trash", "Settings saved" on Settings screens). A plugin page that reports its own save result through the notices hook will lose that message; accepted trade-off.
- **Dashboard:** on `load-index.php` (plain Dashboard only, not `index.php?page=`), render the tile page between the standard admin header and footer and stop, so no widget code runs. Tiles are built from the live `$menu`/`$submenu` (already pruned by the group and sorted by Menu Order), so a tile can never point at a blocked page.

### Redirect-loop fix (v1.3.0)
Port of the betterconnected live patch (30 Jul 2026): in `guard_access()`, never redirect a user onto the landing page itself (plain `index.php` or `profile.php`, no `?page=`). Fixes ERR_TOO_MANY_REDIRECTS when a group blocks both Dashboard and Profile, and stops the updater reverting the live hotfix.

### Third-party services
None.

### Rollout
1. Build + test on the dev site (malcolmduffin.temp-dns.com) with a test client user.
2. Mike approves, then release v1.3.0 on GitHub (fleet delivery via ICJD).
3. InvestFife: ICMM not installed (checked 22 Oct 2026). Install on **staging** first, build an "InvestFife Client" group, review with Mike; live only when Mike asks.

### "Done" looks like
- As a test client in a group with both tickboxes on: Dashboard shows only greeting + ticked tiles in Menu Order; no notice banners on Dashboard, Posts, Pages or a plugin page; saving a post still shows "Post updated".
- A tile for a section the group blocks never renders.
- IC's unrestricted admin sees the normal Dashboard and all notices.
- Saving a group keeps its dashboard settings; a v1.2.0 group with no new keys behaves exactly as before.
- Group blocking Dashboard + Profile no longer loops.
- No PHP warnings, no console errors; checked desktop and phone width.

## Out of scope for v1
- Front-end (public site) navigation menus — wp-admin only.
- Multisite network admin.
- Import/export of groups.
