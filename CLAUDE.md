# TBT Search Table — Claude Code guidance

The VIP page's advanced search table (`[tbt_search_table]`), replacing wpDataTables table 185. Automatic rows from lessons and four tools, plus overrides, hidden rows and manual rows, plus a one-off import from table 185.

**Current release: 0.1.0 → `docs/SPEC-0.1.0.md`.** The approved look is `docs/prototypes/search-table.html` (the prototype wins on looks, the spec on behaviour). Phase 0 findings: `docs/PHASE-0.md`.

## Basics

- WordPress 6.x, PHP 8.0+. Pure PHP + vanilla JS, no build step, no jQuery.
- Prefix `tbtst` / `TBTST_`, CSS classes `tbtst-`, text domain `tbt-search-table`, REST namespace `tbt-search-table/v1` (admin only, `manage_options`).
- TBT Hub, Swipe, Matching Game, Drill, Test Maker and Lesson Builder are all optional. Check them when used (`class_exists` / `post_type_exists`), never at load. Read their data, never write it.
- Light only. Tokens come from Hub's `tbt-tokens` handle, else the bundled `assets/css/tbt-tokens.css`. Never redefine token values locally.

## Files

```
tbt-search-table.php                bootstrap, Hub item
includes/class-tbtst-format.php     pure helpers (no WordPress), tested
includes/class-tbtst-db.php         {prefix}tbtst_rows, install/upgrade
includes/class-tbtst-settings.php   Settings tab values and defaults
includes/class-tbtst-sources.php    automatic rows: lessons + Swipe, Matching Game, Drill, Test
includes/class-tbtst-rows.php       merge (auto + overrides + manual), transient cache, orphans
includes/class-tbtst-import.php     table 185 scan, import, undo
includes/class-tbtst-rest.php       admin routes
includes/class-tbtst-admin.php      admin screen shell + embedded JSON
includes/class-tbtst-shortcode.php  [tbt_search_table]
assets/js/table.js, assets/css/table.css   front
assets/js/admin.js, assets/css/admin.css   admin
tests/test-format.php               run: php tests/test-format.php
```

## Data contract: `{prefix}tbtst_rows`

One row per manual row, and one per automatic row that has an override or is hidden. An automatic row with no override and not hidden has **no** table row (`TBTST_DB::save_auto()` deletes it).

- `source`: `manual`, `lesson`, `swipe`, `mg`, `drill`, `test`. `source_id` is 0 for manual rows, otherwise the post, set, drill or test ID. `(source, source_id)` is unique for automatic rows, enforced in code (the index isn't UNIQUE because every manual row has `source_id` 0).
- `title`, `category`, `tags`, `level`: for automatic rows, NULL means "follow the source", and `level` `''` means explicitly no level. `category` is stored by **name**. `tags` are joined with `, `.
- `url`: manual rows only.
- `hidden`: rows with `hidden = 1` never reach the front.
- `import_ref` is table 185's `wdt_ID`, and `import_batch` is `gmdate('YmdHis')` of the import run. Undo deletes a batch's rows where `updated_at = created_at`.
- Row keys: `manual:{id}` or `{source}:{source_id}`. In REST paths the colon becomes a slash: `/rows/swipe/5`.

Schema version: `TBTST_DB_VERSION`. `maybe_upgrade()` runs on `plugins_loaded`, because FTP deploys never fire activation.

## Rules to keep

- Titles are decoded before caching; the front and admin insert every value as text (`textContent`), never as HTML.
- Embedded JSON goes through `TBTST_Shortcode::json()` (hex-escaped), so `esc_attr` can't alter it.
- `fold()` exists in PHP (`TBTST_Format`) and JS (`table.js`, `admin.js`). Keep them in step.
- Refreshing deletes orphaned overrides only for sources that were read and returned rows, so a deactivated plugin never wipes edits.

## Validation

```bash
for f in tbt-search-table.php includes/*.php; do php -l "$f"; done
node --check assets/js/table.js && node --check assets/js/admin.js
php tests/test-format.php
```
