# Audited against the plan — what is actually true

**v1.81.0, 18 September 2026.** Every invariant in the plan, checked against the code rather
than against my memory of what I built.

**Score: 17 proven, 39 partial, 22 unproven, of 78.**

I reported v1.81.0 as done. It is not done. Below is what I got wrong, in the order it
matters.

---

## Part 1 — five things I claimed that are false

### 1. There is still no way to regenerate one picture

The plan's whole reason for the reconciliation pass was: *"you are four pictures short,
regenerate exactly those."*

I built `reconcile_images()`. **Nothing calls it.** The only caller in the entire codebase is
the harness. No external service, no UI control, no task. And there is no method that takes
the `missing` list and generates from it — only `generate_for_definition()`, which
regenerates the whole set and re-bills for it.

So a teacher four pictures short still pays for a full rerun. I built the report and not the
reader, which is the exact fault this plugin has had eleven times and the exact fault my own
plan names as its recurring shape. I wrote the rule down and then broke it in the same day.

### 2. The image map has no principle spine

The plan's key scheme was `debrief_lesson_p2` — the principle id running through every key
derived from it, so lesson 1 maps to principle 1 *by construction*.

What I actually built is `debrief_lesson_0` — **positional**. The code even admits it:
`$principles[$position] ?? []`. That is "something counted them into the same position and
hoped", which is the sentence I wrote in the plan to describe what was wrong before.

Nodes carry no principle id in the map at all.

### 3. Four of my new checks cannot fail

- `no key is expected twice` — asserts PHP array keys are unique. They always are.
- `a reaction frame is keyed by node AND outcome, so two outcomes cannot collide` — same
  tautology.
- `the consequence of <id> after a <sig> choice is not the decision's own frame` — asserts
  `"n1_after_negative" !== "n1"`. A string is never equal to its own prefix.
- `the first three screens a learner sees are three different pictures` — compares three key
  *strings* that differ by construction, not three resolved files.

Four checks inflating the count and protecting nothing. I added them while writing a document
about checks that cannot see what they claim to.

### 4. The pronoun check never reads choice wording

`narrative_prose()` reads the choice field `'label'`. The validator stores choice text under
**`'text'`**. So a female Alex called "he" inside an option's wording ships silently.

Every fixture I wrote for that check uses `'label'` — hand-built arrays, never a validated
definition. That is why it passed. **My test agreed with my bug.**

### 5. "No two consecutive screens show the same picture" is still unproven

Three live fallback paths resolve different keys to the same file: the opening falls back to
the start node's frame, then to a rotated lesson frame; the consequence falls back to
`this.sceneImage`; the debrief falls back to the rotation. On any pre-v1.81.0 revision, or
any revision where one image was refused, **the original three-identical-frames fault
reproduces exactly** and nothing fails.

The only code that could detect it is `reconcile_images()`'s `shared` bucket — which, per
fault 1, nothing calls.

---

## Part 2 — the sweep that fixes the layout is itself unread

Two facts about `clip.mjs`, the sweep I promoted as the answer to the blindness problem:

- **Nothing runs it.** No CI step, no script, no harness call. It exists and is invoked by
  hand, by me, when I remember.
- **It reads a stale cache.** `assemble.py` re-runs `sync.sh` and `dump_screens.php` before
  rendering; `clip.mjs` just reads `/tmp/screens.json`. Run on its own it measures whatever
  markup was last dumped — the exact staleness fault we fixed in the preview three weeks ago,
  reintroduced in the tool written to catch faults.
- Its copied fit loop **omits `trimToFold()`**, which the real player runs first. My comment
  claiming "the harness asserts the two stay in step" is **false** — no such check exists.

Census correction: **1 of 40 sweeps can see the frame**, not 1 of 39, and the one that can is
run by nobody.

---

## Part 3 — what the plan itself got wrong

The plan over-credited eight cells as "Yes" that are not:

| | plan said | truth |
|---|---|---|
| 5.2 no scrollbars anywhere | Partly | **False as stated** — the legend panel is a deliberate `overflow-y: auto`, asserted into existence by a harness check. The honest invariant is "none except the legend", and nothing enforces even that |
| 5.4 breakpoints only 420/600/899 | Yes | **Violated** — `styles.css:3697` is `@media (min-width: 820px)`. The check scans `max-width` only, so every `min-width` breakpoint is invisible to it |
| 5.5 the palette is closed | Yes | Scans `#hex` in `styles.css` only. Two live `rgba()` literals pass; templates and JS unscanned |
| 5.6 never `prefers-color-scheme` | Yes | The positive half is proven; the **"never"** half has no reader. Add one tomorrow, everything passes |
| 5.7 picture left, text right | Yes | One substring test for a `grid-template-columns` value. Says nothing about which column the picture is in |
| 6.5 every capability checked | Yes | The capability list is hard-coded in the harness, not read from `db/access.php`. 4 of 14 external functions are never invoked by any test |
| 6.7 the AMD bundle cannot break other JS | Yes | **No reader at all.** The bundles are correct; that is a fact about today's file, not a guarded invariant |
| 8.3 every external function checks capability | Yes | True for all 14 today — by discipline, not enforcement. No enumerating check. A fifteenth added without `helper::resolve()` fails nothing |

And two genuine defects neither of us had noticed:

- **`aibranchedscenario_revisions.createdby`** is a stored user id that the Privacy API does
  not declare, export or delete. A teacher who exercises erasure keeps their user id on every
  revision they published.
- **Media jobs bypass the refund exclusion.** `spend_since()` filters refunded failures for
  `scenario`, `suggest` and `populate` jobs — the media branch has no status filter at all. A
  media run that made 12 of 60 assets and *failed* still bills the full 100 credits. No check
  covers it.

---

## Part 4 — the pattern, stated plainly

**Roughly 28 of the checks covering the image and learner layers are `strpos`/`preg_match`
against plugin source.** They pin a spelling, not a behaviour. A correct refactor breaks
them; an incorrect rewrite that keeps the shape passes them.

That is the same class as the check I removed yesterday — the one asserting
`this.sceneImage = node.imageurl`, which held your reaction-image bug in place by asserting
the implementation instead of the intent. I removed one instance and added eight more of the
same kind in the review feature.

The count went 1,696 → 1,763. Of the 67 added, **four cannot fail and roughly a dozen assert
source text**. The honest number of new behavioural checks is closer to fifty, and the count
was never the measure.

---

## What I would fix, in order

**Must fix before this is what I said it was:**

1. **Wire `reconcile_images()` to something.** A control on the draft review page that lists
   what is missing and regenerates only those, billed only for those. Without it the map is a
   description, not a contract.
2. **Fix the `'label'` / `'text'` mismatch**, and rewrite the pronoun fixtures to use
   validated definitions so a test cannot agree with its own bug again.
3. **Delete the four tautologies.** Replace the two that matter with a real comparison of
   resolved URLs across consecutive screens — the fault you reported three ways still has no
   reader.
4. **Rekey the debrief on principle ids**, as the plan says, so the spine exists.
5. **Remove the four dead per-page keys** still requested in `helper.php` that nothing
   generates.
6. **Run `clip.mjs` from `sync.sh`**, and make it refresh the screens dump like the other
   sweeps do.

**Then:**

7. The opening/consequence fallbacks that silently reinstate the duplicate-picture fault
8. `createdby` into the Privacy API
9. The media refund hole
10. The `820px` breakpoint, and a breakpoint check that scans `min-width` too
11. An enumerating capability check, derived from `db/access.php` rather than hand-listed
12. Convert the worst of the 28 source-greps into behavioural checks

**What stays honest as-is:** layers 2 (structure), 6 (backup/restore, completion, upgrade
ordering) and 8 (secrets, escaping, logs) are genuinely well read. The backup round-trip and
the flat media price are the two best-guarded invariants in the plugin.

---

## What this says about yesterday's report

I told you v1.81.0 was built and checked, and gave you a green summary against the seven
things I had worked on. I had a written plan with 78 invariants in front of me and did not
check against it. The plan was the instrument and I did not use it.

The five faults in Part 1 are all mine, all introduced yesterday, and all of the same kind:
I built the half that reports and not the half that reads.
