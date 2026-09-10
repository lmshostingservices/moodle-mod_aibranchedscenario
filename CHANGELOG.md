# Changelog

All notable changes to AI Branched Scenario are recorded here.

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
