# AI Branched Scenario

Decision-based scenario training for Moodle™. AI Branched Scenario turns source
material into a branching activity in which learners enter a situation, make
decisions, see the consequences, and finish with a debrief tied back to the
principles being taught.

[Documentation](https://lms-labs.com/docs/ai-branched-scenario) ·
[Source repository](https://github.com/lmshostingservices/moodle-mod_aibranchedscenario) ·
[Report an issue](https://github.com/lmshostingservices/moodle-mod_aibranchedscenario/issues)

| Release detail | Value |
| --- | --- |
| Component | `mod_aibranchedscenario` |
| Installation folder | `mod/aibranchedscenario` |
| Release | **2.0.0** |
| Numeric version | **2026091900** |
| Moodle | **4.4–5.2** (`requires = 2024042200`, `supported = [404, 502]`) |
| Maturity | Stable |
| Licence | GNU GPL v3 or later |

> **Release note:** This document describes v2.0.0. Updating documentation on the
> default branch does not alter or fix the immutable v2.0.0 tag or its published
> ZIP. The current costs below are service charges, not evidence that the old
> pricing text inside that archive has been changed.

## What learners experience

Generation produces a validated node graph rather than a recall quiz.

1. **Principles first.** The scenario teaches three to eight decision principles.
   Each can include a worked example—the words a learner might actually use—and a
   pitfall that explains a plausible but ineffective response.
2. **The opening situation.** The learner enters a role, setting, and immediate
   problem.
3. **A decision.** Each decision node presents **two to four choices**
   (`MIN_CHOICES = 2`, `MAX_CHOICES = 4`).
4. **The consequence.** The server records the choice before returning its
   consequence and changes to Engagement, Trust, and Tension.
5. **Escalation.** At tension 75 or above, a node can use its crisis variant.
6. **Read-only review.** Back and forward controls let a learner revisit decisions
   already taken. Review does not resubmit a choice or recalculate its score.
7. **Debrief.** The ending presents the outcome, skill scores, decision journey,
   lessons, critical decisions, practice guidance, and takeaways.

Each choice names its next node. Branches can reconverge at intentional
bottlenecks, and `__auto__` can advance to the next stage or select an ending from
the learner's accumulated decision quality. The graph must be acyclic.

## Two authoring routes

Both routes use the same importer, validator, draft review, media pipeline, and
player.

### Route A: generate inside Moodle

The wizard collects source content and the scenario shape: audience, learner role,
setting, atmosphere, challenge, stakes, tone, complexity, decision count, opening
readings, and a named cast. A teacher can fill fields from the source, generate a
draft, edit it node by node, preview it, and publish it.

This route requires LMS Labs credentials, credits, outbound access to the LMS Labs
service, and working Moodle cron. Generation is queued as an ad-hoc task.

### Route B: bring your own scenario

The plugin supplies a structured prompt. A teacher can use an assistant of their
choice, then paste the resulting JSON back into Moodle. The imported definition is
validated exactly like an in-Moodle generation.

Iteration inside the plugin is not charged for scenario-text generation on this
route, but the external assistant may impose its own fees, limits, privacy terms,
and data-processing conditions. Optional illustrations and narration generated
through LMS Labs still consume the applicable media credits.

## Review before publishing

AI output requires human review. Structural validation ensures that the stored
definition can be played; it does not establish instructional, legal, regulatory,
or factual correctness.

The draft review includes a **non-blocking quality advisory**. It can identify:

- principles missing a worked example or pitfall;
- duplicate or near-duplicate choices;
- duplicate or near-duplicate consequences;
- choices that all lead to the same target while scoring identically;
- options whose skill scores do not vary;
- a scenario with multiple decisions but only one outcome;
- common spelling mismatches for the selected English variety;
- pronouns that conflict with a named character's recorded gender;
- debrief lists with fewer or more entries than the principles taught; and
- internal skill names leaking into learner-facing scenario prose.

The review also shows generated media and identifies missing pictures so they can
be generated separately. These checks are advisory and deliberately do not block
publishing. They are not a substitute for a teacher's full quality, accessibility,
subject-matter, and cultural review. Cast-sheet and prompt controls support visual
and voice consistency, but they cannot guarantee perfect cast consistency from a
generative provider.

Publishing creates an **immutable revision**. An attempt is pinned to the revision
on which it started, so publishing a newer revision does not change the activity
under a learner who is already part-way through it.

## Illustrations and narration

### Illustrations

Six image treatments are available:

- Photorealistic
- Cinematic
- Illustration
- Watercolour
- Noir
- Oil

Media is keyed to the screen or debrief entry for which it was generated. Opening,
principle, node, crisis, reaction, outcome, and debrief media are looked up by their
own keys. A missing image is not replaced with a borrowed opening, lesson, or scene
image; that screen shows no image and the missing-media review identifies the gap.

Image prompts carry the scenario's cast description and selected treatment.
Generation instructions exclude readable text, captions, logos and brand marks,
identifiable real people, children, injury, violence, and weapons. Provider output
should still be reviewed before publication.

### Narration

Narration supports these 18 language and variety codes:

`en-AU`, `en-GB`, `en-US`, `en-NZ`, `es-ES`, `fr-FR`, `de-DE`, `it-IT`,
`pt-BR`, `nl-NL`, `hi-IN`, `id-ID`, `ja-JP`, `ko-KR`, `cmn-CN`, `ar-XA`,
`vi-VN`, `th-TH`.

Administrators can select narrator, male-character, and female-character voices.
Character lines use the voice group selected from the character record; narration
falls back to the narrator where no character applies. Generated clips and images
are stored through Moodle's File API. Published activities play from Moodle's own
stored files rather than fetching media from an AI provider at play time.

## Credits and access

Current authoring charges are:

| Authoring operation | Current credits | Current USD |
| --- | ---: | ---: |
| Scenario generation or regeneration | **20** | **US$2.00** |
| Populate the wizard from source content | **3** | **US$0.30** |
| Each suggestion | **1** | **US$0.10** |
| Each image request | **5** | **US$0.50** |
| Each narration request | **5** | **US$0.50** |

Images and narration are charged for each request, so a run's media total depends
on the number of requests. Learner play and replay consume no generation credits.

For historical release accuracy, the immutable v2.0.0 artifact contained an older
package-quote display. This documentation correction does not change that tag or
ZIP and must not be read as evidence that its embedded display has been fixed.

A site administrator sets an **AI-credit allowance per user per rolling 24
hours**. It is a credit budget, not the old “40 requests” counter; v2.0.0 ships with
a default allowance of 400 credits, and `0` removes that local limit. A media job
counts using the applicable per-request cost. Operations marked by the service as refunded are
excluded from allowance spend.

> **Refund caution:** The source defines how a response already marked as refunded
> is reconciled locally. It does not promise that every failed or partial request
> qualifies for a refund. Confirm current refund and support terms with LMS Labs
> before repeating an uncertain charge.

### One-time site access is separate

**US$5 on the Moodle Marketplace OR 50 LMS Labs credits — ONE-TIME SITE ACCESS.**
It is distinct from credits consumed by authoring and media generation. Do not
interpret the one-time site-access amount as an included generation bundle or a
recurring authoring tariff.

See the [Moodle Marketplace directory](https://marketplace.moodle.com/) for
Marketplace availability.

## Requirements and installation

### Requirements

- Moodle 4.4 through 5.2.
- The PHP version required by the chosen Moodle release.
- An LMS Labs Site ID and API key, either from
  [AI Grader Central Config](https://lms-labs.com/docs/ai-central-config)
  (`local_aiconfig`) or from this plugin's settings.
- Available LMS Labs credits for billable authoring operations.
- Standard Moodle cron for queued generation.
- Outbound HTTPS access to the configured LMS Labs API host for generation.

The plugin stores no OpenAI, Google, Gemini, or other AI-provider credential.
It authenticates to LMS Labs with the configured Site ID and API key.

### Install

1. Install the plugin ZIP through **Site administration → Plugins → Install
   plugins**, or place the `aibranchedscenario` directory in
   `mod/aibranchedscenario`.
2. Visit **Site administration → Notifications** to complete installation.
3. Open **Site administration → Plugins → Activity modules → AI Branched
   Scenario** and configure the credentials and defaults.
4. Ensure Moodle cron is running.

A connection-status control is available on the settings page. It performs a live
check when an administrator uses it; this README does not claim that any particular
provider is currently accepting requests.

## Configuration

The settings page reports which credential source is selected and can display the
service balance returned by the configured endpoint. The saved API key is masked
in the form.

| Site setting | Purpose |
| --- | --- |
| Ignore Central Config | Use this plugin's own Site ID and API key even when Central Config is installed. |
| Central Config component | Shared credential component; defaults to `local_aiconfig`. |
| LMS Labs Site ID / API key | Plugin-level credentials when shared credentials are not used. |
| LMS Labs API host | Service host; change only when instructed. |
| Request timeout | Maximum wait for a service request. |
| Maximum source content | Ceiling for pasted content; v2.0.0 schema maximum is 60,000 characters. |
| AI credits per user per day | Rolling 24-hour credit allowance; default 400, `0` for no local limit. |
| Allow illustrations / narration | Site-wide media-generation switches. |
| Narrator / male / female voice | Voice defaults for generated narration. |
| Default language, theme, and activity options | Values prefilled for new activities. |
| Generation job retention | Days before old job records are cleaned up; default 30. |

Per-activity options include illustrations, narration, timeline/progress display,
Engagement/Trust/Tension readings, debrief display, maximum attempts, replay,
grading method, completion on finish, and an optional minimum completion score.
Point grades and Moodle scales are supported.

## Grading, attempts, and reporting

- Decision quality is calculated on the server from recorded choices.
- Four skill dimensions—Presence, Adaptability, Empathy, and Clarity—move by
  `-2` to `+2` per decision and are normalised to a 0–100 score.
- Grade aggregation can use the highest, last, first, or average attempt.
- Completion can require finishing and can additionally require a minimum score.
- A score supplied by a browser is not trusted.
- A retried decision uses its sequence number to avoid recording the same choice
  twice.

The activity defines eight capabilities:

| Capability | Default purpose |
| --- | --- |
| `mod/aibranchedscenario:addinstance` | Add the activity. |
| `mod/aibranchedscenario:view` | View the activity page. |
| `mod/aibranchedscenario:attempt` | Make learner attempts. |
| `mod/aibranchedscenario:manage` | Author and edit. |
| `mod/aibranchedscenario:publish` | Publish revisions. |
| `mod/aibranchedscenario:generate` | Use AI generation. |
| `mod/aibranchedscenario:viewreports` | View learner reports. |
| `mod/aibranchedscenario:deleteattempts` | Delete learner attempts. |

`deleteattempts` authorises deletion; it is **not** an export capability. Privacy
exports are handled by Moodle's Privacy API and its own permissions and workflow.

## External services and data

For authoring operations, the plugin can send LMS Labs:

- source content and wizard values supplied by the teacher;
- the Site ID and API key used for authentication and credit accounting; and
- option lists and structural limits used to constrain generated output.

LMS Labs holds the downstream provider credentials. Learner attempt and decision
data are not part of the generation payload described by the plugin. Generated
responses are normalised and validated server-side before storage, and narrative
content is rendered as text rather than trusted provider HTML.

The client and diagnostic paths are designed to avoid exposing raw provider errors
and to redact credentials from recorded exchanges. This is a description of the
plugin's controls, not an absolute guarantee of zero data leakage. Administrators
must also assess Moodle configuration, hosting, logs, integrations, the LMS Labs
service, any Route B assistant, and applicable provider terms.

## Privacy

The Moodle Privacy API metadata covers:

- learner attempts and their scores, state, outcome, and timestamps;
- the per-decision event journey;
- authoring/generation jobs; and
- publisher attribution on immutable revisions.

Approved exports include the user's real attempt journey, their generation-job
records, and attribution for revisions they published.

Approved erasure deletes matching attempt events, attempts, and generation jobs.
It also recalculates or clears the affected grade and completion state. For a
teacher's published revision, erasure removes the `createdby` attribution while
retaining the revision itself, because active and historical attempts may still
depend on that immutable activity content.

Source content and Site ID transmitted to LMS Labs are declared as an external
location. Data already processed outside Moodle is governed by the relevant
service's retention and privacy terms and cannot be erased by Moodle's plugin
provider alone.

## Backup and restore

Activity configuration, published revisions, and generated media are included in
backup and restore. Learner attempts and decision events are included when the
backup includes user data. Course duplication creates a new activity without
carrying learner attempts into it.

## Accessibility

Choices and controls use keyboard-reachable interactive elements, focus is moved
as scenes change, live regions announce progress and outcomes, and colour is not
the only status signal. The interface honours `prefers-reduced-motion`.
Accessibility still depends on authored wording, generated media review, the
Moodle theme, and the site's wider configuration.

## Failure handling

- Generation jobs run in the background and retain a readable plugin error state
  when they fail.
- A failed first media write leaves the previous media set in place.
- Regenerating one media area does not intentionally delete the other.
- A credit or allowance refusal is surfaced rather than represented as a completed
  generation.
- Missing media can be identified and generated without substituting an unrelated
  image.

## Technical limits

- Scenario JSON contract version: `1`
- Maximum stored definition: 2 MiB (`2,097,152` bytes)
- Maximum nodes: `60`
- Choices per decision: `2–4`
- Maximum source content: `60,000` characters
- Maximum quick-start brief: `6,000` characters
- Maximum narrative string: `4,000` characters
- Maximum short string: `255` characters
- Maximum absolute skill delta per choice: `2`
- Maximum absolute dynamics delta per choice: `40`
- Crisis threshold: tension `75`
- Graphs must be acyclic

Teachers can edit generated text before publishing. v2.0.0 does not provide a
general visual graph editor for manually adding, deleting, or rewiring arbitrary
nodes.

## Development

- JavaScript source is in `amd/src`; built AMD artefacts are in `amd/build`.
- Rebuild AMD artefacts after changing source JavaScript.
- `.github/workflows/ci.yml` runs the plugin CI workflow.
- `docs/LMS_LABS_ROUTE_CONTRACT.md` documents the LMS Labs route contract included
  with the source package.

No automated test-count claim is made here: counts can change by environment and
should only be quoted with reproducible, release-specific evidence.

## Support

- Documentation: <https://lms-labs.com/docs/ai-branched-scenario>
- Repository: <https://github.com/lmshostingservices/moodle-mod_aibranchedscenario>
- Issues: <https://github.com/lmshostingservices/moodle-mod_aibranchedscenario/issues>
- LMS Labs: <https://lms-labs.com>
