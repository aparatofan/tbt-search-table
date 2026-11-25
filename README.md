# TBT Search Table

The VIP page's **Advanced search**: every lesson and open activity in one table, with filters for title, category, tags and level, plus **Search everything**, sorting and paging. It replaces wpDataTables table 185.

Most rows fill in by themselves from what is already on the site. Mariusz can edit any row, hide it, or add rows by hand. A one-off import brings over the table 185 rows that the site can't produce by itself (the Multidecker links, old PDFs, Genially and other outside links).

## The shortcode

```
[tbt_search_table per="10"]
```

`per` is the starting number of rows per page: 5, 10, 25, 50 or 100 (default 10). The shortcode renders the filter panel, toolbar, table and pager. The *ADVANCED SEARCH* heading stays in Divi.

The visible rows are embedded in the page's own HTML, so whatever protects the VIP page protects the rows. There is no public REST route that lists them.

## Where the rows come from

| Source | Rows | Category | Level | Link |
|---|---|---|---|---|
| Lessons | Published posts in a **Lesson category**, and in none of the **Never list** | The lesson category (a child wins over its parent, then A–Z) | `CEF A1`…`CEF C2` tag, else Lesson Builder's `_tbtlb_level` | Permalink |
| TBT Swipe | Decks with `deck_type` = `open` that are not drafts | *Swipe* | The deck's level | `TBTS_DB::deck_url()` |
| TBT Matching Game | Published games (not password-protected) | *Matching Game* | The game's level (`B1.5` counts as `B1`) | Permalink |
| Translation Drill | Published drills with no class | *Drill* | none | `TBTD_Urls::drill_url()` |
| TBT Test Maker | Published tests with access `open` | *Test* | none | `TBTTM_Urls::test_url()` |
| Manual | Added in admin or imported from table 185 | Picked from the list | Picked | Typed or imported |

Every tool is optional. If a plugin is missing, its rows don't appear and nothing else breaks. Other plugins' data is only ever read.

Automatic rows are cached for an hour. Saving or deleting a post, changing its categories or tags, and editing a category or tag clear the cache at once. A new Swipe deck, drill or test appears within the hour, or straight away after **Refresh now**.

## Admin: TBT Hub → Search Table

Under TBT Hub when Hub is active, otherwise a top-level **Search Table** menu. Administrators only (`manage_options`).

- **All rows.** Filter by source, by hidden state, or by searching. **Edit** stores an override on an automatic row; a field you set back to the automatic value follows the source again. **Reset** removes all overrides. **Hide** / **Show** works on any row. **Delete** works only on manual rows. A row whose category no longer exists is flagged **Unknown category**.
- **Import from table 185.** Scans the rows (read-only). Skipped rows are counted: empty, not imported by rule, draft previews, covered by a lesson or activity, duplicates, already imported. Click a count to see its rows. Links to this site are matched to posts through old addresses too (WordPress's `_wp_old_slug`). A link to a published post that isn't in the table says why. The rest are grouped into Multidecker links, files, links to this site and other sites, with select all and **Set category for the ticked rows**. **Import selected** refuses while a ticked row has no category. **Undo last import** removes the last batch, except rows edited since.
- **Missing CEF tag.** Lessons with no CEF tag, with **Edit post** links, and the *odd* CEF tags (`CEF B1+` and the like).
- **Settings.**
  - **Lesson categories**
  - **Never list posts in**
  - **Extra categories:** default *Language Games* and *Mind-maps*.
  - **Multidecker hosts**
  - **Never import table 185 categories:** default *Handouts*, *Handout*, *Video Library*, *Audio library* and *Articles*.

  Until you save, the defaults are used. Saving clears the cache.

## Development

```bash
for f in tbt-search-table.php includes/*.php; do php -l "$f"; done
node --check assets/js/table.js && node --check assets/js/admin.js
php tests/test-format.php
```

Deploys go to the live site over FTPS from `main` (`.github/workflows/deploy.yml`). For the first deploy, run the workflow by hand with **allow_create** ticked.
