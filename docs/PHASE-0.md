# TBT Search Table 0.1.0: Phase 0 report

Status: **waiting on Mariusz.** Steps 4 and 5 (the repos) are done below. Steps 1–3 need the SQL at the end of this file run in phpMyAdmin on `serwer135893_bluetree`, with the results pasted back into the session.

## Step 4: tools (read from the repos' `main`)

| Tool | Where the data lives | Open to anyone with the link | Link helper | Level |
|---|---|---|---|---|
| **Swipe** 1.17.0 | table `{prefix}tbts_sets`: `id, title, slug, status, deck_type, level, class_id` | `deck_type = 'open'` and `status = 'published'` (the only other status is `draft`). Class decks are `deck_type = 'class'`. | `TBTS_DB::deck_url( $set )`, `includes/class-tbts-db.php:205`. Public static, and reads only `$set->slug` and the `tbts_deck_page_id` option. Safe to call. Returns `''` when no deck page is set. | yes, `level` (`A1`–`C2` or NULL) |
| **Matching Game** 0.9.1 | post type `tbt_match_game` (`class-post-type.php:15`), meta `_tbtmg_game_data` (`class-game-repository.php:15`) | `post_status = 'publish'`. The plugin has **no class-only state**: no class or lesson field, and the post type is public. | `get_permalink()` (rewrite slug `matching-game`) | yes, `_tbtmg_game_data['level']`. It may hold a Students-style value like `B1.5`, so I cut it at the dot, as the plugin's own `Levels::normalise()` does. |
| **Drill** 1.9.0 | table `{prefix}tbtd_drills`: `id, slug, title, status, class_id` | `status = 'published'` and `class_id IS NULL`. Drill's own README says "no class means an open drill". Note: attaching a class never restricts the link, but the class is what separates class drills from open ones. | `TBTD_Urls::drill_url( $slug )`, `includes/class-tbtd-urls.php:58` (the class is `TBTD_Urls`, not `TBTD_URLs`). Public static and safe. Returns `''` with no player page. | **no** |
| **Test Maker** 0.7.0 | table `{prefix}tbttm_tests`: `id, slug, title, status, access, class_id` | `status = 'published'` and `access = 'open'` (guests can take it). `access = 'login'` tests need a login, and the class doesn't restrict them either. | `TBTTM_Urls::test_url( $slug )`, `includes/class-tbttm-urls.php:70`. Public static and safe. | **no** |

All four helpers depend only on an option and a slug, so calling them from this plugin doesn't copy any private code. **No hard stop from the tools.**

### Two things that differ from the spec

1. **`CATEGORY_ICONS` doesn't exist.** Lesson Builder (`main`, last commit 16 Nov 2026) has only `TBTLB_Config::CATEGORIES` with **two** entries: *Man and Society* and *Personal productivity*. No other repo has a list of 23 categories either. The **Lesson categories** default therefore has to come from the step 2 results plus your corrections.
2. **`_tbtlb_type` is never written.** Lesson Builder writes only `_tbtlb_level` (values `A1`–`C2`, `class-tbtlb-rest.php:332`). The spec only needs the level, so nothing changes. I'll just not read `_tbtlb_type`.

### One question (you can answer with the SQL results)

Test Maker: the spec says `access = 'open'` only. Published `login` tests with no class are also usable by any logged-in student, which is everyone on the VIP page. **Keep `open` only** (the spec, and my recommendation), or include `login` tests with no class too?

## Step 5: skeleton

Everything is in place to copy. From `tbt-poll`: the bootstrap, the `tbt_hub_items` filter, the admin menu under `TBT_HUB_SLUG` with its fallback, `deploy.yml` (with `tbt-poll.php` → `tbt-search-table.php` in the preflight), `tests/`, `fonts.css` and the Roboto Slab fonts. From `flashcards`: `tbt-flashcards/assets/vendor/tbt/tbt-tokens.css` and the table, search and pager patterns in `tbt-flashcards/assets/css/glossary.css`.

## Step 6: still to fill in after the SQL

| Setting | Proposed default |
|---|---|
| Lesson categories | *from step 2* |
| Never list | *darmowa lekcja* plus any other labels from step 2 |
| Multidecker hosts | *from step 1h* |
| Table 185 | rows in the database? how many (over ~3 000 means paging the scan)? how are tags separated? |

---

## SQL for phpMyAdmin (all read-only)

**First run 0.** If the prefix it shows isn't `wp_`, replace `wp_` in every query below with the real one.

```sql
-- 0. Table prefix
SHOW TABLES LIKE '%options';
```

### Step 1: table 185

```sql
-- 1a. wpDataTables tables
SHOW TABLES LIKE '%wpdatatable%';
```

```sql
-- 1b. Table 185 itself (mysql_table_name = where its rows live)
SELECT id, title, table_type, mysql_table_name, LEFT(content, 400) AS content
FROM wp_wpdatatables WHERE id = 185;
```

```sql
-- 1c. Its columns as wpDataTables sees them
SELECT orig_header, display_header, column_type, pos
FROM wp_wpdatatables_columns WHERE table_id = 185 ORDER BY pos;
```

In 1d–1h, replace `wp_wpdatatable_185` with the `mysql_table_name` from 1b if it differs.

```sql
-- 1d.
SHOW CREATE TABLE wp_wpdatatable_185;
```

```sql
-- 1e.
SELECT COUNT(*) FROM wp_wpdatatable_185;
```

```sql
-- 1f.
SELECT * FROM wp_wpdatatable_185 LIMIT 15;
```

In 1g, replace `LINKCOL` with the link column's name from 1d. The query handles a bare URL, `<a href="…">`, `<a href='…'>` and wpDataTables' `url||text` form.

```sql
-- 1g. Rows per link host
SELECT LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(
         SUBSTRING_INDEX(IF(LOCATE('href=', LINKCOL) > 0, SUBSTRING(LINKCOL, LOCATE('href=', LINKCOL) + 6), LINKCOL), '||', 1),
       '://', -1), '/', 1), '"', 1), '''', 1))) AS host,
       COUNT(*) AS n_rows
FROM wp_wpdatatable_185 GROUP BY host ORDER BY n_rows DESC;
```

In 1h, replace `CATCOL` with the category column's name. This shows the made-up categories the import will have to fix.

```sql
-- 1h. Rows per category in table 185
SELECT CATCOL AS category, COUNT(*) AS n_rows
FROM wp_wpdatatable_185 GROUP BY CATCOL ORDER BY n_rows DESC;
```

### Step 2: categories

```sql
-- 2a. Every category with its count of published posts
SELECT t.term_id, t.name, t.slug, tt.parent,
       (SELECT COUNT(*) FROM wp_term_relationships tr
        JOIN wp_posts p ON p.ID = tr.object_id AND p.post_type = 'post' AND p.post_status = 'publish'
        WHERE tr.term_taxonomy_id = tt.term_taxonomy_id) AS published
FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id AND tt.taxonomy = 'category'
ORDER BY tt.parent, t.name;
```

```sql
-- 2b. Posts with two or more categories, by combination
SELECT cats, COUNT(*) AS posts FROM (
  SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ' + ') AS cats
  FROM wp_posts p
  JOIN wp_term_relationships tr ON tr.object_id = p.ID
  JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
  JOIN wp_terms t ON t.term_id = tt.term_id
  WHERE p.post_type = 'post' AND p.post_status = 'publish'
  GROUP BY p.ID HAVING COUNT(*) >= 2
) x GROUP BY cats ORDER BY posts DESC;
```

```sql
-- 2c. Five examples
SELECT p.ID, p.post_title, GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ' + ') AS cats
FROM wp_posts p
JOIN wp_term_relationships tr ON tr.object_id = p.ID
JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
JOIN wp_terms t ON t.term_id = tt.term_id
WHERE p.post_type = 'post' AND p.post_status = 'publish'
GROUP BY p.ID HAVING COUNT(*) >= 2 ORDER BY p.post_date DESC LIMIT 5;
```

### Step 3: CEF tags

```sql
-- 3a. Every tag starting with CEF, with its post count
SELECT t.term_id, CONCAT('[', t.name, ']') AS name_in_brackets, tt.count
FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id AND tt.taxonomy = 'post_tag'
WHERE t.name LIKE 'CEF%' ORDER BY t.name;
```

The lesson categories aren't fixed yet, so 3b counts **per category**. I add up the lesson categories from the result.

```sql
-- 3b. Published posts with / without a valid CEF tag, per category
SELECT t.name AS category, SUM(x.has_cef) AS with_cef, SUM(1 - x.has_cef) AS without_cef
FROM (
  SELECT p.ID, EXISTS(
    SELECT 1 FROM wp_term_relationships r2
    JOIN wp_term_taxonomy x2 ON x2.term_taxonomy_id = r2.term_taxonomy_id AND x2.taxonomy = 'post_tag'
    JOIN wp_terms y ON y.term_id = x2.term_id
    WHERE r2.object_id = p.ID AND LOWER(y.name) REGEXP '^[[:space:]]*cef[[:space:]]*[abc][12][[:space:]]*$'
  ) AS has_cef
  FROM wp_posts p WHERE p.post_type = 'post' AND p.post_status = 'publish'
) x
JOIN wp_term_relationships tr ON tr.object_id = x.ID
JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
JOIN wp_terms t ON t.term_id = tt.term_id
GROUP BY t.name ORDER BY t.name;
```
