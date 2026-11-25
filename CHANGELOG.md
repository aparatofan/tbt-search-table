# Changelog

## 0.1.1 — 25 Nov 2026

Fix: table 185 links to lessons whose address changed (`docs/SPEC-0.1.1.md`).

- **Old addresses are now recognised.** The import finds the post a link to this site points to in this order, without any HTTP request:
  1. `?p=` / `?page_id=`
  2. `url_to_postid()`
  3. the slug as a post or page
  4. the newest published post that used that slug before (`_wp_old_slug`, the same lookup as WordPress's old-slug redirect)

  A lesson reached through an old address, such as `/we-should-all-grow-fat-and-be-happy/`, is now **covered**, not "no longer exists".
- **A draft doesn't hide a published lesson.** If a draft took the address and a published lesson used it before, the published lesson wins.
- **Reason lines.** A link to a published post that isn't in the table stays in *Links to this site*, ticked, with a grey line saying why:
  - its category isn't in Lesson categories
  - it is in the Never list
  - it is a page, not a post

  The current address is shown under the old one.
- **"No longer exists"** now means nothing was found, or the post isn't published. For a draft, private, scheduled or trashed post, a second line says which. These rows start unticked.
- **Select all** in a group no longer ticks "no longer exists" rows. They can still be ticked one by one. Before, the group checkbox looked unticked while a group was partly ticked, so one click ticked every row, including those.
- **Duplicates.** An old and a new address of the same post count as one link.

## 0.1.0 — 25 Nov 2026

First release (`docs/SPEC-0.1.0.md`).
