# TBT Search Table 0.1.0: Phase 0 report

Status: **done, and 0.1.0 is built on it.**
- **Step 1 (table 185)** was answered in the updated spec (25 Nov 2026). The rows are in `wp_wpdatatable_62`, and the importer reads that name from `wp_wpdatatables` at run time.
- **Steps 4–5** are below.
- **Steps 2–3** (categories and CEF tags) are optional now: the defaults below come from the approved prototype. The SQL at the end is still useful as a check before the first **Settings → Save**.

## Step 6: defaults used

| Setting | Default |
|---|---|
| Lesson categories | The 23 categories in the prototype (and in `TBTST_Settings::DEFAULT_LESSON_CATEGORIES`): Better communicator, Better writer, Business English, Culture, English grammar, Entertain Yourself, Everyday English, Health and fitness, How to learn, How to say it 1, How to say it 3, Learn from TED, Learn more words, Man and Society, Personal productivity, Science stuff, Surprise me, Talking Picture, Tests and Quizzes, The arts, Travel and nature, What do you think, Wise quotes. These are matched to the site's categories by name, ignoring case, spaces and & vs "and". |
| Never list | *darmowa lekcja* |
| Extra categories | Language Games, Mind-maps |
| Multidecker hosts | multidecker.com, squarewords.arturdelura.com |
| Never import table 185 categories | Handouts, Handout, Video Library, Audio library, Articles |

## Step 4: tools (read from the repos' `main`)

| Tool | Where the data lives | Open to anyone with the link | Link helper | Level |
|---|---|---|---|---|
| **Swipe** 1.17.0 | table `{prefix}tbts_sets`: `id, title, slug, status, deck_type, level, class_id` | `deck_type = 'open'` and `status = 'published'` (the only other status is `draft`). Class decks are `deck_type = 'class'`. | `TBTS_DB::deck_url( $set )`, `includes/class-tbts-db.php:205`. Public static, and reads only `$set->slug` and the `tbts_deck_page_id` option. Safe to call. Returns `''` when no deck page is set. | yes, `level` (`A1`–`C2` or NULL) |
| **Matching Game** 0.9.1 | post type `tbt_match_game` (`class-post-type.php:15`), meta `_tbtmg_game_data` (`class-game-repository.php:15`) | `post_status = 'publish'`. The plugin has **no class-only state**: no class or lesson field, and the post type is public. | `get_permalink()` (rewrite slug `matching-game`) | yes, `_tbtmg_game_data['level']`. It may hold a Students-style value like `B1.5`, so I cut it at the dot, as the plugin's own `Levels::normalise()` does. |
| **Drill** 1.9.0 | table `{prefix}tbtd_drills`: `id, slug, title, status, class_id` | `status = 'published'` and `class_id IS NULL`. Drill's own README says "no class means an open drill". Note: attaching a class never restricts the link, but the class is what separates class drills from open ones. | `TBTD_Urls::drill_url( $slug )`, `includes/class-tbtd-urls.php:58` (the class is `TBTD_Urls`, not `TBTD_URLs`). Public static and safe. Returns `''` with no player page. | **no** |
| **Test Maker** 0.7.0 | table `{prefix}tbttm_tests`: `id, slug, title, status, access, class_id` | `status = 'published'` and `access = 'open'` (guests can take it). `access = 'login'` tests need a login, and the class doesn't restrict them either. | `TBTTM_Urls::test_url( $slug )`, `includes/class-tbttm-urls.php:70`. Public static and safe. | **no** |

All four helpers depend only on an option and a slug, so calling them from this plugin doesn't copy any private code. **No hard stop from the tools.**

### Two things that differ from the spec

1. **`CATEGORY_ICONS` doesn't exist.** Lesson Builder (`main`, last commit 16 Nov 2026) has only `TBTLB_Config::CATEGORIES` with **two** entries: *Man and Society* and *Personal productivity*. The prototype's `WP_CATS` list has the 23, so that's the default (see Step 6).
2. **`_tbtlb_type` is never written.** Lesson Builder writes only `_tbtlb_level` (values `A1`–`C2`, `class-tbtlb-rest.php:332`). The spec only needs the level, so nothing changes. This plugin doesn't read `_tbtlb_type`.

### One open question

Test Maker: the spec says `access = 'open'` only. Published `login` tests with no class are also usable by any logged-in student, which is everyone on the VIP page. 0.1.0 keeps `open` only, as the spec says. Including `login` tests with no class would be a one-line change in `TBTST_Sources::test()`.

## Step 5: skeleton

Copied. From `tbt-poll`: the bootstrap, the `tbt_hub_items` filter, `deploy.yml` (with `tbt-poll.php` → `tbt-search-table.php` in the preflight), `tests/`, `fonts.css` and the Roboto Slab fonts. TBT Poll has no admin page, so the submenu under `TBT_HUB_SLUG`, with a top-level menu when Hub is absent, follows TBT Swipe and Translation Drill. From `flashcards`: `tbt-flashcards/assets/vendor/tbt/tbt-tokens.css` and the table, search and pager patterns in `tbt-flashcards/assets/css/glossary.css`.

---

## SQL for phpMyAdmin (all read-only)

The table prefix is `wp_`.

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
