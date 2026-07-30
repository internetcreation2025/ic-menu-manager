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

## Updates (fleet updates from GitHub via ICJD)

The plugin advertises new versions from this repo's **GitHub Releases** through
WordPress's standard update API — no PUC library (so it can't collide with the
fleet's `internetcreation` PUC 4.11), and it fails silently if GitHub is
unreachable. New releases appear as a normal plugin update in wp-admin, in
`wp plugin update`, and via the **Check for updates** link on the Plugins screen.

**Delivery on the fleet is handled by ICJD, not WordPress cron.** The
`icjd-site-policy` mu-plugin deliberately disables unattended WP auto-updates so
every update goes through ICJD's snapshot → apply → smoke-test → rollback
pipeline. This plugin does **not** try to force WP-cron auto-update (ICJD vetoes
that by design); it simply exposes the release so ICJD's apply pass rolls it out
safely to all sites. Cutting a GitHub release is the fleet-wide trigger.

**To ship an update to the whole fleet:**

1. Bump the `Version:` header in `ic-menu-manager.php`.
2. Commit and push to `main`.
3. Build the zip and cut a release whose **tag matches the version**, attaching the zip:
   ```bash
   cd "IC SaaS Apps"
   zip -r -X /tmp/ic-menu-manager.zip ic-menu-manager \
     -x "ic-menu-manager/.git/*" "*/.DS_Store" "ic-menu-manager/project_specs.md"
   gh release create v1.1.0 /tmp/ic-menu-manager.zip \
     -R internetcreation2025/ic-menu-manager -t "v1.1.0" -n "Release notes…"
   ```

Sites running an older version will show/apply the new release through the
standard update path (ICJD's apply pass, or a one-click update in wp-admin). The
updater compares the release **tag** (a leading `v` is stripped) against the
running version with `version_compare`. Nothing rolls out until you publish a
release.

If you ever need a site to self-update via WP-cron independently of ICJD, that's
an ICJD-policy change (exempt this plugin in `icjd-site-policy`), not a change
here — this plugin intentionally respects the fleet update governance.

Repo is public, so no token is needed. If it is ever made private, define
`ICMM_UPDATER_GITHUB_TOKEN` in `wp-config.php` on each site.

## Requirements

WordPress 6.0+, PHP 7.4+.
