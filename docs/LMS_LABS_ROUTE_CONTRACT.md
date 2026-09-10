# LMS Labs route contract for `mod_aibranchedscenario`

This document specifies the server routes the Moodle plugin calls. It is the
agreement referred to in `CLAUDE_HANDOVER_AI_BRANCHED_SCENARIO.md`: *"The exact
schema must be agreed with the Moodle plugin before either side is released."*

A reference implementation of every route in this document is delivered alongside
the plugin as `lmslabs-aibranchedscenario-route.ts`. It is deliberately **not**
packaged inside the plugin ZIP — it belongs in the LMS Labs repository, not in a
Moodle installation. It is written against the conventions described in the handover
but is not wired into the LMS Labs codebase; it exists so this contract is
executable rather than prose.

## Boundaries

- The plugin holds **no AI provider credentials**. It authenticates as a site with
  `siteId` plus an `aigr_…` API key and nothing else.
- LMS Labs owns the prompts, the provider calls, the credit ledger and the tariff.
- The plugin sends the option lists and structural limits it will accept, and
  re-validates everything that comes back. A route may not assume the plugin will
  store what it returns.
- No route may return a provider error string, a prompt, a stack trace or any
  credential fragment.

## Authentication

Every request carries, in the JSON body:

| Field | Type | Notes |
| --- | --- | --- |
| `siteId` | string | The customer site identifier. |
| `apiKey` | string | Matches `^aigr_[0-9a-f]{64}$`. Also sent as the `X-API-Key` header. |
| `pluginId` | string | Always `mod_aibranchedscenario`. |
| `pluginVersion` | string | The numeric plugin version installed on the site. |
| `requestId` | string | Idempotency handle, see below. |
| `contract` | int | Scenario contract version. Currently `1`. |

The server must resolve the API key to a client and then verify the supplied
`siteId` belongs to that client. Validating the key's shape is not authentication.

## Idempotency

`requestId` is `<operation>_<sha1 of the request payload>`. It is stable for a
given request, so a plugin retry after a timeout produces the same value.

The generic debit API does not accept an idempotency key, so each route below owns
its own charge and refund lifecycle:

1. Resolve `requestId`. If a completed result is already stored for it, return that
   result and charge nothing.
2. Pre-charge the approved credit amount.
3. Call the provider.
4. On any failure — provider error, timeout, schema failure, persistence failure —
   refund the exact amount charged and return an error.
5. Store the result against `requestId` before returning it.

## Envelope

Success:

```json
{
  "ok": true,
  "data": { },
  "model": "gemini-3.1-pro",
  "credits": { "used": 12, "remaining": 4088, "unlimited": false }
}
```

Failure:

```json
{
  "ok": false,
  "error": "INSUFFICIENT_CREDITS",
  "credits": 0,
  "buyUrl": "https://lms-labs.com/credits"
}
```

The plugin maps HTTP 401/403 to "credentials rejected", 402 or
`error: "INSUFFICIENT_CREDITS"` to "not enough credits", 429 to "rate limited", and
anything else to a generic failure carrying only the `error` token.

## Balance

```
GET /api/credits?siteId=<siteId>
X-API-Key: aigr_…
```

The plugin reads `creditsRaw` and `isUnlimited` and ignores the display value in
`credits`. A `creditsRaw` of `-1` is also treated as unlimited.

## `POST /api/aibranchedscenario/populate`

Fills the authoring wizard from pasted source content.

Request adds: `brief` (string), `sourceContent` (string), `language` (BCP-47),
`options` (the plugin's canonical option lists).

Response `data.fields` is an object using the plugin's own field names. Enumerated
fields must be exact members of the matching list in `options`; anything else is
dropped by the plugin.

```json
{
  "fields": {
    "title": "The fault nobody logged",
    "industry": "manufacturing",
    "audience": "Production shift supervisors",
    "setting": "warehouse",
    "atmosphere": "tension",
    "openingsituation": "…",
    "centralproblem": "…",
    "whyhard": ["timepressure", "safetyrisk"],
    "stakes": ["learnersafety", "compliance"],
    "participantrole": "Afternoon shift supervisor",
    "characters": [{"name": "Sam", "role": "Line operator", "trait": "Behind on quota", "appearance": "…"}],
    "principles": [{"title": "Log a fault before the next shift", "summary": "…"}]
  }
}
```

## `POST /api/aibranchedscenario/suggest`

Suggests one field. Request adds `field` (one of the plugin's suggestable field
names), `context` (the current wizard values) and `options`.

Response: `{"suggestion": "…"}` for a text field, or `{"values": ["timepressure"]}`
for a multi-select field.

## `POST /api/aibranchedscenario/generate`

The main operation. Request adds:

- `source` — the normalised wizard values plus `language`, `theme` and
  `contractversion`.
- `options` — the canonical option lists.
- `limits` — `maxNodes`, `minChoices`, `maxChoices`, `maxSkillDelta`,
  `maxMetricDelta`.

Response `data.scenario` must be a scenario definition in the contract below.

### Scenario definition

```json
{
  "version": 1,
  "title": "The fault nobody logged",
  "subtitle": "A late shift, a machine that should have been stopped",
  "role": "You are the afternoon shift supervisor on the packing line.",
  "setting": "Packing line, main floor",
  "language": "en-AU",
  "tone": "neutral",
  "complexity": "intermediate",
  "facilitator": {"name": "Sam", "role": "Line operator", "trait": "Behind on quota", "gender": "male"},
  "characters": [{"name": "Priya", "role": "Shift manager", "trait": "Direct", "appearance": "…"}],
  "principles": [
    {"id": "p1", "title": "Log a fault before the next shift", "summary": "…"}
  ],
  "openingmetrics": {"engagement": 55, "trust": 50, "tension": 30},
  "hook": "It is 2:40 pm. …",
  "startnode": "n1",
  "nodes": [
    {
      "id": "n1",
      "type": "decision",
      "title": "The vibration",
      "stage": 1,
      "bottleneck": false,
      "situation": "…",
      "facilitatorspeech": "Can't we just fix it ourselves?",
      "challenge": "What do you say to Sam?",
      "imageprompt": "…",
      "imagealt": "…",
      "crisisvariant": {"situation": "…", "facilitatorspeech": "…", "challenge": "…"},
      "choices": [
        {
          "id": "n1_a",
          "text": "Stop the line and log the fault now.",
          "signal": "positive",
          "consequence": "…",
          "feedback": "…",
          "principleid": "p1",
          "tags": ["escalates-appropriately"],
          "effects": {"engagement": 5, "trust": 10, "tension": -10},
          "skills": {"presence": 2, "adaptability": 0, "empathy": 1, "clarity": 2},
          "next": "n2"
        }
      ]
    },
    {
      "id": "end_strong",
      "type": "outcome",
      "outcome": "strong",
      "title": "The line restarts safely",
      "situation": "…",
      "summary": "…"
    }
  ],
  "debrief": {
    "whatmattered": ["…"],
    "criticaldecisions": ["…"],
    "practice": ["…"],
    "sourceconnection": "…"
  },
  "takeaways": [{"heading": "Speak early", "body": "…"}]
}
```

### Rules the generator must satisfy

The plugin's validator enforces all of these and rejects the whole scenario if any
fails. Failing them wastes credits, so the route should validate before charging is
finalised and refund if its own check fails.

1. **Node ids** match `^[a-z0-9][a-z0-9_-]{0,63}$` and are unique.
2. **Every `next`** names an existing node id, or the literal `__auto__`, which lets
   the plugin pick the outcome node matching the learner's score band.
3. **Reachability** — every node is reachable from `startnode`.
4. **Acyclic** — no path returns to a node it has already visited. Recovery is
   modelled as a forward path back to the main line, not a loop.
5. **At least one `outcome` node**, reachable from the start. Ideally one per band:
   `strong`, `mixed`, `highrisk`.
6. **Decision nodes** carry two to four choices; `outcome` nodes carry none. A
   `beat` node carries a single `next` instead of choices.
7. **Branch and bottleneck** — different choices lead to genuinely different nodes,
   and those branches reconverge at nodes marked `"bottleneck": true`. Do not emit a
   graph in which every choice from a node points at the same target.
8. **Delta ranges** — each `skills` value is an integer in -2..+2; each `effects`
   value is an integer in -40..+40. `openingmetrics` are 0..100.
9. **Enumerations** — `signal` is `positive`/`neutral`/`negative`; `type` is
   `decision`/`beat`/`outcome`; `outcome` is `strong`/`mixed`/`highrisk`; `tags` come
   from the plugin's `choiceTags` list.
10. **Size** — at most `limits.maxNodes` nodes and 2 MB of encoded JSON.
11. **Plain text only.** Every narrative field is plain text with `\n\n` paragraph
    breaks. No HTML, no Markdown, no scripts. The plugin escapes everything, so
    markup will be shown to learners literally.
12. **Principles first.** Identify three to five decision principles in the source
    content, then build the scenario around them, and point each choice's
    `principleid` at the principle it tests. Do not invent legislation, policy or
    technical requirements the source content does not support.
13. **A crisis variant** on any node where tension could plausibly exceed 75. It
    rewrites only the narrative text; the choices stay the same.

## `POST /api/aibranchedscenario/image`

Request adds `prompt`, `style` (one of the plugin's image styles) and
`aspectRatio` (`"16:9"`).

Response `data`: `{"base64": "…", "mimeType": "image/png"}`. The plugin accepts
`image/png`, `image/jpeg` and `image/webp`, checks the file signature against the
declared type, and rejects anything over 12 MB.

Use the shared Gemini-first banner-image service (`gemini-3.1-flash-image`, falling
back to `gpt-image-1`). Do not call retired Imagen 4 models, and do not copy the
legacy unguarded DALL·E routes.

## `POST /api/aibranchedscenario/speech`

Request adds `text` (at most 4,500 characters), `voice`, `language` (BCP-47) and
`format` (`"mp3"`).

Response `data`: `{"base64": "…", "mimeType": "audio/mpeg"}`.

Chirp HD voices follow `{languageCode}-Chirp3-HD-{voice}`. Which ledger narration is
charged against is a product decision that must be settled before this route ships:
the plugin assumes the generic LMS credit ledger, like every other route here.

## Open items requiring the product owner

1. The credit amount for each of the five billable operations: `scenario`,
   `populate`, `suggest`, `image`, `speech`. The plugin does not choose or display a
   price; it only reports what the service says it charged.
2. Whether narration is billed against generic LMS credits or sold as a SCORM Voice
   operation.
3. The catalogue record, `docsPath` and initial status for the plugin, which should
   start at `testing`.
