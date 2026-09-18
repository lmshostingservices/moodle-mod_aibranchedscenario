# The watertight audit plan

**mod_aibranchedscenario v1.81.0 — 18 September 2026.**

Every claim about coverage below was counted from the code, not estimated.

---

## How to read this

The plugin already has a lot of checking: **1,696 harness checks, 45 unit tests, 39 browser
sweeps, 2 Behat features**. And every fault you have reported in the last three weeks
passed all of it.

That is the thing this plan is built around. The question is never "which rule does this
fault break" — it is **"which surface does this fault live on, and can anything see that
surface at all?"** A check that cannot see the surface is not a weak check, it is an absent
one that reports PASS.

The clearest example, found today: **38 of the 39 browser sweeps load the review page,
which sets `height:auto !important` on every slide.** The frame is the constraint that was
failing. Thirty-eight sweeps were measuring a page with the failing constraint switched
off, and all thirty-eight said zero findings while the Continue button was sliced in half.

So each layer below is stated as: **what must be true**, **what proves it**, and **what
proves it today** — with the gap named rather than softened.

---

## The nine layers

| # | Layer | What lives there | Checks today | Honest state |
|---|---|---|---|---|
| 1 | **Content contract** | What the AI must produce | ~380 harness | Good, and now the weakest link is counts |
| 2 | **Validation** | What the plugin accepts | 12 unit + ~240 harness | Good on shape, blind on meaning |
| 3 | **Media** | Images and narration | ~190 harness | Keys proven, pictures never seen |
| 4 | **Learner path** | Attempt, scoring, resume | 13 unit + ~210 harness | Audited once, 5 faults found, all fixed |
| 5 | **Layout** | Every screen at every size | 39 sweeps | **1 of 39 can see the frame** |
| 6 | **Moodle integration** | Gradebook, completion, backup | 6 unit + ~150 harness | Good; cron never verified live |
| 7 | **Money** | Credits, quotas, refunds | ~90 harness | Fixed after an outage; no live proof |
| 8 | **Security & privacy** | Secrets, leaks, GDPR | 5 unit + ~120 harness | Good |
| 9 | **The service** | LMS Labs, the paid route | 0 | **Never once exercised live** |

---

## Layer 1 — the content contract

**What must be true**

| # | Invariant | Proven today |
|---|---|---|
| 1.1 | Every principle carries a non-empty `example` and `pitfall`, not folded into prose | Yes |
| 1.2 | The named cast appears by name in every scene; nobody's role, seniority or gender changes | Partly — name presence only |
| 1.3 | **Pronouns in the prose match the cast record's gender** | **No check exists** |
| 1.4 | Spelling follows the language field, whatever the source uses | Yes |
| 1.5 | The strongest option is not always first | Yes (and the server throws on it — see layer 9) |
| 1.6 | **One debrief entry per principle: 3 principles → 3 lessons, 3 criticals, 3 practice, 3 takeaways** | **Rule added v1.81.0; no check yet** |
| 1.7 | Each debrief entry answers its principle in the same order | **No check exists** |
| 1.8 | Every consequence is distinguishable from its siblings | Yes |
| 1.9 | No skill name leaks outside the debrief | Yes |
| 1.10 | Both routes carry the identical standard | Yes |

**The gap.** 1.3, 1.6 and 1.7 are the three you have personally caught. All three are
**meaning**, not shape, and every check in this layer is a shape check. A scenario with two
lessons and a female Alex called "he" is structurally perfect.

**What to build**

- `pronoun_conflicts($prose, $name, $gender)` — sentence-window scan, routed to the existing
  prose-repair path, rejection with the character named if repair fails
- `debrief_covers_principles($definition)` — count and order, one entry per principle
- Both wired as harness checks that a two-lesson fixture must fail

---

## Layer 2 — validation

**What must be true**

| # | Invariant | Proven today |
|---|---|---|
| 2.1 | Every id is normalised and unique across all seven key families | Yes — after the collision outage |
| 2.2 | No unreachable ending, no single-choice node, no cycle | Yes |
| 2.3 | Every choice's `next` resolves | Yes |
| 2.4 | Gender is `male`/`female`/`non-binary`, or explicitly unstated — **never silently blanked** | **No — blanked at `validator.php:483`** |
| 2.5 | Text fields are length-capped without severing a word | Yes |
| 2.6 | A list shorter than its contract is repaired or rejected, not accepted | **No — `$listof` caps a maximum, enforces no minimum** |
| 2.7 | Rejection messages name the field and never leak content | Yes |

**The gap.** 2.4 and 2.6 are the same fault in two places: **the validator silently accepts
less than was asked for.** A blanked gender and a two-item list both pass, and both then
travel all the way to a learner's screen. Validation that quietly deletes is worse than
validation that rejects, because nothing downstream can tell the difference between "not
supplied" and "discarded here".

---

## Layer 3 — media

**What must be true**

| # | Invariant | Proven today |
|---|---|---|
| 3.1 | Every scene, crisis, lesson and debrief page has its own key | Yes |
| 3.2 | Keys are unique; a collision never throws and never re-bills | Yes — after the retry loop |
| 3.3 | Publishing is atomic with the revision it belongs to | Yes — after the seven-minute window |
| 3.4 | A partial failure is reported with its reason, never silently zero | Yes |
| 3.5 | Cast sheet, treatment, teacher direction and safety clauses survive trimming | Yes |
| 3.6 | **The gender sentence is in the brief and survives trimming** | **Not built** |
| 3.7 | **The consequence shows a reaction, not the decision's own frame** | **No — `player.js` reuses it** |
| 3.8 | **The opening is not the first decision's picture** | **No — `player.php:288` reuses it** |
| 3.9 | **Each debrief entry has its own picture, keyed to its principle** | **Not built — one per page, not per entry** |
| 3.10 | No two consecutive screens show the same picture | **No check exists** |
| 3.11 | No lettering, no real people, no injury — in every brief, at every length | Yes |

**The gap, and it is the big one in this layer:** *nothing has ever looked at an image.*
Every check here proves the **brief** was correct. Whether the picture that came back shows
an exhausted team or a cheerful one is invisible to all 190 checks. 3.10 is the cheapest
real proof available — it needs no model, just a comparison of what is shown on consecutive
screens — and it would have caught the three-identical-frames opening on day one.

---

## Layer 3b — the image map

This is its own layer because it is the thing that ties layers 1 and 3 together, and right
now it does not exist as a thing at all. Images are *filenames*. A filename is not a link.

**Where the pictures come from today**

| Key | Briefed from | Shown on |
|---|---|---|
| `lesson_<principleid>` | the principle | its teaching slide |
| `<nodeid>` | the node | the decision — **and the consequence, and sometimes the opening** |
| `<nodeid>_crisis` | the crisis variant | the crisis |
| `debrief_whatmattered` | the whole page's text | all lessons learnt, one picture |
| `debrief_criticaldecisions` | the whole page's text | all critical decisions, one picture |
| `debrief_practice` | the whole page's text | all practice points |
| `debrief_takeaways` | the whole page's text | all takeaways |

Four things are wrong with that table and they are all the same thing: **the key names a
screen, not an idea.** So a picture cannot follow its idea from where it was taught to
where it is looked back at, and three screens end up sharing one photograph because
they happen to be one "page".

**What must be true**

| # | Invariant | Proven today |
|---|---|---|
| 3b.1 | Every image key names the **idea** it illustrates, not the screen it lands on | No |
| 3b.2 | A principle's id runs through every key derived from it, so the chain is readable | No |
| 3b.3 | Every slot that can show a picture is either filled or explicitly recorded empty | **No — a missing picture is indistinguishable from a slot that never wanted one** |
| 3b.4 | A reconciliation pass can list exactly which slots are unfilled and regenerate only those | **Not built** |
| 3b.5 | Regenerating one picture never re-bills or disturbs the others | Partly — the collision fix covers half of it |
| 3b.6 | A revision's images travel with that revision through backup, restore and republish | Yes |
| 3b.7 | No two consecutive screens show the same file | No |

**The key scheme to build**

The principle id is the spine. Everything that teaches, tests or recalls that principle
carries it:

```
principle p2                     the idea
  lesson_p2                      where it is taught
  n4                             the decision that tests it        (node carries principleid)
  n4_after_positive              the reaction, per outcome signal
  n4_after_neutral
  n4_after_negative
  n4_crisis                      the escalated variant
  debrief_lesson_p2              lessons learnt, entry for p2
  debrief_critical_p2            critical decisions, entry for p2
  debrief_practice_p2            practice, entry for p2
  debrief_takeaway_p2            takeaways, entry for p2
  opening                        the establishing frame, once per scenario
```

Read down that list and you can see the learner's whole journey through one idea, and so
can the code. **Lesson 1 maps to decision 1 maps to takeaway 1 because they share `p1` —
not because something counted them into the same position and hoped.** That is the tagging
you asked for, and it is what makes "reapply this picture to the right spot" a lookup
rather than a guess.

It also makes the debrief counts self-checking: if the map expects `debrief_lesson_p3` and
there is no third lesson, **the missing picture is the missing lesson**, reported by the
same pass.

**The reconciliation pass**

A new step that runs after generation and on demand from the teacher's review screen:

1. Build the expected map from the definition — every principle, every node, every signal
   each node actually uses, every debrief entry
2. List what exists in the revision's file area
3. Report the difference: **expected but missing**, **present but orphaned**, **shared by
   two screens that should differ**
4. Offer to generate only the missing ones, billed only for those

You said not to be afraid of regenerating more, and that is the right instinct — this pass
is what makes it safe to. Today there is no way to say "you are four pictures short"
because nothing knows how many there should have been. Once the map is the contract, a
short scenario is a countable fact, and topping it up costs four images instead of a
rerun of the whole thing.

**Cost.** A five-decision, three-principle scenario goes from about 14 pictures to about
30 — three reaction frames per decision node and twelve debrief entries. Media is a flat
charge to the customer, so this is your cost, not a repricing. If it is too much, the lever
is one reaction frame per node instead of three, which takes it to about 21. **Either way
the map is the same; only a constant changes.**

---

## Layer 4 — the learner path

**What must be true**

| # | Invariant | Proven today |
|---|---|---|
| 4.1 | A resumed attempt stays on its own revision | Yes — was a fault, fixed |
| 4.2 | Scale grading maps correctly; `scale_used_anywhere` is honest | Yes — was a fault, fixed |
| 4.3 | Grades survive backup and restore | Yes — was a fault, fixed |
| 4.4 | Deleting an attempt or erasing a user recalculates completion | Yes — was a fault, fixed |
| 4.5 | An abandoned attempt does not block a new one | Yes — was a fault, fixed |
| 4.6 | Every metric ends inside 0–100 whatever the path | Yes |
| 4.7 | The debrief reflects the decisions actually taken | Yes |
| 4.8 | **A learner can go back and see what they just chose** | **Not built — you raised it today** |

**Note the pattern.** This layer was audited properly exactly once, and that single audit
found five real faults. That is the argument for the rest of this document: the layers
nobody has walked end to end are not the safe ones, they are the unmeasured ones.

---

## Layer 5 — layout

This is the layer with the largest gap between how much checking exists and how much it
proves.

**What must be true**

| # | Invariant | Proven today |
|---|---|---|
| 5.1 | **Nothing clips. Ever. On any screen, at any frame height** | **As of v1.81.0 — `clip.mjs`, 1 sweep** |
| 5.2 | No scrollbars anywhere in the plugin | Partly |
| 5.3 | Every slide sits above the fold | Partly — measured with the frame off |
| 5.4 | Breakpoints only at 420 / 600 / 899; no `:has()` | Yes |
| 5.5 | The palette is closed; no stray colours | Yes |
| 5.6 | Dark mode by measured luminance, never `prefers-color-scheme` | Yes |
| 5.7 | Picture left, text right, on every screen | Yes |
| 5.8 | Fullscreen and normal view show the same content, differently sized | **No — this is where today's fault lived** |
| 5.9 | Focus is visible; reduced motion respected | Yes |

**The finding, stated plainly.** 38 of 39 sweeps inject:

```css
.pv-frame .aibs-slide, .pv-frame .aibs-consequence, .pv-frame .aibs-deckslide {
    height: auto !important; max-height: none !important; overflow: visible !important
}
```

That line exists for a good reason — the review page shows every screen at once and each
must be readable end to end. But it means **the entire browser-sweep estate is blind to the
frame**, which is the single constraint the whole layout is built around. Measured today
with the frame restored: the consequence was cut by 90px at a 420px frame, 57px at 520px,
and 59px top plus 51px bottom at the desktop cap. Thirty-eight sweeps reported nothing.

**What to build**

1. **Promote `clip.mjs` to the primary sweep.** It runs the real fit algorithm (shrink →
   densify → shrink) and then measures whether any drawn box falls outside the box that
   holds it. It is the only sweep that can currently fail on a clipping fault.
2. **Extend it across the matrix**: 3 frame heights × 3 breakpoints × light/dark ×
   every screen type. That is the sweep that would have caught this three weeks ago.
3. **Add a fullscreen-parity check** (5.8): the same screen, measured at a page frame and at
   a fullscreen frame, must show the same elements — only scaled.
4. **Keep the review page as it is.** It is for reading, not for measuring. The mistake was
   never having a second page that measures.

---

## Layer 6 — Moodle integration

| # | Invariant | Proven today |
|---|---|---|
| 6.1 | Clean install on 4.4, 4.5, 5.2 | Yes |
| 6.2 | Upgrade steps strictly ascend; `$oldversion` read once | Yes |
| 6.3 | Backup and restore round-trip everything, including media | Yes |
| 6.4 | Completion, gradebook and events behave on all three versions | Yes |
| 6.5 | Every capability is declared, checked and documented | Yes |
| 6.6 | All 700 strings exist; none hard-coded in templates | Yes |
| 6.7 | The AMD bundle cannot break another plugin's JS | Yes — after the concatenation lesson |
| 6.8 | **Cron actually runs the ad-hoc tasks on a customer site** | **Never verified** |
| 6.9 | **Behat features pass** | **Never run** |

---

## Layer 7 — money

| # | Invariant | Proven today |
|---|---|---|
| 7.1 | Pricing is fixed in the plugin, not site-configurable | Yes |
| 7.2 | 100 / 150 / 150 / 200 credits, exactly as specified | Yes |
| 7.3 | Media is priced once, as a flat `media_price()` | Yes — after the quota outage |
| 7.4 | A failure refunds, and a failed refund surfaces distinctly | Yes (plugin side) |
| 7.5 | A retry never double-bills | Yes — after the collision loop |
| 7.6 | **The above is true against the live service** | **Never tested** |

The outage here was mine: media priced as `images*5 + clips*5` = 410 against a 400 quota,
refusing media on every scenario. Worth remembering as a category — **an arithmetic error
in pricing looks exactly like a service failure from the outside.**

---

## Layer 8 — security and privacy

| # | Invariant | Proven today |
|---|---|---|
| 8.1 | No provider secrets in the plugin; `siteId` + API key only | Yes |
| 8.2 | No provider error, prompt, credential fragment or stack trace reaches a browser or log | Yes |
| 8.3 | Every external function checks capability and context | Yes |
| 8.4 | Privacy API exports and deletes everything the plugin stores | Yes |
| 8.5 | All output escaped; no raw HTML from the service | Yes |
| 8.6 | Customer content never appears in a log line | Yes |

This layer is in the best shape of the nine. It is also the one where a single miss is a
disclosure rather than a bad screen, so it stays on the list.

---

## Layer 9 — the service

**Zero checks. Nothing in this layer has ever been exercised.**

`lms-labs.com` is blocked by egress from this environment, so no live generation has ever
run from here. Every claim about the paid route — that it works, how long it takes, what it
costs, what the populate step fills in — rests on reading code, mine and theirs.

Known, from the source review:

- A craft rule throws terminally on **20–70% of generations** depending on decision count
- Its message is discarded at the first hop, so the cause is unrecoverable
- The customer is then told "the AI service could not complete the request" — which is
  false; the server rejected its own content

**What to build:** the twenty-minute live test. One scenario, both routes, published, and
every number in this document that currently rests on my reading gets replaced with a
measurement.

---

## The gaps, ranked

Ranked by **learner-visible × likelihood × cost to fix**.

| | Gap | Layer | Why it ranks here |
|---|---|---|---|
| 1 | Debrief counts — one entry per principle | 1, 2 | You have hit it three times today. Rule shipped; enforcement not built |
| 2 | Pronoun/gender chain | 1, 2, 3 | A woman called "he" is the most visibly wrong thing in the product |
| 3 | Clipping sweep across the full matrix | 5 | One sweep exists; it needs the matrix before it counts as cover |
| 4 | **The image map — keys named for ideas, not screens** | 3b | Unlocks 5, 6 and the reconciliation pass; nothing else in the image layer is solid without it |
| 5 | No two consecutive screens share a picture | 3 | Cheapest real image check; catches the opening duplicate today |
| 6 | Reconciliation pass — "you are four pictures short", regenerate only those | 3b | Makes topping up safe and cheap instead of a full rerun |
| 7 | Reaction image on the consequence | 3 | The film cut you asked for; needs the count decision |
| 8 | Per-entry debrief images keyed to principles | 3b | Falls out of gap 4 almost free |
| 9 | Back/forward review | 4 | New build, not a fix |
| 10 | Fullscreen parity | 5 | Would have caught today's fault independently |
| 11 | Run Behat | 6 | Written, never executed |
| 12 | Verify cron on a customer site | 6 | Unknowable from here |
| 13 | The live route test | 9 | Twenty minutes, ~$60, replaces a chapter of assumptions |

---

## The rule that keeps this watertight

One sentence, and it is the one that would have caught every fault we have found:

> **When a fault is found, the first question is not "which rule does this break" but
> "which surface does it live on, and can anything see that surface". If nothing can see
> it, the check is what gets built first — before the fix.**

Applied today: the clipping fault produced `clip.mjs` *before* the density lever was
written. That ordering is why the fix is provable rather than hoped — with the lever
disabled, the sweep fails at all three frame heights; with it, zero.

Every fault in this plugin's history has had the same shape: **a report with no reader.**
`publish_media()` returning void. `$dropped` with no caller. `error.message` never read. A
gender field blanked with nobody told. A list truncated to two with nothing asking why.
Thirty-eight sweeps measuring a constraint that was switched off.

The audit is watertight when every one of the invariants above has a reader — something
that fails, loudly, when the invariant does not hold.

---

## Order of work

**Now** — enforcement, because rules without enforcement are requests

1. Debrief counts: validator enforces one entry per principle; harness fixture with two must fail
2. Gender: stop blanking, add `pronoun_conflicts()`, gender sentence in the fixed tail of the brief
3. `clip.mjs` across the full matrix; add the fullscreen-parity check

**Next** — the images

4. **The image map** — rekey everything on the principle id, so a picture follows its idea
5. The reconciliation pass — count the expected slots, name the missing, regenerate only those
6. Consecutive-picture check
7. Per-entry debrief images, which fall out of 4 almost free
8. Reaction frames on the consequence *(needs your decision: three per node, or one)*

**Then** — the things that need a real site

9. Run Behat
10. The twenty-minute live route test
11. Verify cron on a customer site

**Standing**

12. Every new fault produces its check before its fix. No exceptions — that is the rule
    that turns this from a list into a ratchet.
