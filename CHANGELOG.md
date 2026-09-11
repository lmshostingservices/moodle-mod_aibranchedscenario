# Changelog

All notable changes to AI Branched Scenario are recorded here.

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
