# AI Branched Scenario

An activity module for Moodle™ that turns pasted source content into a realistic
branching scenario. Learners take a role inside a situation, make decisions that
change what happens next, live with the consequences, and finish with a debrief
that connects the experience back to the source material.

- **Component:** `mod_aibranchedscenario`
- **Installation folder:** `mod/aibranchedscenario`
- **Release:** 1.0.0
- **Numeric version:** 2026090900
- **Supported Moodle versions:** 4.4 to 5.2 (`$plugin->requires = 2024042200`)
- **Licence:** GNU GPL v3 or later

## What it produces

Generation does not produce a quiz. It produces a node graph:

- **Decision nodes** present a situation, an optional spoken line from a character,
  and a direct question. Each offers two to four choices.
- **Every choice names the node it leads to.** Different choices lead to different
  places, and the story is written so branches reconverge at deliberate bottlenecks
  rather than exploding into hundreds of paths.
- **Outcome nodes** end the scenario in one of three bands: strong, mixed or high
  risk. A choice may also target `__auto__`, in which case the server picks the
  outcome band from the learner's accumulated decision quality.
- **Crisis variants** let a node be rewritten when tension has climbed above 75, so
  the same decision point reads differently for a learner who has let the situation
  get away from them.

Before writing anything, generation is asked to identify three to five **decision
principles** in the source content, and the scenario is built around those. That is
what stops a 3,000-word policy becoming a set of unconnected recall questions.

## Prerequisites

- Moodle 4.4 or later, PHP 8.1 or later.
- An LMS Labs Site ID and API key, supplied either by the
  [AI Grader Central Config](https://lms-labs.com/docs/ai-central-config) plugin
  (`local_aiconfig`) or by this plugin's own settings.
- Available LMS Labs credits. Generation consumes credits; the exact tariff is set
  and charged server side by LMS Labs, not by this plugin.
- Moodle cron running. Scenario generation is queued as an ad-hoc task.

The plugin contains **no AI provider credentials of any kind**. It never contacts
OpenAI, Google or any other provider directly.

## Installation

1. Copy the `aibranchedscenario` folder into `mod/` in your Moodle installation, or
   install the ZIP through *Site administration → Plugins → Install plugins*.
2. Visit *Site administration → Notifications* to complete the database install.
3. Configure the plugin at
   *Site administration → Plugins → Activity modules → AI Branched Scenario*.

## Configuration

The settings page shows which credentials are in use, where they came from, and the
current credit balance. The API key is never displayed in full after saving and is
never sent to the browser.

| Setting | Purpose |
| --- | --- |
| Ignore Central Config | Force the plugin's own Site ID and API key even when Central Config is installed. |
| Central Config component | Which component to read shared credentials from. Defaults to `local_aiconfig`. |
| LMS Labs Site ID / API key | Fallback credentials used when Central Config is unavailable. |
| LMS Labs API host | Only change this if you have been given a different endpoint. |
| Request timeout | How long to wait for the service before giving up. |
| Maximum source content | Characters of pasted content sent for one scenario. Default 60,000. |
| Generation requests per user per day | Bounds accidental or runaway credit use. Default 40. |
| Allow scene image / narration generation | Site-wide switches for the two optional media features. |
| Generation job retention | How long job records are kept before the cleanup task removes them. |

## Capabilities

| Capability | Default roles | Purpose |
| --- | --- | --- |
| `mod/aibranchedscenario:addinstance` | Editing teacher, Manager | Add the activity to a course. |
| `mod/aibranchedscenario:view` | All, including Guest | See the activity page. |
| `mod/aibranchedscenario:attempt` | Student | Work through the scenario. |
| `mod/aibranchedscenario:manage` | Editing teacher, Manager | Author and edit scenarios. |
| `mod/aibranchedscenario:publish` | Editing teacher, Manager | Publish a revision. |
| `mod/aibranchedscenario:generate` | Editing teacher, Manager | Spend credits on AI generation. |
| `mod/aibranchedscenario:viewreports` | Teacher, Editing teacher, Manager | See learner attempts. |
| `mod/aibranchedscenario:deleteattempts` | Editing teacher, Manager | Delete attempts. |

Students receive `view` and `attempt` only. Consequences, feedback, unchosen
branches and the answer graph are never sent to a learner's browser until the
matching decision has actually been recorded on the server.

## Authoring workflow

1. **Source** — paste the policy, procedure or unit content. Optionally add a short
   brief, then use *Fill the wizard from this content*.
2. **The scene** — setting, atmosphere and the opening situation.
3. **The challenge** — the central problem, why it is hard, and what is at stake.
4. **The people** — the role the learner plays, and up to four characters.
5. **Design** — number of decision points, tone, complexity, image style and the
   opening engagement, trust and tension levels.
6. **Build and publish** — generate, edit the generated text node by node, preview
   as a learner, and publish.

Publishing creates an **immutable revision**. A learner who is part way through an
attempt stays bound to the revision they started on, so republishing never changes
a scenario underneath them.

## Grading and completion

- Decision quality is calculated server side from the recorded decisions. Each
  decision moves four skill dimensions by -2 to +2, so a path of *N* decisions spans
  -8*N* to +8*N*; that span is mapped onto 0–100.
- The gradebook receives the first, last, highest or average attempt, as configured.
- Activity completion supports "finish the scenario", optionally with a minimum
  decision quality.
- A score posted by a browser is never trusted; the browser never sends one.

## External services and data

When a teacher generates a scenario, the plugin sends the following to LMS Labs
(`https://lms-labs.com`):

- the pasted source content and the authoring wizard values;
- the site identifier and API key used to authenticate and account for credits;
- the plugin's option lists and structural limits, so the service can constrain the
  model to values this plugin will accept.

LMS Labs holds the AI provider credentials and passes the request to a contracted
provider. **Learner attempt data is never sent off-site.** Optional scene images use
LMS Labs' Gemini-first image path (currently `gemini-3.1-flash-image`); optional
narration uses Google Chirp HD through LMS Labs.

Everything the service returns is validated against a server-side schema before it
is stored. Narrative content is stored and rendered as plain text — the plugin never
renders provider or teacher HTML, and never executes generated JavaScript.

## Privacy

The plugin implements the Moodle Privacy API in full: metadata for every personal
field it stores, export of real attempt content including the decision journey, and
deletion for a context, a single user and an approved user list.

Personal data stored: learner attempts, the individual decisions in each attempt,
and a record of which teacher requested each generation. Deleting a user's data
removes their attempts and decision log from this plugin. Data already processed by
LMS Labs or a contracted provider is subject to that service's retention policy and
cannot be deleted by Moodle.

## Backup and restore

Activity configuration, published revisions and their media are always included.
Learner attempts and their decision logs are included only when the backup includes
user data. Course duplication produces a new activity without carrying learner
attempts across.

## Failure recovery

- If generation fails, nothing is stored and the draft is untouched, so the teacher
  can simply try again.
- If the service times out, the job is marked failed with a plugin error identifier;
  raw provider errors are never shown to a browser or written to logs.
- If a learner's decision request is retried after a network failure, the sequence
  number makes it idempotent — the same decision can never be recorded twice.
- If credits run out, the teacher sees a clear message rather than a partial
  scenario.

## Accessibility

Every choice and control is a real button reachable by keyboard, focus is moved to
each new scene as the story advances, progress and outcomes are announced through
live regions, colour is never the only signal, and the whole interface honours
`prefers-reduced-motion`.

## Known limits

- A scenario is capped at 60 nodes, four choices per decision, and 2 MB of stored
  definition.
- The node graph must be acyclic, so recovery is modelled as a forward path back to
  the main line rather than a loop.
- Teachers can edit all generated text; adding, removing or rewiring nodes by hand is
  not offered in this release.
- Manual authoring without any AI service configured is not supported in this
  release; generation requires LMS Labs credentials.

## Development

- JavaScript source lives in `amd/src`; built artefacts in `amd/build` must be
  regenerated with the project's AMD build script after any source change.
- `.github/workflows/ci.yml` runs moodle-plugin-ci.
- `docs/LMS_LABS_ROUTE_CONTRACT.md` specifies the server routes this plugin calls,
  and `docs/reference-server/` contains a reference implementation of them.

## Support

LMS Hosting Services — <https://lms-labs.com>
