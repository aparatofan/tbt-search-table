# TBT Search Table 0.1.0 — the VIP page's advanced search, without wpDataTables

The VIP page has an **Advanced search** table: title, category, tags, level and an **Open** link for every lesson and activity. Today it is wpDataTables table **185**, and Mariusz updates it by hand. Its admin form is broken: the category field is no longer a dropdown, so the table now has made-up categories, duplicates and rows that don't agree with each other.

**TBT Search Table** replaces it with a small plugin of our own. Most rows **fill in by themselves** from what is already on the site: lesson posts and the open activities in Swipe, Matching Game, Drill and Test Maker. Mariusz can **edit** any row, **hide** it, or **add rows by hand**. The category is always picked from a list. A one-off **import** brings over the table 185 rows that the site can't produce by itself. That's mainly several hundred **Multidecker** links, which point to an outside site Mariusz no longer owns, plus old PDF lessons. A **Missing CEF tag** list shows lessons that have no level yet.

The look is locked by the approved prototype `docs/prototypes/search-table.html` (Mariusz adds it to the repo). When this spec and the prototype disagree on looks, the prototype wins. When they disagree on behaviour, this spec wins. The spec adds a few things the prototype doesn't show: grouped import with "select all" (there are hundreds of Multidecker links), a **Settings** tab, **Refresh now** and **Undo last import**. Style them like the rest of the prototype's admin view.

Repo: `aparatofan/tbt-search-table` (empty, FTP secrets added). Prefix: `tbtst` / `TBTST_`. Text domain `tbt-search-table`.

## Depends on

| Plugin | Needed? | Used for |
|---|---|---|
| TBT Hub | optional | menu parent, Overview item, `tbt-tokens.css` (bundled fallback, as Flashcards does) |
| TBT Swipe 1.17.0 | optional | open decks |
| TBT Matching Game 0.9.1 | optional | published games |
| Translation Drill 1.9.0 | optional | open drills |
| TBT Test Maker 0.7.0 | optional | open tests |
| TBT Lesson Builder | optional | `_tbtlb_level` and `_tbtlb_type` meta on lessons |

Each source is checked when it is read (`class_exists` / `post_type_exists`). If a plugin is missing, its rows don't appear and nothing else breaks. This plugin reads other plugins' data **read-only**. It never writes to their tables.

## Decisions (locked)

| Topic | Decision |
|---|---|
| Rows | Two kinds. **Automatic** rows come from lessons and tools, are rebuilt from the source, and their link is always the source's own. **Manual** rows are typed in or imported, and Mariusz owns every field of them. |
| Columns (front) | Title · Category · Tags · Level · Link (**Open** pill). Filters: Title, Category (dropdown), Tags, Level (dropdown), plus **Search everything**, **Show N entries** (5/10/25/50/100, default 10), and sorting on Title, Category and Level. Default sort: Title A–Z. |
| Category list | WordPress categories plus five **tool names**: Swipe, Matching Game, Drill, Test, Multidecker. Automatic tool rows use their tool name. In every admin dropdown, tool names are listed first under *Activities (tools)*, then *WordPress categories*. On the front, the Category filter lists only categories that have at least one visible row, in two groups: *Lessons* and *Activities*. |
| Lesson rows | Published posts (`post`) with at least one category from the **Lesson categories** setting, and none from the **Never list** setting. The category shown is the post's lesson category. If a post has more than one, the child category wins over its parent, then alphabetical order. Tags: all the post's tags, sorted A–Z (case-insensitive). The link is the permalink. Handout and PDF lessons are ordinary posts, so they come in the same way. |
| Level | Lessons: from the tag `CEF A1`…`CEF C2` (case and spaces ignored), else `_tbtlb_level`, else empty. Tools: the tool's own level if it has one (Swipe `level`, Matching Game data `level`), else empty. Mariusz fills in missing tool levels by hand (an edit), and that's fine. |
| Tool rows | **Swipe:** decks with `deck_type` = `open` that are not drafts. Link: `TBTS_DB::deck_url( $set )`, and a row is skipped when that's `''`. **Matching Game:** published `tbt_match_game` posts (Phase 0 checks whether there's a class-only state to exclude). Link: permalink. **Drill:** published drills that anyone with the link can open (Phase 0 defines this). Link: `TBTD_URLs::drill_url( $slug )` (Phase 0 confirms the class name). **Test:** `status` published **and** `access` = `open`. Link: `TBTTM_URLs::test_url( $slug )`. Tool rows start with no tags. |
| Multidecker | Not a plugin on this site. Its links are **manual rows** from the import, with category **Multidecker**. |
| Edits | Editing an automatic row stores an **override** for title, category, tags and level. A field with no override follows the source. **Reset** removes all overrides on that row. The link of an automatic row can't be edited. |
| Hide | Any row can be hidden. Hidden rows never reach the front. In admin they show greyed with a **Hidden** flag. |
| Delete | Manual rows only, with a confirm dialog built into the page. Automatic rows are hidden, never deleted. |
| Access | Front: whatever protects the page the shortcode sits on (the VIP page). Rows are embedded in that page's HTML, so there's **no public REST route** that lists them. Admin: `manage_options`. |
| Freshness | Automatic rows are cached in a transient for 1 hour. The cache is cleared on `save_post_post`, `deleted_post`, `set_object_terms` and `edited_term`/`delete_term` (categories and tags), and by **Refresh now** in admin. Overrides and manual rows are applied after the cache, so admin edits show at once. A new Swipe deck or test appears within the hour, or at once after Refresh now. |
| Language | Front and admin in English. |
| Phones | The front table stacks into labelled cards below 600px, as in the prototype. Admin is desktop. |

---

## Phase 0 — read-only inventory (stop and report)

CC can't see the live database. Steps 1–3 are **read-only SQL for Mariusz** to run in phpMyAdmin (database `serwer135893_bluetree`). Write the queries with the site's real table prefix (find it first with `SHOW TABLES LIKE '%options'`), and keep each one short enough to paste. Mariusz pastes the results back into the session.

1. **Table 185.** Find out where its rows live:
   - `SHOW TABLES LIKE '%wpdatatable%';`
   - the row for ID 185 in the wpDataTables table list: `id`, `title`, `table_type`, `LEFT(content, 400)`
   - if it's a manual table (rows in the database): `SHOW CREATE TABLE` of its data table, `SELECT COUNT(*)`, and `SELECT * … LIMIT 15`
   - the **link domains**: count of rows per host of the link column (the link may be a bare URL or an `<a href>`, so the query should pull out the `href` when there is one)

   Report the columns (title, category, tags, level, link) and how tags are separated. **Hard stop** if the rows are not in the database (e.g. read live from Google Sheets or an Excel file). We then switch the importer to a CSV that Mariusz exports.
2. **Categories.** Every category: term ID, name, slug, parent, and count of published posts. Mark which look like real lesson categories and which are labels (e.g. *darmowa lekcja*, *Lucy*). Also count how many published posts have **two or more** of the lesson categories, and give five examples.
3. **CEF tags.** Count published posts in lesson categories **with** and **without** a tag whose name matches `CEF A1`…`CEF C2`. List every tag name that starts with `CEF` with its post count, to catch spellings like `CEF b1` or `CEF  B2`.
4. **Tools (from the repos, `git clone --depth 1`).** For each of `tbt-swipe`, `tbt_matching_game`, `translation-drill` and `tbt-test-maker`, report:
   - the table or post type and the columns read here
   - what makes an item **open to anyone with the link**, as opposed to a class-only or draft item
   - the public helper that builds its link (class and method, file and line), and whether it's safe to call from another plugin
   - whether the item has a level

   Expected: Swipe `deck_type` + `status` + `level` + `TBTS_DB::deck_url()`; Test Maker `status` + `access` + `TBTTM_URLs::test_url()`; Drill `status` + `class_id` + `drill_url()`; Matching Game `post_status` + `_tbtmg_game_data['level']`.
5. **Skeleton.** Copy from `aparatofan/tbt-poll`: the bootstrap shape, Hub item filter, the admin submenu under `TBT_HUB_SLUG` (and its fallback when Hub is absent), `.github/workflows/deploy.yml` (with `tbt-poll.php` → `tbt-search-table.php` in the preflight), and the tests layout. Copy from `aparatofan/flashcards` the bundled `tbt-tokens.css` fallback and the glossary's table, search and pager CSS patterns, which the prototype follows.
6. **Report and STOP.** **Hard stop** if:
   - step 1 says the rows aren't in the database;
   - a tool's "open" state can't be told apart from class-only items;
   - a tool's link can only be built by copying private code.

   Otherwise, end with a short table: the proposed **Lesson categories** default (the 23 categories with banner icons in Lesson Builder's `CATEGORY_ICONS`, or Mariusz's corrections), the **Never list** default (*darmowa lekcja* and any other label from step 2), and the **Multidecker** host or hosts from step 1. Wait for Mariusz to confirm.

---

## Files

```
tbt-search-table.php                 version 0.1.0, bootstrap, Hub item
includes/class-tbtst-format.php      pure helpers (no WordPress)
includes/class-tbtst-db.php          table, install/upgrade
includes/class-tbtst-sources.php     automatic rows: lessons + four tools
includes/class-tbtst-rows.php        merge (auto + overrides + manual), cache
includes/class-tbtst-import.php      table 185 scan, import, undo
includes/class-tbtst-rest.php        admin-only routes
includes/class-tbtst-admin.php       the admin screen (four tabs)
includes/class-tbtst-shortcode.php   [tbt_search_table]
assets/js/table.js                   front: filters, sort, paging
assets/css/table.css
assets/js/admin.js
assets/css/admin.css
assets/css/tbt-tokens.css            bundled fallback (copied from Flashcards)
assets/css/fonts.css + assets/fonts/ Roboto Slab, as TBT Poll bundles it
tests/test-format.php
.github/workflows/deploy.yml
CLAUDE.md                            new: what this plugin is, prefix, current release → docs/SPEC-0.1.0.md
README.md
docs/SPEC-0.1.0.md                   this file (Mariusz adds it)
docs/prototypes/search-table.html    the approved prototype (Mariusz adds it)
```

---

## Task 1 — Table (`class-tbtst-db.php`)

`TBTST_DB_VERSION` = `1`. Created by `dbDelta` on activation **and** from a `plugins_loaded` version check, because FTP deploys never fire activation.

`{prefix}tbtst_rows`: one row per **manual row**, and one per **automatic row that has an override or is hidden**.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `source` | varchar(10) | `manual`, `lesson`, `swipe`, `mg`, `drill`, `test` |
| `source_id` | bigint unsigned | 0 for manual; post ID / set ID / drill ID / test ID otherwise |
| `title` | varchar(255) NULL | NULL = follow the source (automatic rows) |
| `category` | varchar(190) NULL | a WordPress category **name** or a tool name |
| `tags` | text NULL | tags joined with `, ` |
| `level` | varchar(2) NULL | `''` = explicitly none; NULL = follow the source |
| `url` | varchar(500) | manual rows only; `''` for automatic |
| `hidden` | tinyint(1) default 0 | |
| `import_ref` | varchar(40) default '' | the table 185 row ID for imported rows |
| `import_batch` | varchar(20) default '' | the import run, for Undo |
| `created_at`, `updated_at` | datetime | UTC |

Indexes: `UNIQUE (source, source_id)` for automatic rows (manual rows all have `source_id` 0, so use a separate non-unique index on `source`, and enforce uniqueness for automatic rows in code), `import_ref`.

The category is stored by **name**, not ID. If Mariusz later renames a WordPress category, the old name no longer matches. Rows whose category isn't in the current list show a red **Unknown category** flag in admin, and the Edit dropdown opens empty so a new one has to be picked.

## Task 2 — Pure helpers (`class-tbtst-format.php`)

No WordPress functions. Tested in Task 10.

- `fold( $s )`: lower-case (mb), strip accents, `ł → l`, collapse whitespace. Used for search, matching and duplicates.
- `cef_level( array $tags )`: the first tag matching `/^\s*cef\s*(a1|a2|b1|b2|c1|c2)\s*$/i`, upper-cased level, else `''`.
- `split_tags( $s )`: splits on `|`, `,` or `;`, trims, drops empties, de-duplicates (by `fold`), keeps the first spelling.
- `sort_tags( array $tags )`: A–Z by `fold`.
- `href( $s )`: the URL from a bare link or from the first `href="…"` / `href='…'`; `''` if none.
- `normalise_url( $url )`: lower-case scheme and host, drop `www.`, drop a trailing slash, keep the path and only the query keys `p`, `page_id`, `deck`, `test`, `drill` (sorted). Used to match table 185 links to automatic rows.
- `kind( $url, $site_host, array $md_hosts )`: `multidecker` (host in `$md_hosts`), `pdf` (path ends `.pdf`), `site` (host = site host), `other`.
- `match_category( $text, array $names )`: the name whose `fold` equals `fold( $text )`, else `''`.
- `valid_level( $s )`: `A1`…`C2` (upper-cased) or `''`.

## Task 3 — Automatic rows (`class-tbtst-sources.php`)

`TBTST_Sources::all(): array` returns a list of `array( source, source_id, title, category, tags[], level, url )`.

- **Lessons:** one `WP_Query` for published posts in the Lesson categories (`fields => ids`, `no_found_rows`, all posts). Then prime the term caches (`update_object_term_cache`) and read the categories and tags from the cache, never with one query per post. Apply **Never list**. Category and level as in Decisions.
- **Swipe, Matching Game, Drill, Test:** as in Decisions and Phase 0. Each source is wrapped in its own `try`/`catch` (`Throwable`). A failing source logs one line and adds no rows, and the rest still render.
- Titles are decoded (`wp_specialchars_decode` / `html_entity_decode`) **before** caching, so the front escapes them exactly once.

## Task 4 — Merge and cache (`class-tbtst-rows.php`)

- `auto()`: `TBTST_Sources::all()` cached in the transient `tbtst_auto` (1 hour). It's cleared by the hooks in Decisions and by `TBTST_Rows::flush()`.
- `merged( $include_hidden )`: for each automatic row, apply the override row (same `source` + `source_id`) field by field, where non-NULL wins. Add `edited` = any override field non-NULL, and `hidden`. Then append every manual row. Each item gets a `key`: `manual:{id}` or `{source}:{source_id}`.
- Override rows whose source item no longer exists (a deleted post or deck) are ignored everywhere. **Refresh now** deletes them, since there is nothing left to override.
- `categories()`: all WordPress category names (`hide_empty => false`, sorted) plus the five tool names.

## Task 5 — Front (`class-tbtst-shortcode.php`, `table.js`, `table.css`)

`[tbt_search_table per="10"]`. The heading *ADVANCED SEARCH* and its subtitle stay in Divi, so the shortcode renders the filter panel, the toolbar, the table and the pager only, exactly as in the prototype's VIP view.

- The server embeds the visible rows as JSON in a `data-rows` attribute (`wp_json_encode`, then `esc_attr`): `[title, category, tags[], level, url, is_tool]`. Rows are already sorted A–Z. There's no REST call.
- Filters and **Search everything** match with `fold`. Title and Tags are "contains". Search everything covers title, category, tags and level.
- Clicking a tag puts it in the Tags filter. Active filters show as chips with ✕, plus **Clear all** when there are two or more.
- The pager shows numbers with ellipses, plus ‹ › buttons. "Showing X to Y of N entries". Changing any filter goes back to page 1.
- The Link column has the **Open** pill. Links to this site open in the same tab. Links to other hosts (Multidecker) open in a new tab with `rel="noopener"`.
- Tool categories are shown in italic muted text, as in the prototype.
- Empty result: "No lessons or activities match. Try fewer filters."
- **Every value is inserted as text** (`textContent` or an `esc()` helper). Titles and tags come from editors and old data.
- Light only. TBT tokens via Hub's `tbt-tokens.css` when Hub is active, else the bundled copy. Roboto Slab for rows, Roboto for controls. Every class is prefixed `tbtst-`, and the CSS reset uses `:where()`.

## Task 6 — Admin screen (`class-tbtst-admin.php`, `admin.js`, `admin.css`)

**TBT Hub → Search Table** (`manage_options`). If Hub is absent, it's a top-level menu, as in TBT Poll. Plain wp-admin styling, as in the prototype's Admin view. The page title has the **Add row** button next to it, plus **Refresh now** (flushes the cache and shows "Refreshed · N automatic rows").

Four tabs. The first three are as in the prototype, plus **Settings**:

1. **All rows.** Filters: Source (All, Lesson, Swipe, Matching Game, Drill, Test, Manual), Hidden (All / Visible / Hidden), and a search box. Columns: Title (with **Edited**, **Hidden** and **Unknown category** flags) · Source · Category · Tags · Level. Hover actions:
   - **Edit**
   - **Hide** / **Show**
   - **Reset**, only on edited automatic rows
   - **Delete**, only on manual rows, with a confirm dialog

   The list is client-side with paging at 50, from JSON embedded in the page.

   **Edit / Add row** dialog fields:
   - Link: manual rows only, and required. Automatic rows show "Source · link is set automatically".
   - Title: required.
   - Category: required, a dropdown with Activities (tools) then WordPress categories.
   - Tags: comma-separated.
   - Level: None or A1–C2.

   On automatic rows, a line under the fields says "Your changes override the automatic values until you press Reset". Saving a field equal to the source value stores NULL for it, so a row is only **Edited** when something really differs.
2. **Import from table 185.** See Task 7.
3. **Missing CEF tag.** Lesson rows (not hidden) with no tag that `cef_level` accepts. Columns: Lesson · Category · Tags now · **Edit post** (`get_edit_post_link`). The tab shows a red count badge. Once the tag is added and the cache refreshes, the lesson drops off. Also list **odd CEF tags**: tags starting with "CEF" that `cef_level` rejects, with their post counts.
4. **Settings.**
   - **Lesson categories:** a checkbox list of all categories, grouped under parents. Default from Phase 0.
   - **Never list posts in:** a checkbox list, with *darmowa lekcja* as the default.
   - **Multidecker hosts:** one per line, default from Phase 0.

   Saving flushes the cache.

## Task 7 — Import from table 185 (`class-tbtst-import.php`)

Read-only until **Import selected**. Every action is a REST call with a nonce, and nothing is written on page load.

**Scan.** Read every row of table 185 (where Phase 0 found it). For each row:
1. Use `href()` on the link and `split_tags()` on the tags. The level comes from the level column via `valid_level`, else from `cef_level( tags )`.
2. **Covered:** the normalised link equals the normalised link of an automatic row, or `url_to_postid()` gives a post that is an automatic lesson row. Skipped and counted.
3. **Duplicate:** the same normalised link as an earlier row in table 185. Skipped and counted.
4. **Already imported:** `import_ref` exists in `tbtst_rows`. Skipped and counted.
5. Everything else is **left to check**, grouped by `kind()`:
   - **Multidecker links**: category preset **Multidecker**, all ticked.
   - **PDF files**: ticked.
   - **Links to this site**: ticked, unless `url_to_postid()` = 0 and the path isn't a file. Those rows are unticked and marked "The page this links to no longer exists."
   - **Other sites**: ticked.

**Screen.**
- The stats line: rows in table 185 · covered · duplicates · already imported · left to check.
- One collapsible section per group, with a group checkbox (select all / none), a count, and a **Set category for the ticked rows** dropdown with **Apply**.
- Each row: checkbox · title and link · category dropdown · level. The category is preset by `match_category()` against the current list. Unmatched categories show red, with "“X” is not a category" under them.
- **Import selected** refuses while any ticked row has no category, with a message naming the count. The rest is a toast.

**Import.** One batch ID (`gmdate( 'YmdHis' )`). Each ticked row becomes a manual row with its title, category, tags, level, link, `import_ref` and `import_batch`. Tags are saved as they are (including `CEF B1`).

**Undo last import.** Shown when a batch exists. It deletes that batch's manual rows that haven't been edited since (`updated_at` = `created_at`), and says how many were kept because they had been edited.

Large table: the scan runs in one request. If Phase 0 shows more than about 3 000 rows, say so in the report, so we can page it.

## Task 8 — REST (`class-tbtst-rest.php`)

Namespace `tbt-search-table/v1`. Every route uses `permission_callback` → `current_user_can( 'manage_options' )` and the nonce `wp_rest`.

| Route | Does |
|---|---|
| `POST /rows` | Adds a manual row. Title, category (must be in `categories()`), link (`esc_url_raw`, http/https only) required. |
| `PATCH /rows/{key}` | Edits. Manual: every field. Automatic: overrides (Task 6, so a field equal to the source value is stored as NULL). |
| `POST /rows/{key}/hide` `{hidden: bool}` | Hides or shows. |
| `POST /rows/{key}/reset` | Automatic only: clears overrides. The row is deleted if it's not hidden. |
| `DELETE /rows/{key}` | Manual only. |
| `POST /refresh` | Flushes the cache and cleans orphans (Task 4). Returns the automatic count. |
| `GET /import/scan` | Task 7 scan result. |
| `POST /import` `{rows:[{ref, category, …}]}` | Imports. Each ref is re-read from table 185 on the server; only the category (and the tick) come from the browser. |
| `POST /import/undo` | Task 7. |
| `POST /settings` | Task 6 settings. |

Each route returns the updated row (or list) so the screen re-renders without a reload.

## Task 9 — Bootstrap and docs

`TBTST_VERSION` `0.1.0`, header (Requires PHP 8.0). Hub item: slug `tbt-search-table`, title *TBT Search Table*, description *The VIP page's advanced search: lessons and open activities*. `.github/workflows/deploy.yml` from TBT Poll. README: what it is, the shortcode, the settings, and the sources. CLAUDE.md: prefix, files, the data contract (`tbtst_rows`), current release → `docs/SPEC-0.1.0.md`.

## Task 10 — Tests (`tests/test-format.php`)

Plain PHP, as TBT Poll's tests. Cover every Task 2 helper:
- `cef_level`: `CEF B1`, `cef  c2`, `CEF B1+` rejected, none.
- `split_tags`: pipes, commas, duplicates, empties.
- `href`: bare, `<a href>`, single quotes, none.
- `normalise_url`: www, slash, scheme, kept vs dropped query keys.
- `kind`: all four.
- `match_category`: case and accents.
- `valid_level`.

## Validation

```bash
for f in tbt-search-table.php includes/*.php; do php -l "$f"; done
node --check assets/js/table.js && node --check assets/js/admin.js
php tests/test-format.php
```

---

## Manual steps for Mariusz

**Before CC starts:** run the Phase 0 SQL and paste the results into the session.

**After merging:**
1. First deploy: run the workflow by hand with **allow_create** ticked (the server folder is new). Create `/tbt-search-table/` on the server first if the workflow asks for it. Then activate the plugin.
2. **Search Table → Settings:** check the three defaults, then save.
3. **Import from table 185:** check the groups, fix the red categories, then **Import selected**.
4. Put `[tbt_search_table]` on the VIP page **below** the wpDataTables table, so both are visible for a test.
5. Run the checklist. When you're happy, remove the wpDataTables table from the VIP page.
6. Purge LiteSpeed's cache.

wpDataTables itself stays installed until the glossaries are moved (the next release, in Flashcards).

## Acceptance checklist (live)

- [ ] VIP page: the table looks like the prototype (filters panel, toolbar, blue headers, tag pills, level badges, Open pills, pager). Default 10 rows, Title A–Z.
- [ ] Each filter works on its own and combined. Clicking a tag filters by it. Chips clear one filter or all. Search everything finds a word in a tag.
- [ ] The Category filter shows *Lessons* and *Activities* groups and no made-up categories.
- [ ] A lesson published now (or a new open Swipe deck after **Refresh now**) appears without any typing. A Swipe deck shows its level.
- [ ] A free copy in *darmowa lekcja* doesn't appear twice.
- [ ] Admin edit of a Swipe deck (tags + level) shows on the VIP page after a reload; **Reset** brings back the original.
- [ ] Hiding a row removes it from the VIP page; Show brings it back.
- [ ] Add row: category is a dropdown; saving without one is refused.
- [ ] Import: Multidecker group preset and ticked; a red category blocks import until fixed; after import, the Multidecker links appear on the VIP page with category *Multidecker* and open in a new tab. Scanning again shows them as **already imported**.
- [ ] Undo last import removes the batch (try on a small test batch first, then import again).
- [ ] Missing CEF tag lists lessons without the tag; adding the tag and pressing Refresh now removes the lesson from the list.
- [ ] Phone width: the table stacks into labelled rows; nothing overflows.
- [ ] Logged out, open the VIP page: whatever protects it today still protects it (the rows are inside the page, not in a public route).
- [ ] PHP error log and browser console: nothing new from `tbt-search-table`.

## Out of scope

- **Lesson glossaries.** The "+ My Dictionary" button and moving old wpDataTables glossaries into lessons come next, as a Flashcards release with its own spec.
- Removing wpDataTables (after the glossaries).
- A column for "source type" on the front. The category already says Swipe, Test and so on.
- Editing the post's own tags or categories from this screen. Use **Edit post**.
