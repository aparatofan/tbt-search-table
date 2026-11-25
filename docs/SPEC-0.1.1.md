# TBT Search Table 0.1.1 — fix: table 185 links to lessons whose address changed

**Problem** (seen live, 25 Nov 2026): on Import from table 185, "Links to this site" has 87 rows. Many are real, published lessons reached by an OLD address. For example, `/we-should-all-grow-fat-and-be-happy/` opens the lesson through WordPress's old-slug redirect. `url_to_postid()` returns 0 for them, so they are marked "The page this links to no longer exists" and are not counted as covered. Also, some rows marked "no longer exists" are still ticked; the spec says they start unticked.

## Phase 0 findings

| Link | Post | Found by | Status | Categories |
|---|---|---|---|---|
| `/6-elevator-pitches/` | 8321 | post_name | draft | Better communicator |
| `/choosing-career/` | 9969 (`match-your-career-to-your-personality`) | _wp_old_slug | publish | Personal productivity |
| `/we-should-all-grow-fat-and-be-happy/` | 479 (`grow-fat-and-be-happy`) | _wp_old_slug | publish | What do you think |
| `/what-it-is-to-drive-a-ford-model-t/` | 21220 (`what-it-is-like-to-drive-a-ford-model-t`) | _wp_old_slug | publish | Surprise me |

The ticked "no longer exists" rows came from the group checkbox. wp-admin shows its partly-ticked state as unticked, and one click ticked every row in the group.

## Fix

- **T1** New resolver `TBTST_Import::resolve_post( $url ): int`. No HTTP requests to the site. Tried in this order:
  1. `?p=` / `page_id=` links: the ID itself, if that post exists.
  2. `url_to_postid()`.
  3. The last path segment as a slug, via `get_page_by_path()` for post and page.
  4. A single read-only query for a published post whose `_wp_old_slug` meta equals that slug (newest first).

  A post found in steps 2–3 that isn't published is kept only if step 4 finds nothing.
- **T2** Use `resolve_post()` for "covered" and for "no longer exists". A row is covered when it resolves to a published post that is an automatic row.
- **T3** A row that resolves to a published post that is NOT an automatic row stays in "Links to this site", ticked, with a grey line saying why:
  - "Lesson exists, but its category (Name) isn't in Lesson categories"
  - "Lesson exists, but it is in Never list (Name)"
  - "Lesson exists, but it is a page, not a post"

  Show the current address under the old one.
- **T4** "The page this links to no longer exists" only when `resolve_post()` finds nothing, or the post is not published (draft, private, trash). Those rows start unticked, and Select all leaves them out.
- **T5** Tests for the pure part: the slug from a URL (trailing slash, query string, `?p=123`).
- **T6** Version 0.1.1, CHANGELOG.

## Acceptance

- Rescan: "covered" goes up by most of the 87.
- The rows left in "Links to this site" each show a reason line.
- `/we-should-all-grow-fat-and-be-happy/` is covered (or shows a reason line), not "no longer exists".
