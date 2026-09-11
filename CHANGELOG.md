# Changelog

All notable changes to AI Branched Scenario are recorded here.

## [v1.20.0] - 2026-09-11

The scrolling is fixed, and it was not what any of us thought it was.

### Fixed — the reason the player scrolled at all

- **A hidden slide was still being laid out, at full height, below the one on screen.**
  `hidden` comes from the browser's own stylesheet, which loses to any author rule — and
  every slide here is given a display of its own (the decision slide is a grid so the
  picture can sit beside the text; the closing and debrief slides are flex columns). Each
  of those quietly outranked `[hidden] { display: none }`. So an opening deck of five
  principles was six slides tall, and nothing measured downstream could ever have found it,
  because every element it measured was the right size. Measured on the real rendered
  markup: 891px of overflow at 1920×945, of which 700 was one invisible slide.

### Fixed — three more, each measured rather than reasoned about

- **The sticky probe walked in fixed steps and stepped straight over the course banner.**
  The stride was a sixteenth of half the viewport — about 30px on a 945px window — so after
  claiming a 60px site bar the next sample landed at 89px, past the four-pixel gap test, and
  the walk stopped without ever probing inside a banner running 60 to 187. It reported 60px
  of pinned furniture where there were 187, which is why the player's bar parked itself
  underneath the banner. It now walks from one band to the next, which cannot miss.
- **The fold check measured the slide, not the player.** The slide is not the last thing on
  the screen — the arrows, the card's margin and the player's padding sit below it — so a
  slide that ended above the fold still left about eighty pixels of player below it, at
  every window size tested.
- **Whether there is room for a frame is now measured, not guessed.** It used to be a media
  query — 900 wide, 620 tall — which cannot see the theme's header or the course banner. On
  a 900×620 window under a 260px banner the query said yes and there were 180 pixels to work
  with; the 300px floor then produced a frame taller than the space it was meant to fit, with
  the overflow hidden and nothing to scroll. Eleven of forty-eight combinations failed this
  way. The player now measures what is actually left and gives up the frame when there is
  genuinely not enough screen, which lets the page scroll honestly instead of clipping.

**Verified by measurement**, on the real rendered player in a real browser, across
seventeen window sizes and three course-banner heights — fifty-one combinations: every one
now sits entirely above the fold, none scroll.

### Changed — the frame holds still

- **It is capped, not maximised.** The frame used to take whatever was left of the window,
  which on a tall screen cropped a 16:9 photograph into a letterbox on its side and left the
  text floating in an enormous card. There is no need to use every pixel — anyone who wants
  the whole screen has the fullscreen control for exactly that.
- **It is measured once and then held.** Recomputing per screen made it slightly different
  on a lesson slide, a decision and a consequence, so the card grew and shrank as the learner
  moved through. The arrows are now reserved for even on screens that do not show them, and a
  consequence takes the same frame as a slide rather than shrinking to its contents. Only a
  resize releases it. Measured: identical on every screen at 1920×945, 1440×900 and 1366×768.

### Changed — room given back to the content

- **The gutter is gone.** The player sat inside up to forty pixels of its own background on
  every side — eighty pixels of height handed to nothing. The card reaches the edges now and
  its own border and shadow do the separating, which is what they were for.
- **The bar is one row, not two.** A large title stacked over a row of progress and readings
  took about ninety-six pixels off the top of every screen, on a page whose banner has
  already said what the activity is. Title at reading size, then progress, then readings,
  then controls, in one row that wraps back to two when the width is not there: 68px.

### Fixed — narration

- **The narrator reads the whole screen.** It read the situation and stopped, so a learner
  listening rather than reading was never told the scene's name and never heard the question
  being put to them. It now reads the title, the situation, the line to consider and the
  question.
- **And the consequence screen reads the lesson, not just the story.** "Why this mattered"
  was on the page and not in the recording — the half that teaches.
- **A clip that dies mid-stream no longer freezes the way forward.** Only `ended` was
  listened for; a connection dropping fires `error`, and the Continue button stayed disabled
  for ever with the control still claiming narration was on.
- **Leaving a card stops the reading.** The stop came after two early exits, so moving to a
  slide with no recording, or with narration muted, left the previous clip playing over it.
- **The arrows wait for the reading, then come back on their own.** They fade rather than
  disable, so a learner who does not want to listen can still press one, and a recording that
  never finishes releases them after ninety seconds regardless.

### Changed — every principle now teaches three things

- A teaching slide carrying one sentence and half a screen of nothing was not a content
  problem to be styled around: the example and the pitfall were optional, so the service left
  them out. They are required now, and a definition without them is rejected with the
  principle named. The narration reads them with the labels the slide shows, so they do not
  run together into one paragraph for a listener.

### Added — spelling that matches the language chosen

- A teacher picks en-AU and gets "finalizing" and "organize" back. The prompt asks for the
  chosen variety in as many words; asking is not getting. The draft review now names the
  words that disagree with the language selected, so the teacher fixes them before a learner
  reads them. It flags, it does not rewrite. "practice" and "license" are deliberately not on
  the list: both are correct as nouns in British and Australian English, and a checker that
  cannot tell a noun from a verb would flag the right spelling about as often as the wrong one.

### Changed — hover

- Every round control inverts rather than tints: the disc fills, the mark reverses out. A
  tinted background with an accent-coloured icon put a second colour on screen competing with
  whatever the scenario's own accent was, which is how a blue arrow ended up on a grey disc.

## [v1.19.0] - 2026-09-11

A pass over the stylesheet and the look of the thing, after a full audit. No schema
changes, no behaviour changes beyond what is listed here.

### Fixed — four design tokens were dead and nobody could see it

- `--aibs-warn`, `--aibs-positive-border`, `--aibs-negative-border` and
  `--aibs-negative-strong` were each defined as themselves — `--aibs-warn: var(--aibs-warn)`.
  A custom property that references itself is a cycle, which the spec resolves to nothing
  at all, everywhere it is used. So the amber ring on the debrief was never amber (the
  three-way traffic light was really two-way), the strong and high-risk outcome badges had
  no border, the emerald theme's accent border was missing, the crisis chip had no edge,
  and the error banner lost both its red border and its red text. Left over from a refactor
  that named the tokens and never filled in the values.

### Changed — the system is now a system

- **Eleven font sizes became seven.** The file held .92, .95, 1.02, 1.04, 1.05, 1.06, 1.16,
  1.18, 1.7, 1.9 and 2.3rem — several of them a hundredth of a rem apart, which is a
  difference no eye can see and no rule can justify. There is now one named ramp from
  micro to display, and not a single bare rem literal left in a `font-size`.
- **Eight line heights became three**, one for each job text does here: a heading that has
  to hold together as one shape, a compact run of interface text, and a paragraph someone
  reads.
- **Font weights go through the two weight tokens**, not through twenty-six literal 600s
  and 700s sitting beside them.
- **Every elevation comes from the shadow scale.** Two bespoke shadows — the button's
  resting state and the lesson card's hover — are gone; the second became the fourth step
  of the scale, since a card lifting under the pointer is a real elevation and deserved a
  name.
- **The spacing grid is stated and enforced.** Spacing here is a two-pixel grid; five
  values were sitting off it (3, 5, 9, 11, 15px) and have been snapped on. It is
  deliberately not tokenised: `var(--aibs-space-3)` in ninety places says nothing that
  `12px` does not.
- A stray `8px` radius on the choice letter, the only fourth value in a three-value scale.

### Added — dark mode

- The activity was a white slab on a dark Moodle theme. It now has a full dark palette:
  base surfaces, all six accent themes, and the toasts, which live outside the player and
  carry their own copy.
- **The decision is not made by `prefers-color-scheme`.** That would be wrong in both
  directions — a light theme on a machine set to dark would get a dark activity in a light
  page, and a commercial theme with its own dark toggle would get nothing, because the
  operating system was never asked. Instead the player measures the page it was dropped
  into: the first ancestor that actually paints a background, and how light that colour is.
  A dark page gets the dark palette whatever made it dark. A theme that toggles at runtime
  is picked up too, because the observer watches the attributes those toggles change.
- Every text pair in the dark palette was measured rather than eyeballed: the lowest is
  5.5:1, against a 4.5:1 requirement.
- White that sits on a photograph — the avatar's ring and the play badge — is now a token
  of its own and deliberately does *not* flip, because the backdrop is the picture either
  way and a dark ring on a dark photo disappears.

### Added — right-to-left

- Every accent bar was `border-left`, alignment was physical, and the speaker plate and
  play badge were pinned with `left` and `right`. On an Arabic or Hebrew site the grid
  mirrors correctly, which made it worse: every bar and badge ended up on the wrong side
  of a correctly mirrored layout, and the scene's rounded corners were rounded on the
  outside edge and square against the text. All now logical properties, with an explicit
  RTL rule for the two things that have no logical form — the arrow's transform and the
  arrow glyph itself, which is flipped to point the way the language runs.

### Fixed — consistency

- **The report page is now inside the product.** It opened on a bare Moodle heading with an
  unshelled row of numbers, so a teacher coming from the wizard landed on what looked like
  a different, older plugin. Same shell, same masthead, and the attempts table is carded
  and takes its own horizontal scroll rather than widening the page.
- **The review page had two titles** — Moodle's heading and the template's own masthead,
  both printing the activity name.
- **The debrief deck was the one deck not held to the frame.** Identical markup and
  identical arrows to the opening deck, and one of them scrolled. It now takes the same
  frame; because its slides carry more text, they scroll inside it rather than clipping.

### Fixed — feel

- **Pressing had no state of its own.** A button under the pointer lifted a pixel, and
  pressing it kept it lifted — so the one moment the interface exists to answer a finger
  with was the moment it did nothing. Buttons, choices and icon buttons now take the press.
- **The three rings arrived on the same frame** while everything else in the product that
  arrives as a set — the choices, the lesson cards — arrives one after another. They are
  now eighty milliseconds apart, the same interval the choices use.
- **Scene photographs snapped in** at full strength the instant they decoded. They now fade
  over a quarter of a second, driven from the image's own load event rather than from a CSS
  animation that would have finished before a slow picture arrived. An image that never
  arrives does not leave an invisible box.

## [v1.18.2] - 2026-09-11

### Changed — the screen got a band of its space back

- **"What these mean" no longer has a row of its own.** A closed one-line disclosure sitting
  under the top bar cost a whole band of every scene to say nothing. The question mark now
  rides at the end of the three readings it explains, and the panel opens over the scene
  instead of pushing it down — so closed, it costs nothing at all. The words are still there
  for a screen reader, and on a touch screen the mark gets a full 44px target.

### Fixed

- **The slide is now checked against the fold, not just sized for it.** The frame was worked
  out by measuring the chrome around it, which is arithmetic about a page the plugin has not
  seen — a theme bar it did not know about, or a top bar that wrapped onto a second line
  after the sum was done, and the bottom of the slide lands below the fold. After layout the
  slide's own bottom edge is compared with the bottom of the screen and the frame is pulled
  up by the difference before the text is scaled to it.
- The fit calculation no longer counts the legend twice, now that it sits inside the bar
  whose height already includes it.

## [v1.18.1] - 2026-09-11

### Fixed — narrow screens and touch

- **Tablet portrait got half of each layout.** The picture stops sitting beside the text
  below 900px, but the padding, the way a choice stacks and the rail label only changed
  below 720px — so everything between 721 and 899px, which is where an iPad in portrait
  sits, got the stacked layout with desktop spacing. One breakpoint now, at 899px.
- **The fixed slide frame needed height as well as width.** It was gated on width alone, so
  a short landscape screen produced a three-hundred-pixel box with hidden overflow and no
  way to reach what it cut off. Below 620px of viewport height the slide goes back to being
  as tall as it needs to be and the page scrolls normally.
- **Touch targets were drawn for a mouse.** The round controls in the top bar were 34px and
  the avatar on the scene 42px, against a 44px floor on both major platforms. On a touch
  screen they are 44 and 48, and the deck arrows are given room between them. Desktop
  chrome stays compact.
- **Two orphan breakpoints became one.** 560px hid the speaker hint and 600px moved the
  toasts, and neither lined up with anything else.
- **iOS moves its toolbars, which moves `vh` under a fixed frame.** The height in use comes
  from JavaScript measuring the real viewport, but the CSS fallback now uses the small
  viewport unit where the browser has one, so the frame does not jump as the page settles.

### Note

- The page-scroll lock was already refused below 900px and whenever the player does not fit,
  so a phone has never been locked. That is now covered by a test rather than by intent.

1115 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.18.0] - 2026-09-11

A full design and accessibility audit of the stylesheet, and the fixes that came out of it.
Three independent passes over 3,043 lines of CSS and every template.

### Fixed — defects

- **The commonest ending had no badge.** `mixed` is the fallback band and the default
  whenever an outcome word is not recognised, and it was the only one of the three with no
  style of its own: it fell through to the accent colours and read as a third brand tone
  beside the green and the red. It now has the neutral palette its siblings implied.
- **A verbose consequence could clip the way forward.** The card is held to the slide's
  frame with hidden overflow, so a long consequence pushed Continue out of the bottom and
  left the learner with no way on at all. The prose is what gives now; the rings and the
  button never move.
- **Five focusable controls had no focus style.** The avatar on the scene, every step of
  the wizard, every option toggle, the disclosure summary and the links out to the
  documentation. Moodle's own themes zero `button:focus`, so a keyboard user had nothing at
  all on the wizard's entire navigation. All five take the same ring the choice buttons
  already used — an outline, not a colour change, which fails for anyone who cannot
  distinguish the two colours.
- **Pressed and hovered looked identical** on an option toggle: both painted the same soft
  accent background, so there was no way to tell a hovered option from a chosen one.
- **The avatar had no hover state at all** — the one control sitting on a photograph, where
  a theme has nothing useful to fall back on.
- **Disabled buttons faded but did not restate their colour**, so a theme's own
  `button:disabled` won. The rest of the file already knew this; two rules did not.
- **Generated text could break its container.** A speaker called "Dr Amara
  Okonkwo-Ferreira" ran off the side of the scene image; a principle sentence in a chip
  stretched a pill wider than its card; a long metric label pushed the meters out of the
  sticky bar, which is on screen for the whole attempt; and a title with no spaces in it
  overflowed the masthead. None of those lengths is this plugin's to assume.
- **Reduced motion was half-applied.** Three hover lifts were dropped and four were not,
  and with the transition already at nothing those four snapped a pixel instead of easing —
  the jitter the setting exists to remove. The spinner was worse: the blanket rule froze it
  at whatever angle it had reached, so a generation running for minutes showed a stalled
  wheel. It keeps turning, slowly. And a scroll started from script ignores CSS entirely,
  so the preference is now read in the code as well.

### Fixed — consistency

- **Thirty-odd colour literals are now tokens**, including a soft red written out four
  times, a soft green that was one theme's accent border (so the "strong" badge matched
  the emerald theme and clashed with the other five), eight hard-coded whites — three of
  them `!important` — and an amber that was the only untokenised colour in a set of three.
  The toast, which lives on the body and cannot inherit the palette, now carries its own
  copy rather than eight literals.
- **Five sizes of secondary text became two**, and three sizes of uppercase micro-label
  became one. Twenty-six declarations were spread across `.82`, `.84`, `.85`, `.86` and
  `.88rem` for the same job.
- **Two label weights became one.** 650 and 700 are indistinguishable on a static font
  stack, which is what Moodle's default theme ships — so half the hierarchy was invisible
  at runtime.
- **Hard-coded radii became tokens**, including a card that changed its corner radius at
  one breakpoint while the card beside it did not.
- **A dead `min-width: 900px` block** whose every declaration was overwritten 500 lines
  later has been collapsed, so an edit there no longer appears to do nothing.

### Removed

- Nineteen dead rules for markup that no longer exists: the delta rows replaced by the
  progress rings in v1.10.0, the principle list replaced by the opening lesson in v1.14.0,
  and the swatches, which were never rendered at all.

### Testing

40 new checks covering every finding above, including a sweep that fails on any colour
literal outside the palette. 1101 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit
passing. CodeSniffer clean.

### Note

- **There is no dark mode**, and no `color-scheme` is declared. On a dark Moodle theme the
  activity is a light slab. Hoisting the literals onto tokens was the prerequisite; the
  palette itself is a deliberate next step rather than something to ship unseen.
- Breakpoints remain fragmented at 420, 560, 600, 720, 899 and 900. The 721–899px band —
  tablet portrait — gets the stacked layout with desktop padding. Worth one pass.

## [v1.17.0] - 2026-09-11

### Fixed

**The page could still scroll, and the frame was sized against a guess.** The space for a
slide was worked out as the viewport less the theme's header and this plugin's own bar,
less a flat 48 pixels for everything else. Everything else is the legend, the deck's
arrows and the card's own padding — between ninety and a hundred and fifty pixels
depending on the screen — so the frame was sized for space that was already spoken for and
the page scrolled by exactly the difference.

- Every one of those is now **measured**, margins included, rather than allowed for. Heights
  do not move with the scroll position, so it is right whether the page is at the top or not.
- The space is **worked out again on every screen** rather than once at load, because what
  else is on the screen changes: the legend is on a scene and not on a debrief, the arrows
  are on a deck and not on a decision.

### Added

- **The page is locked against scrolling in the learner's view.** A scenario is a player,
  not a document: a learner should never be scrolling to look for options that are already
  on the screen. Two guards, because a locked page with something unreachable on it is far
  worse than a page that scrolls — the lock is taken only above the breakpoint where the
  side-by-side slide applies, since a narrow screen stacks and is meant to scroll, and only
  while the player genuinely ends inside the viewport. A short window or an unusually tall
  header hands it straight back. It is reconsidered after every screen and on every resize.

1061 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.16.1] - 2026-09-11

### Fixed

**The plugin's bar was parked underneath the course banner, and the slide height was
measured against the wrong number.** Pinned furniture stacks: a theme's navbar takes the
first sixty pixels or so, and a course format's banner is pinned to the bottom of the
navbar rather than to the top of the window. Measured against format_aicourse 2.3.3, whose
hero is `position: sticky` and runs from y 61 to y 181 on an activity page, this plugin
was probing only the very top edge — so it found the navbar, never saw the banner at all,
and both the sticky bar and the slide ceiling were 120 pixels out.

The strip is now walked downwards from the top: anything fixed or sticky that touches the
band already claimed extends it, and the walk stops at the first gap, so a pinned button
halfway down the page is not mistaken for a header. A pinned stack may now claim half the
viewport rather than a third, because a navbar and a banner together exceed a third on a
laptop.

1049 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

### Note

- Below 992px the AI course format un-pins its banner, and it un-pins under
  `prefers-reduced-motion` as well. The walk measures what is actually painted, so both
  cases are handled without knowing anything about that format in particular.

## [v1.16.0] - 2026-09-11

### Fixed

**The opening lesson was not laid out as a slide.** The ceiling added in v1.13.0 was a
maximum, not a height, so each card shrank to fit its own contents: a slide a third the
height of the space it had been given, a picture in a letterbox, three lines of text pinned
to the top-left of a tall empty column, and the page still scrolling underneath it.

- A slide is now a **fixed frame**. Every slide in the deck is the same height — the space
  between the course banner and the fold — so moving through them does not resize the page
  under the reader. That includes the slides with no picture.
- The **picture fills its half** of the frame instead of sitting in a letterbox with white
  space above and below it.
- The **text is centred in its half** rather than pinned to the top, with room to breathe
  between the label, the heading and the prose.
- **The type scales with the frame**: the heading from 1.45rem to 2.05rem and the body from
  1rem to 1.14rem, with the prose held to a 62-character measure so a wide screen does not
  produce lines nobody can track.
- A **chip no longer stretches** the width of the column. In a flex column it had been
  filling the whole row, which is why the label looked like a banner.
- **Every kind of slide is fitted**, not only the scenes: the opening slide, the lesson
  slides and the closing slide all step their type down if they would otherwise overflow.

### Changed

- "What good looks like 1" is now "Principle 1". It was a label describing nothing.

1043 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.15.3] - 2026-09-11

### Fixed

- **"Usually takes 20 seconds to 30 seconds on this site" was untrue.** The range was
  measured from real runs, but from the wrong half of the job: only the scenario call is
  timed, and the pictures and the narration are generated afterwards by a scheduled task.
  A teacher was quoted half a minute and then waited several. A quoted number a site
  routinely overshoots is worse than no number, because the teacher concludes the thing has
  hung and presses the button again. It now says **"Can take up to 5 minutes to
  generate."** — one sentence, an upper bound, no arithmetic.

### Removed

- `generator::estimate()`, its three constants and the four strings that phrased the range.
  Nothing else used them.

1036 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.15.2] - 2026-09-11

### Fixed

Two fields the player renders were missing from the prompt entirely, found by comparing
what the prompt teaches against what the validator keeps rather than by reading it.

- **"facilitatorspeech" was not in the shape.** It is the one line somebody says out loud
  on a scene, shown to the learner as speech, and the most concrete thing on the screen. It
  appeared in the rules only inside a crisis variant, so a scenario written to this prompt
  had spoken lines only on the one or two crisis scenes and none anywhere else.
- **"speaker" was in neither the shape nor the rules.** It names who says that line, and it
  is what gives a character their own voice and puts their face on the picture to click.
  Without it every line is read by the narrator — which is the feature added in v1.10.0
  working exactly as designed on input that never arrives.

### Testing

The audit is now a test rather than a one-off: the prompt's shape is filled in, validated,
and its key set compared with the normalised document in both directions. Nothing the
prompt teaches may be silently discarded, and every field the shape omits must be one the
plugin derives for itself, named explicitly. A new field on either side fails the check
until both are updated.

1042 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

### Note

- `bottleneck` on a node is accepted, stored and read by nothing. It is not asked for in
  the prompt, which is correct while that remains true.

## [v1.15.1] - 2026-09-11

### Fixed

The authoring prompt asked for fields its own worked shape did not show, which is how an
assistant comes back with a document missing them.

- **The shape now shows a principle's "example" and "pitfall".** The rules demanded both
  and the skeleton showed neither, so an assistant copying the shape produced principles
  with no worked example — and the opening lesson had nothing to teach from.
- **The shape now shows "crisisvariant" and "tags"**, both of which were rules-only.
- **The rule about quotation marks is now the first rule**, rather than the last line of
  the writing advice. It is the single most common reason a pasted document is refused, so
  it belongs where an assistant reads it first.
- **The rules now require one ending per band**, and say what a missing one does: the
  learner who earned that band is silently shown somebody else's ending. "Between two and
  four outcome nodes" permitted exactly that.
- **The abbreviated worked node now says what it leaves out.** It omits "principleid",
  "effects", "skills" and "next" to show the writing register, and an assistant copying it
  produced choices with no skill values — which score zero, making the decision count for
  nothing.

### Testing

17 new checks. The prompt's own shape is now parsed out of the prompt, filled in, and run
through the validator, so a rule and the shape it describes cannot drift apart again.
1037 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.15.0] - 2026-09-11

Three separate faults, each of which rejected a pasted scenario whole.

### Fixed

- **The quote repair did not cover the commonest case.** v1.14.1 escaped a quotation mark
  unless a comma followed it — but `"Great, we're all agreed then", which invites` is
  prose, and that comma is inside the sentence. A quotation mark is now treated as closing
  only when what follows it is something JSON actually allows there: a colon, a closing
  brace or bracket, the end of the document, or a comma followed by the start of another
  value or key. A comma followed by an ordinary word is prose, and that quote is content.
- **`"auto"` was not recognised.** An assistant told the activity can pick the next stage
  writes `"next": "auto"`, which is not a node id, so every scene after it was unreachable
  and the scenario was refused. Both spellings are accepted.
- **And the automatic target went to the wrong place.** It resolved straight to the ending
  the learner had earned, so a scenario whose choices all say "auto" — exactly what the
  authoring prompt asks for — sent the learner from decision one to the debrief, and the
  validator refused it because every later scene was unreachable. It now means the next
  stage where there is one, and the earned ending where there is not. The closing beat,
  which has nothing after it, behaves exactly as before. The progress rail and the
  reachability check both follow the same rule, so what is reachable and what is actually
  reached cannot drift apart.

### Testing

13 new checks built from the document that failed, including every hostile construct in
it — a doubled quote around a whole example, a quote before a comma mid-sentence, a quote
inside a choice's own text and inside a situation — plus the five-stage "auto" chain.
1020 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.14.1] - 2026-09-11

### Fixed

- **"The scenario definition was not valid JSON" on a document that was almost fine.** An
  assistant writing a line of speech into a value produces `"example": ""Have I got that
  right?""`, and an unescaped quotation mark ends the string early and takes the whole
  document with it. It is now repaired on import: after a decode fails, a quotation mark
  inside a string is escaped unless the next thing that matters is a comma, a colon, a
  closing brace or bracket, or the end of the document. A well-formed document never
  reaches that code and is never touched by it. The quoted speech is kept, quotation marks
  and all.
- **And when it cannot be repaired, the message says where to look.** "Not valid JSON" is
  nothing to act on in eight hundred lines. The first line with an unbalanced number of
  quotation marks is named and quoted back.
- The authoring prompt now warns about this directly, since examples and spoken lines are
  exactly where it happens, and shows the escaped form.

1007 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.14.0] - 2026-09-11

### Added

- **The opening page is a lesson, not a list.** What a learner used to get was the
  principles as bullet points above a Begin button - read in four seconds, remembered for
  none of them. It is now a deck in the same shape as the scenario itself: a picture on the
  left, the teaching on the right, one principle to a slide, with the situation first and a
  closing slide carrying the start button.
- **Worked examples.** Each principle can now carry the words a learner could actually say
  or do, and the plausible-sounding version that does not work. Both are shown as their own
  cards - "Sounds like this" and "Not this" - because being told a principle and being
  shown how it sounds are different things. The authoring prompt now requires both.
- **The opening lesson is narrated**, one clip per principle. Never more than eight, so it
  is a small addition to the speech bill.
- **The pictures are the scenario's own.** Scene images already generated are reused in
  order, cycled if there are more principles than scenes. Nothing extra is generated.
- **The cards arrive in sequence and lift under the pointer.** Both stop for a learner who
  has asked for reduced motion.

### Note

- A scenario published before this release has no examples and no lesson narration. The
  lesson still runs, teaching each principle from its summary; generating the scenario
  again fills in the rest.

996 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.13.1] - 2026-09-11

### Changed

- **The debrief is a deck rather than one long page.** It carries the outcome, four skill
  scores, every decision with its consequence and reasoning, the lessons, the practice
  notes and the takeaway cards - which on a single page is a scroll a learner abandons
  half way through. It is now one page at a time with arrows and a position count:
  outcome, skills, the decisions two to a page, what mattered, how to apply it, the
  takeaways, and a closing page carrying Try again and Print. Printing still puts the
  whole thing on the page.
- **The debrief is not narrated.** It is read rather than listened to, so reaching it stops
  whatever the last screen was playing and puts the narration control into its "nothing
  here" state instead of leaving it claiming to be playing.

976 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.13.0] - 2026-09-11

### Added

- **A first step that answers "what is this and what does it cost" before asking for
  anything.** The cost card has moved off every page of the wizard and into step one,
  alongside six cards on what a scenario does to a learner that a document cannot - each
  claim with the mechanism under it rather than an adjective - and a six-step guide to
  building one. The sixth step is the one people were missing: publishing does not change
  the authoring page, and the scenario has to be opened in student view to be seen as a
  learner sees it.
- **Links to the documentation, the published prices and the voice samples**, built from
  the configured service host so a site pointed at staging does not send its teachers to
  the live site.
- **A fill-the-screen button** in the player's top right, with the sticky bar and the slide
  ceiling both re-measured on the way in and out.

### Fixed

- **The slide was taller than the screen.** Whichever column was longer set the height, so
  a wordy scene pushed its own options below the fold - exactly what the side-by-side
  layout existed to prevent. The available height is now measured in the browser and
  handed to the layout, the picture keeps to it, and the text column takes a scrollbar of
  its own rather than pushing the page.
- **The sticky bar let the page show either side of it.** It is only as wide as the player,
  and a course banner behind it was visible in the margins as content scrolled under. The
  bar now paints its background out past both edges.

- **A slide could still scroll.** Now nothing on a scene scrolls at all: the screen is held
  to the height between the course banner and the fold, and when the text does not fit the
  type steps down until it does, stopping before it becomes unreadable. A scene that wants
  more room than the page has is what the fullscreen control is for.
- **The player's top corners squared off while the bottom two stayed round.** The sticky
  bar paints its background past both edges so a course banner does not show either side of
  it, and that flat band was being worn at rest as well as when pinned. It is only worn
  while the bar is actually stuck.
- **The narration control looked the same on and off.** Sound was a two-pixel arc and
  silence a two-pixel bar, in the same 24px circle. Each of the four states now has its own
  drawing: a speaker with waves, a speaker with a cross, a play mark, and a speaker with a
  dash for a screen that has no recording.

### Changed

- The wizard has seven steps rather than six. A bookmarked `?step=n` link lands one step
  earlier than it used to.

959 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.12.3] - 2026-09-11

### Changed

- The cost card no longer puts a "Your settings" badge on the row that applies. The row is
  already picked out, and the same figure is already in the heading; a third marker on the
  same line was noise.

920 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.12.2] - 2026-09-11

### Removed

- **Six settings a site should never have had.** A customer's Moodle does not get to set
  what that customer is charged, and it does not get to decide how much of a scenario is
  narrated. Price, the credits-to-currency rate, the currency and the narration scope are
  all LMS Labs decisions and are now fixed in the plugin: 100 credits ($10 USD) for a
  scenario, 150 ($15) with images or narration, 200 ($20) with both. Any values a site had
  already set are removed on upgrade rather than left in the config table.
- Consequence screens are now always narrated. The setting that turned them off existed to
  save money, and money is not the site's to save; the saving that stays is the one that
  costs nothing, which is not recording a beat's "continue to the next decision point".

918 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.12.1] - 2026-09-11

### Fixed

- **Release pipeline blocker.** The four price settings were accepting `PARAM_RAW_TRIMMED`,
  which is the wrong type for a number and is refused on review. They are `PARAM_INT` now,
  with the credits-to-currency rate as `PARAM_FLOAT`, so a value that is not a number is
  refused where it is typed rather than quietly falling back when it is read. The
  validation on the reading side is kept: a setting can still be wrong in the database,
  and a price that falls back is better than a price of nothing.
- Two multi-line `mtrace()` calls in the scheduled tasks put their first argument on the
  same line as the opening parenthesis. Style only.

918 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.12.0] - 2026-09-11

The version number this run of work is published under. No code changes from v1.11.1:
the slide player, the character voices, the click-to-hear avatar, the outcome cues, the
progress rings, the pricing card and the narration savings are all as described in the
three entries below.

918 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.11.1] - 2026-09-11

### Changed

- **The price is quoted in credits, with the cash beside it**: 200 credits ($20 USD) for a
  scenario with images and narration, 100 credits ($10 USD) for the scenario alone, 150
  credits ($15 USD) with either one. Credits are what a balance is held in and what is
  actually deducted, so they lead; the conversion rate is a setting, so a site can quote
  its own currency.
- **One credit figure at the moment of spending, not two.** The confirmation used to show
  the price and, beside it, what the run costs this site to produce. Those are different
  numbers and the difference is not a teacher's business. The price is now the only credit
  figure shown, and it is what the balance is tested against; the production cost is still
  calculated and is still returned to the plugin, just never displayed.

### Testing

918 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing. CodeSniffer clean.

## [v1.11.0] - 2026-09-11

A price on the thing, said out loud before the button is pressed.

### Added

- **What a scenario costs, on the wizard.** All four prices are shown before anything is
  filled in, with the one matching the current settings marked: scenario alone $10 USD,
  with scene images $15, with narration $15, with both $20. The same price leads the
  generation confirmation, with the number of images and narration clips it covers.
- **Prices are site settings.** Base, images and narration are three amounts that add up,
  with a currency code, so a partner reselling this can set their own. These are prices,
  not the LMS Labs credit tariff: credits are what the service charges the site, and these
  are what the site charges for the finished scenario. Both are now shown at generation
  time, which is the only place they meet.
- **How much is narrated is now a choice.** Scenes and character lines only, or everything
  including every branch of every decision. Narration is most of what a scenario costs to
  generate, and the cheaper setting is the default.

### Changed

- **Two narration savings that cost nothing.** A beat's single "continue" consequence is
  filler and is no longer recorded at all. A spoken line with no named character behind it
  would have been read in the narrator's voice anyway, so it stays inside the narrator's
  clip instead of becoming a second one; only a named character with a gender on record
  gets a clip of their own.
- The clip estimate shown to a teacher is now the number actually generated, rather than
  one per decision, which was wrong in both directions.

### Testing

41 new checks, 912 in total on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing.
CodeSniffer clean.

### Note

- A five-decision scenario with three choices at each: about 40 clips before this release,
  about 25 with everything narrated, about 10 on the default setting. The remaining lever
  is generating a consequence clip the first time a learner actually reaches it rather than
  generating all of them up front, which would cut the typical bill by roughly two thirds
  again at the cost of a wait on first play. Not built.
- The credit tariff in `schema::tariff()` still does not match the route's own source.
  That is a separate question from the prices above.

## [v1.10.0] - 2026-09-11

Everything still outstanding from a full playthrough, in one release.

### Added

- **The slide player.** A decision is now the picture on the left and the decision on the
  right, so the options are on screen with the scene rather than below the fold.
- **Characters have their own voices.** Three settings — narrator, male character, female
  character. The narrator reads the situation; a character's line is its own clip in the
  voice that matches the gender recorded for them, and falls back to the narrator when the
  scenario names nobody. A site that had set a voice before keeps it as the narrator.
- **Click the avatar to hear what they are thinking.** The speaker appears on the scene
  with their initial, their name and a play mark, and the ring around it keeps pulsing
  until it has been played.
- **The way on waits for the screen to be heard.** Continue is held until the narration and
  the character's line have both finished. Only Continue: the lettered options are never
  taken away from the learner. Muting, a browser that refuses to play, or a screen with no
  recording all hand it straight back.
- **Success and failure cues.** A short rising cue on a well-judged screen, a falling one
  on a costly screen, nothing on a mixed one. Synthesised in the browser, so the plugin
  still ships no audio files, and it follows the mute control.
- **Progress rings.** The consequence screen's rows of numbers are now three traffic-light
  rings that fill from where the metric was and count to where it is. Every metric is
  shown, including the ones that did not move. Neither animation runs for a learner who has
  asked for reduced motion.

### Fixed

- **Costly and well judged used the same tick.** Each signal now has its own mark: a tick,
  a dash, a warning triangle.
- **Scene images were generic.** The brief was throwing away every quoted line — the most
  concrete thing on the page — and never mentioned what the scene was. It now carries the
  spoken line as described speech and names the moment.
- **Images came back dark.** Photorealistic is now the default style rather than cinematic,
  and both photographic styles ask for bright, evenly lit, true-to-life colour.
- **Print or save.** The handler now catches a frame that is not allowed to open a print
  dialogue, tries the page it sits in, and says plainly when the browser refuses. I could
  not reproduce the original failure, so this is a defence rather than a confirmed fix.

### Testing

57 new checks, 871 in total on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit passing.
CodeSniffer clean.

### Note

- Narration now costs roughly four times what it did: a five-decision scenario with three
  choices at each goes from about 10 clips to about 40. The tariff needs a decision before
  this ships.
- The content itself — tension, characters who say what they think, feedback that gives the
  mechanism — and the spelling of the chosen language are generated by the LMS Labs service,
  not by this plugin. The brief for those is delivered alongside this release.

## [v1.9.2] - 2026-09-11

Five things a learner sees on a real playthrough, all reported from the same scenario.

### Fixed

- **A well-judged choice was showing a red penalty.** Engagement +12 and Trust +12 came up
  green while Tension -8 came up red on the same screen, because the change was coloured by
  its sign. Tension is the one metric whose good direction is downwards. It is now coloured
  by whether the learner gained or lost, and the number is spelled out as "reduced by 8" or
  "raised by 6" rather than left as a bare signed figure to be read as a score.
- **The question above the options was not a question.** Authors write the challenge line
  as an instruction as often as not, and "Ensure Jamie understands the project guidelines."
  in bold directly above A, B and C read as the first of them. When the line does not end in
  a question mark it now steps back to context and the player asks "What do you do?" in its
  place. The authoring prompt also now requires that line to be a question.
- **The debrief numbered the Continue presses as decisions.** A beat has one way on, so its
  event recorded a press of Continue; listed among the decisions it produced entries reading
  "Continue to the next decision point" with nothing to say about them, and pushed the
  numbering out of step with the decision count printed above the list. Only decisions are
  listed now, numbered from one.
- **Narration stopped on every second screen.** Only the decision screens had a clip, so the
  voice cut out on each consequence while the control still said "Narration on". Consequence
  screens are now narrated as well, one clip per branch since which one is heard is not known
  until the learner chooses. A screen with no clip puts the control into its "nothing to play
  here" state rather than claiming to be playing.

### Note

- Consequence narration is new audio, not new storage: a scenario published before this
  release has no clip for its branches and stays silent on those screens until it is
  generated again.
- It also costs more to generate. A five-decision scenario with three choices at each goes
  from about 10 narration clips to about 40. That is a pricing decision, not a technical one,
  so the per-operation tariff needs a look before this ships.

## [v1.9.1] - 2026-09-11

### Fixed

- **A narrative beat looked like a multiple-choice question with one answer.** A beat has
  one synthesised way on, and it was being rendered in the lettered choice list, so
  "A — Move forward to the next decision point" read as an option to weigh rather than a
  way on. It is now a single centred button with no letter.
- **The chosen language now decides the spelling.** The authoring prompt said to write in
  "the language and spelling of the source content", so a teacher who chose Australian
  English got "Finalizing the Meeting" whenever the material they pasted was American. The
  rule now names the chosen variety as the one that governs — every word of the scenario,
  not just its prose — with the -ise, -our and -re conventions spelled out and mixing them
  forbidden.

### Testing

11 new checks: a beat is marked as one way on and a decision is not, the template renders
it as a button outside the choice fieldset and without an option letter, and the prompt
states that the source content's own spelling does not get a vote, names the conventions,
gives worked spellings and forbids mixing them.

786 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit 45 tests / 283 assertions passing.
CodeSniffer clean.

### Note

- The spelling rule applies to the draft-elsewhere prompt, which is this plugin's. Scenario
  generation uses the service's own system prompt, so the same rule has to be added there
  before an AI-generated scenario respects it.

## [v1.9.0] - 2026-09-11

The first release driven by watching a real generated scenario being played.

### Added

- **The room dynamics explain themselves.** A quiet "What these mean" opens a panel saying
  what engagement, trust and tension are, what raises and lowers each, that they are not
  the grade, and what the grade actually comes from. It says plainly that there is no right
  answer to find — there are choices that land and choices that cost you something.
- **Every wizard step says what it is for** before it asks for anything.
- **Generation says how long it usually takes**, measured from this site's own completed
  runs rather than a number written into the code, with the extremes trimmed once there are
  enough samples. Until a site has three successful runs it uses the shipped estimate and
  does not claim to know.
- **Confirmation now appears where the button is.** Moodle's notifications render at the
  top of the document; the wizard's buttons are at the bottom of a long form, so Publish put
  its success message off screen and the button read as broken. A toast appears beside the
  action as well. Moodle's notification is still raised, not replaced.

### Changed

- **The top bar is half the height and stays put.** Title, setting, subtitle, progress and
  the three dynamics were five stacked blocks costing about 300px at the top of every
  scene; they are now one strip of about 135px that sticks while the scene scrolls under
  it. It parks itself below whatever the site theme has pinned above it, measured at
  runtime, because no theme publishes its header height.
- **Narration is a control rather than a line of text.** It was a status label that happened
  to be a button, which is why it read as "narration is off" rather than "turn narration
  off". It now has an icon, hover text and four honest states.
- Moving to a new scene or a new wizard step now lands the content below the theme's header
  rather than behind it.

### Fixed

- **A refused autoplay was being swallowed.** Browsers will not start sound before the
  person has interacted with the page; the rejection was caught and discarded, leaving a
  silent player and a control claiming narration was on. That is almost certainly why
  narration was never heard. The refusal is now visible, and the control becomes the way to
  start it.
- **A choice with no consequence is refused.** The generator returned one, and the learner
  was shown a signal word, a Continue button, and nothing else — a screen that says nothing
  about what their decision did. A blank screen is worse than a rejected scenario, because
  nobody finds out.
- **Decision buttons keep their own colour in every state.** A theme shipping
  `button { color: #fff !important }` turned resting choice text white on white, measured at
  a contrast of 1.0. The hover work covered hover and focus and left rest, chosen and
  disabled to be inherited, which is how the same fault came back wearing a different state.

### Changed — authoring prompt

- The draft-elsewhere prompt now demands the mechanism rather than a restatement. "You
  addressed Jamie's confusion directly" tells a learner nothing they did not just read;
  feedback must say what the behaviour did to the named person and what that changes next.
  The worked examples were rewritten to model it, since a rule the examples ignore is
  advice.

### Testing

A new probe measures the decision buttons in every state a learner can put them in —
resting, hover, chosen and disabled — against four hostile theme rules. The hover probe ran
only on the authoring wizard, so the player's own buttons had never been measured at all,
which is exactly where the fault was. Resting and hover now read 17.43 and 15.36 under every
rule tried, including a blanket `!important`.

775 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit 45 tests / 283 assertions passing.
CodeSniffer clean.

### Note

- **The chosen state is defended but not measured.** The probe cannot catch it: choosing
  replaces the whole block within a few hundred milliseconds. The fix restates colour and
  background on that state the same way as the others, but it has not been observed under a
  hostile rule, and should not be described as verified.

## [v1.8.1] - 2026-09-11

**A generated scenario reached the plugin for the first time, and the plugin rejected it.**
Reported live as *"Node n10 does not say what follows it."*

### Fixed

- **A narrative beat could not lead to the ending the learner had earned.** A decision's
  choices have always been allowed to target `__auto__`, the automatic outcome. A beat's
  onward link was not: it went straight through the identifier filter, which requires a
  leading letter or digit, so `__auto__` was reduced to an empty string and the beat was
  reported as having no successor. Every ending behind it then became unreachable, which is
  why one node's link took the whole scenario down.

  This is precisely the shape a server-built topology produces for its last scene — a
  closing beat that hands the learner to whichever ending they earned — so the first
  scenario the rebuilt generator managed to produce ran straight into it.
- **Both kinds of link now go through one helper.** A beat and a choice can no longer
  disagree about whether the automatic target is a legal destination, which is the
  disagreement that caused this.

### Testing

11 new checks, reproducing the reported failure from the wire format the service actually
sends: the mapper carries the auto target onto the beat, the beat validates, it is not
reported as having no successor, the endings behind it are reachable, the normalised form
keeps the target on both the node and its player-facing choice, and the whole thing
validates twice.

The boundaries are checked too, because widening a filter is how things get let in by
accident: a decision choice may still target the earned ending; a node may **not** take
`__auto__` as its own id; and a link that is merely malformed is still refused. One of the
new checks asserts that `__auto__` would not survive the identifier filter on its own, so
the test stops being meaningful loudly rather than silently if that ever changes.

695 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit 45 tests / 283 assertions passing.
CodeSniffer clean.

### Note

- Nothing to migrate. A working copy rejected before this release was never stored; generate
  or import again on 1.8.1.

## [v1.8.0] - 2026-09-11

The first release whose packaged files all agree on what version they belong to.

### Fixed

- **The README claimed release 1.0.0 for seven consecutive releases.** Nothing caught it,
  because the version a person reads lives in a different file from the one Moodle reads,
  and only the second was ever checked. It now states the release it ships with.

### Added

- **The suite now checks that the packaged files agree with each other.** The README's
  stated release and numeric version must match `version.php`; the changelog must carry an
  entry for this release *and* for the declared previous one; the upgrade path must reach
  this numeric version; and the previous release must not be this release. The first run of
  these checks failed, because this entry did not exist yet — which is the point of them.

### Note

- No functional change since v1.7.2. Everything below the surface is identical; this
  release exists so that the archive, the documentation and the plugin's own metadata tell
  the same story, which is what the release pipeline's documentation gate is asking for.

### Testing

684 checks on Moodle 4.4.12, 4.5.13 and 5.2.2. PHPUnit: 45 tests, 283 assertions, passing
on 5.2.2 with PHP 8.4. CodeSniffer clean. Behat still not executed — 5 scenarios and 69
steps verified structurally only.

## [v1.7.2] - 2026-09-11

**The PHPUnit suite had never been run.** It was written, committed and reported as
"not run" for the life of the project, on the belief that the sandbox could not install
it. That belief was stale. On its first execution it failed 10 of 45 tests, and one of
those failures was a real defect in the permission code.

### Fixed

- **Every permission check in the external API was unreachable.** `helper::resolve()`
  called `require_login()` before `external_api::validate_context()`. The latter resets the
  page theme *before* it calls `require_login` itself, which is what makes it safe from a
  web service; calling `require_login` first sets the page course against a theme that may
  already be initialised, and Moodle answers with a coding exception. That exception was
  thrown before `require_capability()` was ever reached — so the two tests asserting that a
  student cannot publish or queue a generation were passing without testing anything. The
  explicit call is removed; `validate_context()` performs the login check, as it is
  designed to.
- **The external API documented nine fields as "escaped paragraphs" that are not
  escaped.** The values are plain text, escaped at render by Mustache, which is the correct
  design — escaping in the data layer would show a teacher who writes "10 < 20" the string
  "10 &lt; 20". But an integrator reading the published contract would reasonably have
  inserted them with `innerHTML`. They are now documented as plain text, with escaping
  named as the caller's responsibility.
- Two tests referenced code that had been renamed out from under them —
  `helper::paragraphs()` (now `paragraph_list()`, returning an array) and a
  `consequencehtml` field (now `consequenceparas`). Nothing caught it because nothing ran.

### Changed

- The cross-site-scripting test now asserts at the layer that matters. It checks that the
  stored payload survives verbatim, that rendering it the way the player renders it
  neutralises the markup, and — newly — that no template in the plugin uses a triple
  mustache, since that is the single change that would turn stored text back into live
  markup.

### Testing

**PHPUnit: 45 tests, 283 assertions, all passing** on Moodle 5.2.2 with PHP 8.4. The five
reported deprecations are `@covers` doc-comment annotations, which PHPUnit 11 would rather
see as attributes; they are kept because Moodle 4.4 and 4.5 ship a PHPUnit that does not
support the attribute form, and this plugin supports 4.4.

**Behat: 5 scenarios, 69 steps, every step resolving to a definition.** The features cannot
be executed here — the only ChromeDriver in the environment is major version 147 against a
Chromium 141, and the Chrome for Testing download host is blocked — so they are verified
structurally, not behaviourally. That gap is real and is recorded rather than papered over.

675 harness checks on 4.4.12, 4.5.13 and 5.2.2, CodeSniffer clean, browser pass and 31 UI
checks green on all three.

## [v1.7.1] - 2026-09-11

**Any scenario containing a beat could be saved and then refused on its way to learners.**
Found by building a scenario by hand and trying to publish it, which is a thing nobody had
done before today.

### Fixed

- **The validator could not validate its own output.** A beat's onward link is read from
  the node, but was written only into the synthesised choice, so the normalised form no
  longer carried it. Publishing re-validates the stored working copy — and the second pass
  said every beat did not know what followed it, and everything after the first beat was
  unreachable. A validated definition is now stable: validating it again returns exactly
  what the first pass returned.
- A beat whose link arrives only inside its choices, which is how the service sends them,
  is normalised the same way, so the 1.6.4 mapper fix and this one agree.
- The beat's continue label survives normalisation instead of reverting to "Continue".

### Added

- **A worked example ships with the plugin**, at `examples/scenario-active-listening.json`.
  Paste it into the import box to get a complete, playable five-decision scenario without
  spending a credit or waiting on the generator. It is a workplace active-listening
  scenario, twelve nodes, three endings, and its skill deltas are set so that all three
  endings are provably attainable: best path 87.5%, middle 62.5%, worst 25%.
- The example is covered by the test suite, so it cannot rot: it must validate, validate
  twice, raise no quality warnings, and offer one ending for each band.

### Testing

15 new checks. The round trip is asserted as an invariant rather than a special case —
validate twice, compare, and require the second result to equal the first — because that is
the property publishing depends on. The normalised beat is checked to keep its link, its
label and its single player-facing choice.

One of the new checks was skipped in silence on Moodle 5.2, where the code lives under
`public/` and the harness had assumed it sat beside its own directory. The suite now
resolves the path through `dirroot` and asserts the example is present, so a missing file
fails rather than disappears. 675 checks pass on Moodle 4.4.12, 4.5.13 and 5.2.2,
CodeSniffer clean.

### Note

- Existing working copies need no migration. A stored definition is re-validated on save
  and on publish, which repairs it in place.

## [v1.7.0] - 2026-09-11

### Added

- **Every activity default is now a site setting.** An administrator sets how new AI
  Branched Scenario activities should start, once, under the plugin's settings, and every
  activity created afterwards opens that way. Covered: accent theme, scenario language, the
  progress rail, room dynamics and debrief toggles, scene images, narration, attempts
  allowed, replay, grading method, whether the learner must finish, minimum decision
  quality, and grade to pass.

### Changed

- **The shipped defaults now match how the plugin is actually used.** Scene images and
  narration are on, attempts are 3 rather than unlimited, grading takes the highest attempt
  rather than the last, the learner must finish the scenario, minimum decision quality is
  100 and grade to pass is 100. Previously images and narration were off, attempts were
  unlimited, grading took the last attempt, and neither completion rule was set.
- The activity name still has no default, so a new activity opens with an empty title.

### Note

- **A minimum decision quality of 100 and a grade to pass of 100 mean only a faultless run
  completes or passes the activity.** That is deliberate and is what these defaults ship
  with, but it is strict enough that both settings carry an explanation saying so on the
  settings page. Lower either one if completion should be reachable on a good but imperfect
  attempt.
- Existing activities are untouched. A default applies when an activity is created, never
  afterwards.

### Fixed

- **A failed generation the service refunded was still spending the teacher's own daily
  allowance.** The allowance counted every job, whatever became of it, so three scenario
  generations the service failed and refunded cost sixty credits of a teacher's budget for
  work nobody was billed for — and a teacher whose generations were all failing reached the
  daily limit fastest of all. A failure the service reports as refunded no longer counts. A
  generation the service completed and charged for still does, even when this plugin then
  rejects the scenario, because those credits were spent whatever happened next.

### Testing

11 further checks for the allowance: a refunded service failure spends nothing, several in
a row still spend nothing, a scenario charged for and rejected locally still counts, suggest
and populate are weighted by their own tariff, and the figure shown to the teacher is the
same one the gate enforces — checked at the boundary, where a refusal must report the spend
the teacher can actually see.

23 new checks for the defaults: every one of the thirteen settings falls back to its shipped value when the
site has never saved one, and every one is registered on the settings page — verified by
running the settings file and recording what it adds, rather than searching its source, so
the five registered in a loop are covered like the rest. A saved site value wins over the
shipped one. Zero is honoured rather than mistaken for unset, which matters because zero is
a real answer for both attempts and every toggle. The activity name is confirmed to carry
no default, every setting is confirmed to have a label, and the two strict settings are
confirmed to explain what 100 means. 660 checks pass on Moodle 4.4.12, 4.5.13 and 5.2.2,
CodeSniffer clean.

## [v1.6.4] - 2026-09-11

**This release fixes the fault that has been rejecting generated scenarios since the
plugin was first pointed at the service, and it was this plugin's, not the generator's.**

### Fixed

- **A scene the service sent as `content` took the whole scenario down with it.** The
  mapper turned every `content` node into a `beat`. A beat is validated on a node-level
  `next` and its choices are discarded — but nothing on the wire carries that field, since
  the service puts every onward link inside the choices. So the node arrived with nowhere
  to go and was refused with "does not say what follows it". When the node it happened to
  be was the first one, every other node became unreachable and the scenario was rejected
  entirely. That is exactly the pair of errors recorded against the failed jobs on
  10 September.

  A beat now takes its onward link from a node-level `nextNodeId` where a service supplies
  one, and otherwise from the choice it came from, along with that choice's text as its
  continue label.
- **A `content` node carrying real alternatives is now mapped as a decision.** Converting
  it to a beat discarded its choices, so a branch the generator had written was silently
  thrown away. A node with two or more alternatives is a decision whatever the service
  called it.
- A beat that genuinely has nowhere to go is still refused. The fix supplies a missing link
  from the choices; it does not invent one.

### Credit

Found by the LMS Labs Replit agent while reviewing its own graph-validation work: it
noticed the mapper converts `content` nodes to `beat` but may not supply the node-level
`next`, and flagged it as worth checking independently. It was right, and it was the
answer.

### Testing

12 new checks covering the original failure end to end: the wire shape that failed on
10 September now validates, and neither "does not say what follows it" nor "cannot be
reached" appears; an explicit node-level link wins over the borrowed one; a content node
with alternatives becomes a decision with none of its choices lost; and a content node with
no choices at all still has no link to borrow and is still refused. 602 checks pass on
Moodle 4.4.12, 4.5.13 and 5.2.2, CodeSniffer clean.

### Note

- No stored scenario can be holding the bad shape: the scenarios affected were refused at
  validation and never written. Nothing needs migrating.

## [v1.6.3] - 2026-09-10

The graph validator asks whether a scenario holds together. It does not ask whether the
scenario is worth playing, and a generator that has just been taught to connect its nodes
is exactly the generator that connects them all to the same place.

### Added

- **The draft review page now names decisions that change little.** Six things are
  reported: every choice on a node leading to the same next scene; skill scores that are
  identical across all choices, or all zero; two choices that say the same thing; two
  choices with the same consequence; a decision node offering a single answer; and a
  scenario where every route ends at the same outcome. Each is listed against the scene it
  belongs to, above the scenes themselves.
- **These are advisory and never block publishing.** A slightly flat scenario is still the
  teacher's to publish, and refusing it here would only send them back to a generator that
  would do the same thing again. The panel is styled as a note, not an error. The point is
  that the teacher meets a decorative decision before a learner does.
- A beat with one way forward is left alone — only a `decision` node with a single choice
  is reported, because a beat legitimately has one continuation.

### Fixed

- **A failed job recorded its duration as zero.** The column was written only on success,
  so the jobs table showed every failure as instantaneous while the real figure — 62,560 ms
  on the generation investigated today — sat inside the diagnostic JSON where no report
  could reach it. `fail_job()` now records duration and model alongside the error. This is
  the difference between a rejected payload and a call the service gave up on.

### Testing

38 new checks. Beyond the six detections and their negative cases, the suite covers what
the first run of it caught: percentage similarity is meaningless on short text, where
"Consequence of A" and "Consequence of B" are 94% alike and say different things. Below 40
characters two strings must now match exactly. Also checked: case and punctuation alone
cannot hide a repeated choice; outcome nodes are never judged as if they had choices; an
unscored node is not also reported as identically scored; a single decision leading to one
ending is not called out; and an empty definition, a definition with no nodes, and a node
with no choices are all handled rather than fatal. The panel is also rendered, not merely
computed: a healthy draft shows none, a degenerate one shows it above the scenes, and every
scene still renders either way.

The first run of these checks caught a false positive worth recording. Choices that
converge on one node are this plugin's own architecture — branch and bottleneck, where two
routes rejoin and what the learner did is carried in the skill scores rather than the path.
The demo scenario used by the browser pass does this three times. Reporting it would have
put a note on every well-built scenario until teachers stopped reading the panel, so a
shared destination is now only reported when the scoring is identical too, and the auto
target — "the ending this learner has earned" — is exempt outright. 590 checks pass on
Moodle 4.4.12, 4.5.13 and 5.2.2, CodeSniffer clean.

### Note

- This release does not change what the service generates. It makes what arrives legible
  before it reaches learners. The generator itself is being corrected service-side.

## [v1.6.2] - 2026-09-10

### Changed

- **A rejected request now names the field the service objected to.** The LMS Labs
  routes have been extended, in source, to return an `issues` array alongside their
  refusal — each entry carrying a field path such as `characters.0.role` and a machine
  reason such as `invalid_type`. The plugin reads that array and shows the teacher which
  fields were at fault, so "the service could not complete the request" becomes
  "INVALID_REQUEST: characters.0.role (invalid_type), setting (too_big)".
- Only the field paths and the reason codes are shown. The service's own issue messages
  and any submitted values are discarded at the plugin boundary rather than trusted not
  to contain them, so no teacher content, name or credential fragment can reach a browser
  or a log through this path. The detail is forced onto one line and cut at 200
  characters. Where a deployment sends no `issues` array the plugin falls back to the
  sanitised `message`, and then to the bare error code, so older services behave exactly
  as before.
- The conformance suite gains 20 checks for this, including hostile inputs: a field path
  carrying an API key prefix, a field path spanning several lines, a reason code that is
  really a sentence of prose, and an issue message containing a person's name are each
  refused rather than displayed.

### Note

- **The corresponding service changes are written but not published.** They exist in the
  Replit app's source only; nothing was published or restarted. Until that app is
  published the service will keep returning its unexplained refusal, and this release
  will keep falling back to the bare error code. The plugin change is safe to install
  either way.
- These changes do not establish the cause of the rejected generation reported on
  10 September. The request and response recorded against the failed job remain the place
  that answer will be found.

## [v1.6.1] - 2026-09-10

### Fixed

- **A character the teacher named but gave no job to is sent again.** v1.6.0 left those
  out of the generation request on the belief that an empty `role` was rejected. It is
  not: the route requires the property to be present and to be a string, and an empty
  string satisfies both. An absent, null or non-string value is what it refuses. So the
  previous release was quietly losing a person the teacher had asked for, in order to
  avoid a failure that was never going to happen. The name alone is worth something to
  the generator and is sent as before.
- The conformance suite encoded the same wrong rule and so agreed with the mistake. It
  now checks what the route actually requires — present, and a string — and asserts
  that an empty role is sent as an empty string rather than omitted.

### Note

- The cause of the rejected generation reported on 10 September remains unidentified.
  The three payload faults corrected in v1.6.0 are real, but none of them is known to
  be that one. The request and response for a failed job are recorded against it; that
  record is where the answer is.

## [v1.6.0] - 2026-09-10

The LMS Labs routes validate every request against a strict schema **before** they
authenticate or charge, and their refusal names no field: `INVALID_REQUEST` and
nothing else. The published rules were read off the live service and are now encoded
in the plugin's own test suite, so a request the service would refuse fails here, with
the offending field named, rather than on a teacher's screen.

### Fixed

- **A character with a name and no role was believed to take the whole generation
  down.** This was wrong, and is corrected in v1.6.1 below: an empty role is accepted.
- **The source content ceiling was not enforced where every other ceiling is.** The
  generate route accepts 60,000 characters and the provider left that to its caller's
  own clamp. Found by the new conformance test on its first run.
- **The suggest context could have exceeded its twenty-entry limit.** Room is now
  reserved for the three entries the plugin adds, so the wizard's own values cannot
  crowd them out or push the object over.

### Added

- 25 conformance checks covering every field of the generate and suggest payloads:
  types, minimums, ceilings, item counts, and the properties each object may carry.
  They are run against deliberately oversized input, which is where a ceiling error
  actually comes from. 532 checks in total.

### Known, and not fixable from the plugin

- The suggest route puts the field's specification into the prompt as one JSON block
  and instructs the model to "suggest one concise value". Nothing tells it to follow
  the specification, and "concise" works against a field that asks for two or three
  sentences. Until the route's own instruction changes, an opening situation may still
  come back shorter and flatter than the brief asks for.

## [v1.5.3] - 2026-09-10

### Fixed

- **"The LMS Labs service could not complete the request (no reason given)."** The
  service does say why. It answers a refusal with its own code and a one-line
  explanation, and the provider already reduces that to plain text and caps it. The
  guard added in v1.3.1 to keep provider prose out of the interface accepted only a
  bare identifier, so `INVALID_REQUEST: The request is invalid or exceeds supported
  limits.` matched nothing, was never written to the job record, and every failure read
  "no reason given" — the exact opposite of what the detail was added for. The guard
  now accepts a failure code with an optional single-line explanation, and still
  refuses a stack trace, a multi-line message and free prose.

### Changed

- **The second way into the plugin is offered at the start.** Drafting the scenario in
  another assistant is a choice a teacher makes before touching the wizard, not after
  walking through six steps of one they intend to bypass. The prompt and the paste box
  now sit on the Source step beside "Fill the wizard from this content", under a
  heading that asks which way they want to build it, and importing lands them on the
  build step rather than back at the top.
- The prompt button no longer names one assistant.
- 7 further harness checks, 507 in total.

## [v1.5.2] - 2026-09-10

### Fixed

- **The opening situation still arrived as a course description.** The populate
  response's `introduction` — the generic content route's course introduction, the
  field that produces "Active listening is a crucial communication skill…" — was being
  written straight into the Opening situation. That alone would have been a bad
  mapping. What made it worse is that it arrived *first*: the wizard only asks for the
  fields populate left empty, so a course introduction sitting in that box meant the
  properly briefed request for a real opening scene never ran at all. Every prompt
  improvement made in v1.3.0 and v1.4.0 was unreachable on the autofill path. The
  generic introduction is now ignored, and the field is filled by the request that
  knows what an opening situation is.

## [v1.5.1] - 2026-09-10

### Fixed

- **The daily allowance counted every operation as if it were a full generation.** A
  scenario costs about twenty credits and a suggested field costs one, but both counted
  as one request against a limit of forty. Since v1.3.0 filling the wizard has made up
  to eleven requests in a single press, so a teacher could exhaust a whole day's
  allowance in three or four presses of one button while spending a fraction of the
  credits the limit was meant to bound. Each job is now weighted by what the service
  charges for it, and the setting is described as what it always was: a credit budget.
- **The refusal said nothing useful.** It named a number and stopped. It now says how
  much of the allowance has been used, that it resets twenty-four hours after each
  request, and where an administrator raises it.
- **Generating warns first** when the allowance will not cover the run, rather than
  letting the teacher press the button and be refused.

### Changed

- The default allowance is 400 credits a day rather than 40 requests, which is roughly
  a dozen full generations or thirty wizard fills. **Existing sites keep the value they
  have** — a site that has been running on 40 should raise it.

## [v1.5.0] - 2026-09-10

Closes the ten shortfalls found by auditing the plugin against what a demanding
instructional designer would expect. **This release adds a database column**, so the
upgrade is not a savepoint only.

### Added

- **Read the draft before publishing it.** "Preview as a learner" led to a page saying
  nothing was published, because the player can only render a published revision: the
  only way to see what the AI had written was to publish it to every enrolled learner
  and then look. A new review page lays out every scene and every branch, including the
  ones a single run would never reach, with the consequence, the feedback, the score
  each choice carries and where it leads.
- **Undo the last generation.** Generating again overwrote the working copy in place and
  wiped every image, so an hour of hand editing disappeared on one click with nothing to
  go back to. The copy it replaces is now kept, and restoring swaps the two so an undo
  can itself be undone.
- **Say what a run will cost before it starts.** The one button that spends money had
  less friction in front of it than deleting a forum post. It now asks first, with the
  credits the run is expected to use, how many scenes and pictures that is, the site's
  balance, and a warning that it replaces the current draft.
- **Learners can read their own result again.** Someone who finished, closed the tab and
  came back was shown "Begin the scenario", which spent another attempt and put the
  earlier result out of reach for good — or, with one attempt allowed, failed with an
  error and lost it. The button now says what it will do: carry on, see how you did, or
  try again.
- **The report goes deeper than a score.** Every attempt can be opened to read the
  decisions behind it, which the debrief has always assembled and the report never
  showed.

### Fixed

- **The debrief fetched per-decision feedback and never rendered it.** The most useful
  content in it was on the wire, prepared for the template, and dropped.
- **"Show the debrief" did nothing.** The setting was offered, exported to the template
  and read by nothing, so a teacher who turned it off got the debrief anyway. Turning it
  off now ends the scenario without the breakdown.
- **Media generated after the job reported ready**, so the wizard reloaded and offered
  Publish while cron was still minutes from finishing the artwork — and a run where
  every image failed looked identical to a complete one. The job now finishes only when
  its pictures do, and a partial run says so.
- **The branching of a branching-scenario authoring tool was read only.** A teacher
  could reword a choice but not change where it led or what it was worth. Both are now
  editable, and the target is chosen from the scenes it may point at rather than typed
  as a node identifier.
- **Deleting a learner's attempt took one unconfirmed click** on a red button in a table
  of twenty-five rows. It asks first, and names whose attempt it is.
- **Saving one scene disarmed the unsaved-changes guard for the whole wizard**, so an
  edit made on an earlier step was lost without a prompt.
- Database identifiers are no longer shown to teachers as headings.

### Changed

- 33 further harness checks, 489 in total.

## [v1.4.0] - 2026-09-10

The first release written against a live service rather than a test harness. Every
fault below was found by using the plugin, or by auditing it against what a demanding
instructional designer would expect.

### Fixed

- **A rejected scenario said only "try generating again".** The validator's list of
  what was actually wrong was recorded against the job and never shown, so the honest
  advice was to spend the credits again on a scenario that would fail identically. The
  reason is now displayed. This is the one place a stored detail is shown, and it is
  safe because the text is the plugin's own: field names and rule names, never provider
  output and never learner content.
- **The busy indicator was invisible on five of the six wizard steps.** It lived inside
  the last step's section, which carries `hidden` everywhere else, so pressing Autofill
  on step one showed nothing at all for up to ninety seconds. It now sits in the wizard
  shell and is announced to screen readers.
- **A pasted scenario wrapped in a code fence was rejected as "not valid JSON".** The
  import prompt asks for one JSON document; assistants routinely add a sentence, a
  fence, or both. Everything outside the outermost braces is now dropped.
- **An imported scenario had no pictures.** The media loop ran only inside the
  generation task, so the route a teacher takes when they drafted the scenario
  elsewhere produced text and nothing else, even though the definition carries an image
  brief for every node. Both routes now run the same loop, and importing queues it.
- **Autofill produced exactly one character.** One request returns one person, and it
  was asked once. It now asks until the scenario has two, one at a time, each request
  carrying the people already named — without which the service offers the same person
  again.
- **Suggest rewrote one box and left the rest of the step describing the previous
  idea.** Suggesting the scene now covers the setting, the atmosphere and the opening
  situation together; suggesting the challenge covers the central problem, the
  complications and the stakes.

### Changed

- Media generation is its own ad-hoc task, so it can be run for a scenario that already
  has its text.
- 17 further harness checks, 446 in total.

## [v1.3.1] - 2026-09-10

Corrections to v1.3.0, found by reviewing that release's own diff rather than by
running it. Every one of them failed silently. v1.3.0 was never installed anywhere;
this supersedes it.

### Fixed

- **Autofill could still not fill a picker.** Industry, setting and atmosphere are
  rendered with a schema default already selected, and both the new gap-filler and the
  new merge rule asked "is this empty?" — so a default counted as an answer, the
  pickers were skipped, and the service's own choice was discarded on arrival. The
  wizard is now told which selection is only a default, on the client and on the
  server.
- **The service was told the defaults were decisions.** Every request carried
  `industry: training, setting: trainingroom, tone: neutral` as current values, so it
  wrote a training-room scenario whatever the source content was about — and then the
  plugin refused its answer. Defaults are no longer sent.
- **The scenario mapping lost every collision with the generic one.** `$out + $extra`
  keeps the left operand, so a response carrying both `introduction` and a real
  `openingSituation` kept the introduction: exactly the fault the new mapping exists to
  correct.
- **A failed suggestion was swallowed.** The gap-filler caught and discarded every
  rejection, so a teacher over their daily quota saw a half-filled wizard and no
  message. The run now stops at the first failure and shows it.
- **Autofill was re-entrant.** It now takes up to nine calls, and only the generate and
  publish buttons were disabled while it ran; a second press started a second run
  writing a different snapshot into the same fields. `busy` was set and never read.
- **The Other detail box stole focus on page load.** A saved draft with Other chosen
  dragged the page to the text box on every load, and dropped a screen reader into it.
  The caret now moves only on the click that reveals the box — and does so on the next
  frame, since an element that has only just stopped being hidden cannot take focus.
- **The failure code still never reached the teacher.** `{$a}` was replaced, but the
  code travels on the exception and the job record never stored it, so every message
  read "no reason given". It is stored now; a provider sentence still is not.
- **A prose answer became a character's name.** The character suggestion is split on
  vertical bars; a model answering in prose had its whole sentence written into the
  name field and stored.
- **A hidden Other description was still saved and sent.** Switching from Other to a
  listed option hides the box without clearing it, and a hidden input is still
  submitted. A description is now kept only while its picker says Other.
- **Clicking a decision flashed the keyboard focus ring**, because the new rule used
  `:focus` rather than `:focus-visible` for the outline.

### Changed

- 14 further harness checks, 429 in total.

## [v1.3.0] - 2026-09-10

The wizard fills itself, the prompts say what they mean, and no interactive state can
be repainted by a site theme.

### Fixed

- **The decision buttons turned white on hover.** The v1.0.8 fix restated colour on
  `.aibs-btn-*` only. The buttons a learner actually hovers are `.aibs-choice`, whose
  hover, focus and active rules set no colour at all, so a theme's
  `button:hover { color: #fff }` won in that state. Every interactive state on every
  control the plugin ships now restates both its colour and its background, and both
  are measured: 36 readings across six theme scenarios, including one that paints a
  dark background with `!important`, and the lowest contrast ratio is 5.91.
- **Autofill could only ever fill four fields.** `/populate` is the content route
  shared with the other LMS Labs plugins, and the plugin read five keys from it:
  title, audience, brief, principles, and `introduction` — which it wrote into the
  Opening situation. That is why Autofill left the setting, atmosphere, central
  problem, complications, stakes, role and every character blank for the teacher to
  choose by hand, and why the Opening situation read like a course introduction. It
  was one. The mapper now also reads a scenario-specific response when the service
  sends one, and the wizard fills whatever is still empty afterwards by asking for one
  field at a time.
- **Suggest returned a summary of the source.** The request named the field and sent
  the source content, and said nothing about what the field was for. Every field now
  travels with its own specification and a worked example.
- **Five of those fields could never have worked.** Industry, setting, atmosphere, why
  it is hard and what is at stake are pickers, so a prose suggestion matched no option;
  the answer was discarded and, worse, the group was cleared — taking away a choice the
  teacher had made. Those briefs now list the option keys, and an unrecognised answer
  leaves the group untouched.
- **The service also discarded picker answers.** `provider::suggest()` returned an
  empty `values` list whatever came back.
- **"The request could not be completed ({$a})."** The failure detail was split off the
  stored error and thrown away, so the teacher was shown the placeholder. The reported
  code is passed through; a provider sentence still never is.
- **The image safety direction could be cut out of the prompt.** It sat in the middle of
  the brief and the whole string was trimmed from the tail, so three people with full
  appearance records could push the ban on lettering, real people and injury past the
  ceiling. It is now appended after the rest has been fitted, which also puts it last,
  where a model weights it most.
- **The image anchor contradicted itself.** It promised every frame "the same lighting"
  and "the same people", both of which the mood and cast blocks then overrode — teaching
  the model that the paragraph was soft. It now says exactly what holds and what varies.
- **"Frame 3 of the set"** invited the numeral the direction forbids, and was not true:
  branch nodes share a stage, so a set could contain three frame twos.
- **The import prompt described a document the validator rejects.** It named an outcome
  band of `poor`, which is silently coerced to `mixed` — relabelling every bad ending as
  the middling one — and a language of `en`, which is discarded. Both now come from the
  schema, along with the id rules, the acyclic-graph rule, the length limits, the crisis
  variant and the choice tags.
- **Two primary buttons sat side by side on the last step**, one of which had nowhere to
  go, and Back was offered on the first step. Each is now withdrawn where it does not
  apply.

### Added

- **A detail box on "Other".** Choosing Other sent the service the literal word; where
  the teacher says what they mean, that is what is sent instead, to suggestion,
  autofill and generation alike.
- **A prompt for another assistant.** The import block now offers a copyable prompt
  describing this plugin's scenario format, with the teacher's source content attached
  and one decision node written out in full, so a site without credits still has a way
  in. It is composed from the schema, so it cannot drift from what the validator
  accepts.
- 88 further harness checks, 415 in total.

### Changed

- `populate_wizard` now takes the whole current wizard rather than a brief and the
  source content, so a chosen industry shapes what comes back. Answers the teacher has
  already given are never overwritten.

## [v1.2.0] - 2026-09-10

A packaging release. No file other than `version.php`, `db/upgrade.php` and this
changelog differs from v1.1.0. The release pipeline had already recorded the numeric
version `2026091005`, so v1.1.1 could not be promoted; this release carries a higher
number. Everything described under v1.1.0 below is what it contains.

### Changed

- Version bumped to `2026091006` / `1.2.0`, with a savepoint-only upgrade step.

## [v1.1.1] - 2026-09-10

A packaging release. No file other than `version.php`, `db/upgrade.php` and this
changelog differs from v1.1.0. It exists so a site that has already recorded the
v1.1.0 version number — from a part-finished install, a cached ZIP or an aborted
upgrade — takes the code without needing the previous attempt unpicked first.
Everything described under v1.1.0 below is what this release contains.

### Changed

- Version bumped to `2026091005` / `1.1.1`, with a savepoint-only upgrade step.

## [v1.1.0] - 2026-09-10

Rebuilds scene imagery. Until now each scene's image was generated from the raw
narrative prose of that scene alone, which is why a scenario's pictures did not look
like they belonged to each other. No schema change; the upgrade from v1.0.8 is a
savepoint only.

### Added

- **`image_prompt`, a composer for scene briefs.** Scenes are generated as separate
  requests, minutes apart, by a model with no memory between them, so sending each
  scene's prose on its own produced a different room, a different Sam and a different
  time of day. Every brief is now built from the same four parts in the same order: a
  series anchor identical across the whole scenario, the people actually present
  described from the scenario's own character records, the moment and how it should
  feel, and direction the model needs. Composition is deterministic, so regenerating
  one scene cannot quietly change the look of the set.
- **Endings get an image.** Outcome nodes were skipped entirely. The closing frame is
  what a learner looks at while reading what their decisions came to, and it was the
  only scene with nothing to show.
- **Crisis moments get their own frame.** A node above the tension threshold showed
  escalated prose against the picture of the room before it went wrong. The crisis
  variant is now its own image, stored beside the calm one and selected at play time,
  falling back to the original for scenarios generated before this release.
- **Style choices are described rather than named.** A single stored word gave the
  model almost nothing. Each of the six styles now carries its lens, light and
  palette, so "cinematic" means the same thing in scene one and scene six.
- **Scene images are never announced with no description.** When the generated
  scenario supplies no alternative text, one is composed from the scene.
- `count_images()`, so the number of billable frames a run will produce is knowable
  before it starts rather than after.

### Fixed

- **The media routes were called with fields they do not accept.** `/image` takes
  `prompt`, `sceneTitle` and `style`; the plugin sent an `aspectRatio` the route
  rejected and no scene title at all. `/speech` validates `language` and `voice` with
  regular expressions and rejects the whole request when either fails; values that do
  not match are now dropped in favour of the service's own defaults rather than sent
  and refused.

### Testing

37 new checks cover briefing: every scene carries the same location anchor and the
same visual treatment; a named character is described by name, role and appearance,
identically in every scene they appear in, and a scene naming nobody does not invent
one; composition is deterministic; endings read as closings and a high-risk ending as
an aftermath while still forbidding injury; the crisis variant differs from the calm
one but keeps the anchor; teacher direction is appended last; all six styles produce a
described treatment within the service's limit; alt text is never empty; and an empty
scene still yields a brief the service will accept. The suite is 327 checks and passes
on Moodle 4.4.12, 4.5.13 and 5.2.2, with CodeSniffer clean.

## [v1.0.8] - 2026-09-10

Three corrections, each of which cost real time to find because the plugin could not
show its own working. No schema change; the upgrade from v1.0.7 is a savepoint only.

### Fixed

- **The plugin reported valid API keys as malformed.** It asserted that a key was
  `aigr_` followed by exactly 64 hexadecimal characters. That figure was assumed
  here and never taken from the service; real issued keys carry 63. Every real key
  was therefore labelled "does not match the usual LMS Labs format" on the settings
  page. The check is now a loose shape test only, it never gates a call, and the
  false warning is gone. The same wrong figure reached the LMS Labs routes through
  this plugin's route contract document, where it rejected every real key before
  authentication; that is fixed separately on the service.
- **Button text could render white on a light grey background.** The hover rules
  changed the background without restating the colour, so a site theme's
  `button:hover { color: #fff }` won on specificity. Measured under a theme rule
  using `!important`, the plugin's own buttons rendered at a contrast ratio of 1.14,
  which is unreadable. Colour is now restated in every hover, focus and active
  state, scoped to the activity's body class, and carries `!important` — used
  nowhere else in the stylesheet, confined to colour, in interactive states, on this
  plugin's own components.

### Added

- **Failure diagnostics.** The service reports a rejected request without naming the
  field at fault, so a failure previously left nothing to investigate but the code
  that might have run. Every call now records what was sent and what came back, and
  a failed job stores it. The API key is replaced with a description of its shape —
  its length and prefix, never its value, which is exactly the fact needed to tell a
  malformed key from a rejected payload. Long values are shortened to a length and a
  leading fragment, arrays are summarised by size, and generated narrative is not
  copied into the diagnostic column.
- `get_last_exchange()` on the provider contract, so no future provider can quietly
  drop this.

### Testing

17 new checks: the redacted record never contains the key or any long run of it,
reports its length and prefix, shortens long values, summarises arrays, and keeps
non-secret fields intact; the provider contract requires the method; and the key
shape test accepts real 68-character keys, 69-character keys and short plausible
ones while rejecting values with no prefix — with resolution proven independent of
shape either way. The suite is 290 checks and passes on Moodle 4.4.12, 4.5.13 and
5.2.2, with CodeSniffer clean.

Button colour is now measured rather than asserted. A harness renders the wizard and
reads the computed colour of every button in every state, under Boost alone and under
three kinds of hostile theme rule — plain, body-scoped and `!important` — and computes
the WCAG contrast ratio for each. All twelve combinations pass AA; before this change
the `!important` case measured 1.14.

## [v1.0.7] - 2026-09-10

Cuts request values to the ceilings the LMS Labs schemas set. No schema change; the
upgrade from v1.0.6 is a savepoint only.

### Fixed

- **Oversized wizard values rejected the whole request.** v1.0.6 sent the right
  field names but did not cut their values to the service's limits, and the schemas
  are length checked as well as strict: one value over its ceiling rejects the
  entire request with a generic `INVALID_REQUEST` that names no field. The wizard's
  own limits are looser than several of the service's, so a participant role longer
  than 1000 characters, a title over 300, an audience over 500 or a setting over 300
  was enough to make every generation fail. `title`, `audience`, `role`, `setting`,
  `language`, `tone` and `complexity` are now cut to 300, 500, 1000, 300, 20, 40 and
  40 characters respectively before the request is built.

### Changed

- Building a generate request is now its own method, so its shape and its limits can
  be exercised without a network round trip.

### Testing

18 new checks pin every ceiling, confirm the payload carries no key the schema does
not name, confirm empty optional fields are omitted rather than sent blank, and
confirm source content below the service minimum is refused locally with a message
that says so. The suite is 273 checks and passes on Moodle 4.4.12, 4.5.13 and 5.2.2,
with CodeSniffer clean.

## [v1.0.6] - 2026-09-10

Aligns this plugin with the LMS Labs service contract. Until now the plugin sent a
request shape that had been designed here rather than agreed with the service, and
every generation call was rejected with `INVALID_REQUEST` before it was even
authenticated. No schema change; the upgrade from v1.0.5 is a savepoint only.

**This release needs the matching server change to be live.** The service must be
running the extended scenario contract described below, or generation will return a
scenario the validator rejects.

### Fixed

- **Requests carried fields the service does not accept.** The LMS Labs routes
  validate with a strict schema, so any unknown key rejects the whole request. The
  plugin was sending `pluginId`, `pluginVersion`, `requestId` and `contract` in the
  body along with payload names of its own invention. The body now carries only
  `siteId`, `apiKey` and the fields each route declares; the request identifier
  moved to the `X-Request-Id` header, which is not schema checked.
- **Each route's payload now matches its schema.** `generate` sends
  `sourceContent` with the optional `title`, `audience`, `role`, `setting`,
  `language`, `tone`, `complexity`, `decisions`, `maxNodes`, `learningObjectives`,
  `characters` and `instructions`; `populate` sends `sourceContent` with
  `currentValues`; `suggest` sends `field` with `currentValue`, `sourceContent` and
  `context`. The wizard's longer narrative inputs are folded into `instructions`,
  which the service caps at 2000 characters.
- **Minimum source lengths are enforced before the call.** The service needs at
  least 50 characters to generate and 20 to populate. A teacher who pastes less now
  gets a clear message instead of a rejected request.
- **Credit fields were read from the wrong place.** The service returns
  `creditsUsed`, `creditsRemaining`, `creditsRaw` and `isUnlimited` at the top
  level; the plugin was looking for a nested `credits` object, so the settings page
  would have shown a zero balance on a working connection.
- The service sends a human-readable `message` alongside its error code. It is now
  shown to the administrator, cleaned to plain text and capped, instead of being
  discarded in favour of a bare code.

### Added

- `scenario_mapper`, the single seam between the service's wire format and this
  plugin's stored format. The service speaks camelCase and calls a scene's prose
  `content`, a choice's prose `label` and a terminal node `end`; everything stored,
  validated, rendered and scored here uses its own names. Translating in one place
  means the wire format can change without touching the player, and the stored
  format can change without renegotiating the API. Every optional field the service
  omits becomes this plugin's documented default, so a service running an older
  contract still produces a coherent scenario rather than a fatal.

### Testing

28 new checks cover the mapping in both directions: a full wire scenario mapped and
then run through the validator, the `__auto__` outcome target, crisis variants,
outcome bands, a minimal response with every optional field absent, and the
populate field set. The suite is 255 checks and passes on Moodle 4.4.12, 4.5.13 and
5.2.2, with CodeSniffer clean.

## [v1.0.5] - 2026-09-10

Fixes the Central Config credential resolver. A site with valid credentials in
`local_aiconfig` could be told it had none, leaving AI generation unavailable
while other plugins in the ecosystem worked from the same values. No schema
change; the upgrade from v1.0.4 is a savepoint only.

### Fixed

- **The resolver never asked Central Config.** It called two global functions,
  `local_aiconfig_get_siteid()` and `local_aiconfig_get_apikey()`, which do not
  exist. It now uses the documented integration point,
  `\local_aiconfig\config::get_credentials()`, falling back to
  `get_site_id()` / `get_api_key()`, then to those legacy functions if a release
  defines them, and finally to the stored settings `local_aiconfig/siteid` and
  `local_aiconfig/apikey`. The class is resolved by fully qualified name through
  Moodle's autoloader and guarded with `class_exists()`, and an accessor that
  throws is stepped over rather than taking the settings page down.
- **A guessed key format was gating resolution.** Any key that did not match
  `/^aigr_[0-9a-f]{64}$/` was discarded and the site was told it had no
  credentials at all. That pattern is now a diagnostic note only: the key is sent,
  and only the LMS Labs server decides whether it is valid.
- **Precedence is now explicit and both values must come from the same source.**
  Unless "Ignore Central Config" is enabled: a complete Central Config pair wins,
  then a complete local pair, then nothing. Empty or whitespace-only fallback
  fields can no longer mask valid Central Config credentials, and a half-filled
  source is skipped rather than being combined with the other one. Both values are
  trimmed.

- **The generation task could not build an HTTP client.** The provider used
  Moodle's `curl` class, which lives in `lib/filelib.php` and is only loaded
  conditionally by the bootstrap. A web request has usually pulled it in by some
  other route, but a task running under CLI cron has not — and generation runs as
  an ad-hoc task, so the first generation job on a site would have died with
  "Class curl not found". The provider now requires `filelib.php` itself before
  constructing a client.

### Changed

- When no credentials resolve, the settings page now says **why** — Central Config
  not installed, installed but empty, only one of the two values set, being
  ignored by this plugin, or the local fallback half filled — instead of only
  reporting that generation is unavailable.
- The masked key no longer prints a fixed `aigr_` prefix, since the prefix is no
  longer assumed.

### Testing

39 new checks cover credential resolution: Central Config only by each of the four
routes, local only, empty and whitespace-only local fields against valid Central
Config, "Ignore Central Config" both with and without a complete local pair,
partial credentials on either side and across sources, trimming, an unfamiliar key
format, a throwing accessor class, masking, and building a cURL client in a CLI
context. The suite is 227 checks and passes on Moodle 4.4.12, 4.5.13 and 5.2.2.
The new checks were confirmed to fail against the v1.0.4 resolver before the fix
was applied: nine of them did, on exactly the two behaviours that changed.

## [v1.0.4] - 2026-09-09

Corrects two faults found by the first end-to-end browser pass, run against
Moodle 4.4.12, 4.5.13 and 5.2.2 with a real browser driving the authoring wizard
and the player. No schema change; the upgrade from v1.0.3 is a savepoint only.

### Fixed

- The activity description was rendered twice on the activity page. Since Moodle
  4.0 the activity header printed by `$OUTPUT->header()` already shows the
  description, because `$PAGE->set_activity_record()` hands it the instance;
  `view.php` was also printing the pre-4.0 intro box below it. The manual box is
  gone and the header is left to do its job.
- On screens narrower than 720px the step numbers in the player's progress rail
  were hidden with `display: none`. The dot beside each number is `aria-hidden`,
  so this left a screen reader announcing a list of empty items on every phone and
  small tablet. The numbers are now hidden visually only and remain in the
  accessibility tree.

### Testing

The browser pass is now part of the verification set: on each of the three
supported Moodle versions a teacher walks all six wizard steps, imports a
definition, publishes it, and a student plays the scenario to an outcome, with
every console error, uncaught exception, failed request and 5xx recorded, at
1440px and 390px. axe-core reports zero WCAG 2.1 AA violations on the wizard, the
player landing page, a decision node and a consequence screen at both widths, and
choices are reachable and operable by keyboard alone.

## [v1.0.3] - 2026-09-09

Removes `PARAM_RAW` from the plugin entirely and answers the remaining release
pipeline findings. No schema change, so the upgrade from v1.0.2 is a savepoint
only. Anyone consuming the web services directly should read the two contract
changes below.

### Changed

- **Narrative is no longer returned as HTML.** The external functions used to
  return a `*html` twin for each narrative field, pre-escaped and wrapped in
  paragraph tags on the server. Those fields are replaced by `*paras`: an array
  of plain-text paragraphs, described as `PARAM_TEXT`. The templates render each
  paragraph through Mustache, so escaping now happens at the point of output
  instead of several layers earlier, and no generated markup crosses the API at
  all. Affected fields are `situation`, `summary`, `consequence`, `feedback`,
  `outcome`, `sourceconnection` and `body`. Single line breaks inside a paragraph
  are preserved by CSS rather than by generated `<br>` tags.
- **`import_definition` takes the definition base64 encoded.** The definition is
  a JSON document whose narrative legitimately contains angle brackets, quotation
  marks and non-ASCII text, so a cleaned string parameter would corrupt it. It is
  now declared `PARAM_BASE64`, which can be validated strictly on arrival while
  leaving the document byte-exact. Moodle's `PARAM_BASE64` follows the PEM
  convention of 64 character lines, so callers wrap the encoded document
  accordingly. Malformed input is now rejected by the parameter layer before it
  reaches the plugin at all.
- `index.php` requires `mod/aibranchedscenario:view` at course context before
  listing the activities in a course.
- Six multi-line calls in `classes/local/validator.php` and
  `classes/output/wizard.php` were restructured so the first argument begins on
  its own line.

### Testing

The suite grew to 187 checks and passes on Moodle 4.4.12, 4.5.13 and 5.2.2. New
checks cover the base64 round trip for non-ASCII narrative, rejection of
malformed base64 at the parameter layer, and escaping of hostile content
verified through the rendered template rather than through a helper return
value. Moodle CodeSniffer reports zero errors and zero warnings.

## [v1.0.2] - 2026-09-09

Fixes two faults that only appear on Moodle 4.4, the plugin's declared minimum.
Both were found by installing v1.0.1 on a real 4.4 site; the earlier test matrix
covered 4.5 and 5.2 only.

### Fixed

- `custom_completion::get_sort_order()` omitted `completionpassgrade`. Moodle
  requires the sort order to list every standard and custom completion condition
  the activity can display, and raises "get_sort_order() is missing one or more
  completion conditions" when it does not. This surfaced on any course page or
  activity listing once completion was configured.
- `classes/output/player.php` and `classes/output/wizard.php` imported
  `core\output\renderable`, `core\output\templatable` and
  `core\output\renderer_base`. Those namespaced interfaces were introduced in
  Moodle 4.5, so on 4.4 the activity page died with
  'Interface "core\output\renderable" not found'. Both classes now use the global
  interfaces, which exist across 4.4, 4.5 and 5.2.

### Testing

The verification matrix now covers Moodle 4.4, 4.5 and 5.2 against both MariaDB
and PostgreSQL, and the suite gained a check that renders completion through
`cm_completion_details`, the path that validates the sort order.

## [v1.0.1] - 2026-09-09

Corrects defects found by installing v1.0.0 on Moodle 4.5 and 5.2 against both
MariaDB and PostgreSQL. v1.0.0 was never promoted; it fails to install on
MySQL and MariaDB.

### Fixed

- Renamed the `signal` column on `aibranchedscenario_events` to `signaltype`.
  `SIGNAL` is a reserved word in MySQL and MariaDB, so creating the table failed
  with a syntax error and the whole plugin install aborted part way through.
- Added `aibranchedscenario_get_coursemodule_info()`. Without it Moodle never
  registered the activity's custom completion rule, so completion was never
  evaluated and `custom_completion::get_state()` raised "rule is not used by this
  activity".
- Removed `DEFAULT ''` from six `CHAR NOT NULL` columns (`currentnode`, `outcome`,
  `nodeid`, `choiceid`, `nextnodeid`, `modelused`). Moodle's XMLDB layer rejects an
  empty-string default on a not-null character column and silently rewrote them.
- Replaced the hand-generated AMD build artefacts with output from Moodle's own
  rollup toolchain, so `amd/build` now matches what `grunt amd` produces on both
  the 4.5 and 5.2 branches.
- Resolved every Moodle CodeSniffer error and warning: multi-line call formatting,
  missing method docblocks on the LMS Labs provider, an over-long line in the
  privacy provider, and five unnecessary `MOODLE_INTERNAL` guards.
- Dropped the legacy `aibranchedscenario_get_completion_state()` callback, which
  has no effect on the supported Moodle versions.

### Note for sites that tried to install v1.0.0

A v1.0.0 install aborts after creating some of its tables and never records a
version number, so Moodle treats the plugin as new on the next attempt and fails
with "Table already exists". Run the supplied `CLEANUP_FAILED_INSTALL.sql`,
purge caches, then install this release.

## [v1.0.0] - 2026-09-09

First release.

### Added

- Activity module `mod_aibranchedscenario` for Moodle 4.4 to 5.2.
- Five step authoring wizard: source content, scene, challenge, people, design, then build and publish.
- AI scenario generation through the LMS Labs service, run as a Moodle ad-hoc task so a slow
  provider cannot time out the teacher's browser.
- True branch-and-bottleneck scenario model: every choice names the node it leads to, branches
  reconverge at bottlenecks, and the graph is validated server side for dangling targets,
  unreachable nodes, cycles and size before anything is stored.
- Immutable published revisions, so publishing never changes a scenario underneath a learner
  who is part way through an attempt.
- Server-authoritative player: the browser sends only the identifier of the choice it picked,
  and the server decides what that does to the metrics and where it leads.
- Engagement, trust and tension dynamics, four skill dimensions, crisis variants above a
  tension threshold, consequence and feedback for every choice, and a closing debrief.
- Idempotent decision recording, so a retried request after a timeout cannot record twice.
- Optional scene image and narration generation, stored through the Moodle File API.
- Attempt resume, replay, attempt ceiling and four gradebook aggregation methods.
- Activity completion, including a minimum decision quality rule.
- Teacher attempt report with per-attempt deletion.
- Backup, restore and course duplication, with learner attempts following Moodle's
  "include user data" setting.
- Full Privacy API implementation with export, deletion and external location declarations.
- Six light accent themes, responsive from phone to wide desktop, keyboard operable throughout,
  and a reduced-motion mode.
