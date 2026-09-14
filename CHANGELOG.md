# Changelog

All notable changes to AI Branched Scenario are recorded here.

## [v1.61.0] - 2026-09-14

### Fixed
- **A scenario whose last decision named no successor was rejected whole.** An assistant
  writing five decision nodes states `next` on the ones with an obvious successor and omits
  it on the LAST one, where there is no later stage to name - which is precisely the node
  that has to reach an ending. The scenario failed with "Choice A on node n5 does not lead
  anywhere" followed by all three endings being unreachable, and the teacher who pasted it
  could do nothing about it: the fault was in the model's output and the only person who
  could fix it was the person who wrote the prompt. A choice with no stated successor now
  means "carry on", which is what the automatic target already means, so the last node's
  choices carry the learner to the ending they earned. A successor that is stated but does
  not exist is still a fault - that is a typo, not an omission.

### Changed
- **The prompt now shows a scenario terminating.** The skeleton had one decision node and
  three outcome nodes that nothing pointed at, so it never demonstrated reaching an ending -
  and a model copies the example. It shows `n1` and `n5`, with `n5`'s three choices naming
  the three endings.
- **The prompt no longer contradicts itself.** The skeleton's last decision was `n2` at stage
  2 - the same id and stage as the worked example, which points at `n3`. One prompt, two
  different `n2`s, with opposite jobs.
- **The rule is stated as well as shown**: every choice carries `next`, and the last decision
  node's choices name an outcome id or use `__auto__`.
- Removed a claim the code no longer makes ("a document whose choices all lead nowhere is
  rejected in full"). A rule the reader can disprove is a rule they stop trusting.
- **Two rules were printed twice** in one prompt, nearly verbatim - 427 characters of a budget
  that has to carry twenty-five rules. The concrete spelling examples (`-ise` not `-ize`)
  moved into the shared content standard, which means the **paid route now gets them too**;
  it had been receiving the vaguer half of that rule.
- The prompt is 415 characters shorter and carries more.

### Added
- Harness checks that render the whole prompt and read it as a model would, rather than
  reading the PHP that builds it: the shape is valid JSON, its last decision reaches the
  endings, no node in it collides with the worked example, no rule is stated twice, and no
  rule claims a rejection that no longer happens.

## [v1.60.0] - 2026-09-14

### Added
- **A per-slide quality audit** - `preview/slides.mjs`. The existing sweeps check the page;
  this checks every rule against every slide, one slide at a time, at three widths and in
  both schemes, and names the slide when it fails. The faults that kept reaching a learner
  were never "the page is broken" - they were "this one screen is missing the thing every
  other screen has", which a page-wide sweep reports as zero.

### Fixed
Thirteen faults, all found by that audit on its first run, on screens nothing had checked:

- **Eight screens had text that ignored the fit** - the opening lesson cards, the ring
  labels, the skill rows, the decision records and all three lesson lists. Each had no
  font-size rule of its own, so it sat at the shell's 16px while the type around it grew and
  shrank. That is what "the text is all the same size" and "this text is too small" both
  were, and they were being fixed one screenshot at a time because no sweep asked the
  question per slide. Asking "is it 16px" cannot answer it either: `--aibs-text-body`
  resolves to exactly 16px at rest, so a sized block and an unsized one look identical
  standing still. The audit sets the fit to 1.4 and reads it back.
- **The prose container was unsized**, only its paragraphs - so any text in a prose block not
  wrapped in a paragraph stood still.
- **The withheld-debrief screen was outside the scaled selector list entirely**, so nothing
  on it moved with the fit.
- **Tap targets of 34px** on the avatar and the icon buttons. 44px on a coarse pointer.
- **The opening slide borrowed the first lesson slide's picture**, so the screen a learner
  meets first and the screen immediately after it showed the same photograph - on the two
  slides where it is most obvious, because they are consecutive. It takes the frame of the
  node the scenario opens on.
- **The opening slide never said it had no picture**, so an opening with no frame kept the
  two-column silhouette and drew the words into half a card with the other half empty.

## [v1.59.0] - 2026-09-14

### Fixed
- **A media run that finished only part of the set was recorded as a success.** Only a run
  that made NOTHING counted as a failure, so a scenario asking for forty clips and twenty
  pictures that produced twelve of them was filed as "ready" - and the teacher was told
  nothing, because from the plugin's point of view nothing had gone wrong. What they got was
  a scenario where some slides spoke and some did not, some had pictures and some did not,
  with no pattern to it and nothing anywhere saying why. Every "no voiceover on this slide"
  report was that, and it read as a player fault for days because the only record of it said
  the media was fine. A short run is now recorded as a failure, and the message says how much
  of the set is missing as well as why - "Only 12 of 60 pictures and clips were made, so some
  slides have no picture or no narration. Generate the media again to finish the set."
- The caution mark on the closing card was drawn 42 units wide inside a ring of radius 42, so
  its corners crossed the stroke and it read as two shapes colliding rather than one sign.

## [v1.58.0] - 2026-09-14

### Fixed
- **Saving a pasted scenario ended in the browser's own "Leave site?" dialog.** The guard
  that holds the page while work is in flight could not tell a teacher closing the tab
  mid-import from the import FINISHING and sending them to the review step - so a successful
  save asked them to confirm a navigation they never asked for, on top of the dialog the
  wizard had just shown. A navigation the wizard performs itself now declares itself first.
- **Two of the four type steps on a slide were missing.** The role callout and the situation
  itself had no font-size rule at all, so they sat at the shell's own 16px while the label,
  the heading and the figures around them scaled with the fit - the three things that should
  differ most read as one size. Worse, it inverted: the consequence, set in the lead scale,
  came out SMALLER than the feedback panel under it whenever the fit stepped down. Every
  block now takes a size from the scale, and a slide heading takes a real step above the
  paragraph rather than being the same text in bold.

### Changed
- **The three readings are rows, not columns.** Dial, name, change and band stacked four deep
  meant three readings took the height of a paragraph and every piece of text in them had to
  be small to fit. A reading reads across now - dial left, name and change right, band under
  the name - which is a third of the height, and the room that buys goes into the type and
  the space between the cards. On a phone the change drops to its own line rather than being
  pushed to the far edge of a narrow card.
- Section labels are set as labels: small caps, letter-spaced. A chip that carries a label
  AND a value keeps the value readable - "PRINCIPLE TESTED" is a label, the principle itself
  is content, and setting both in caps at label size made the one piece of content in the
  pill the hardest thing on the card to read.
- The skill rows' descriptions take body size. That line is the only part of the row that
  teaches anything, so label size was the wrong choice for it.

## [v1.57.0] - 2026-09-14

### Fixed
- **The decision record never said which decision it was recording.** Each card played the
  consequence clip - which reads what followed and why it mattered, and never names the
  decision it followed from - so a learner listening to their own record heard five outcomes
  with no decisions attached to them, while the heading and the choice they had actually
  taken sat on screen unread. Each card now has a clip of its own that reads the whole card:
  the moment, "You chose: ...", what followed, and why it mattered. A revision generated
  before this still falls back to the consequence clip it has, so nothing goes silent.

## [v1.56.0] - 2026-09-14

### Changed
- **The four list pages of the debrief are played, not shown.** Lessons learnt, critical
  decisions, how to apply this and key takeaways each arrived whole - five cards in a grid
  with one recording of the entire list read over the top, which is a wall of text with a
  voice somewhere behind it and no way to tell which line is being read. Each page now plays
  itself: a card floats up, the picture beside it changes to the frame from that part of the
  scenario, that card's own clip reads it with the card marked, and the next arrives when the
  clip finishes. The list is one column, because cards arriving side by side have no order to
  arrive in. Everything is in the markup either way - with narration off, muted, reduced
  motion asked for, or a browser that refuses to play, the whole list is simply shown.
- **One mark per band on the closing card.** It was a tick or an exclamation, keyed to
  whether the run was flawless - which drew a green exclamation mark on a strong outcome: a
  warning in the colour of a success, telling a learner two contradictory things at once. A
  tick when it went well, a caution when it was mixed, a cross when it cost.
- **The celebration is for a run that went well, not only a flawless one.** 88.8% and a
  strong outcome is a result worth marking; a closing card sitting in silence after one reads
  as the product having missed what happened. The tick still belongs to a flawless run only -
  the two answer different questions.
- The deck takes its arrow away at the end rather than greying it. A disabled arrow is a
  control that cannot do anything, sitting where a learner expects one that can.

### Fixed
- The three figures on the closing card did not line up: the outcome pill sat higher than the
  numerals beside it and its label sat lower than theirs, so the row read as three separate
  things. They are a grid with equal tracks now - values on one line, labels on another,
  whatever shape the value is - and the pill may take two lines rather than forcing the row
  wider than the card.
- The decision-quality figure stayed accent blue on a banded card: the banded rules tied on
  specificity with a later single-class rule the same element carries.
- The closing sentence took the lead size and the fit together, which on a card with room to
  spare made the one instruction on the page larger than the heading above it.
- On a phone the three closing figures stayed in a row: the container had become a grid and
  the phone rule was still speaking flex to it.

## [v1.55.0] - 2026-09-14

### Changed
- **The card being read out loud now says so.** A page that reads two cards one after the
  other gave a learner no way to tell which one they were hearing - two cards, one voice, and
  the reader left matching the words to the column by guesswork. The card being read wears
  the lift the pointer already gives it, plus a soft accent ring, and carries `aria-current`
  so a screen reader is told the same thing rather than being left with the problem in sound.
  It is driven by the clip queue, so it is right by construction rather than by a second
  piece of bookkeeping that can drift.
- **The imagery now has an emotional temperature that rises across the set.** The three
  endings differed only in lighting - a strong one read "resolved and under control, people
  at ease in their posture", which describes calm, not success: nobody in it is pleased and
  nothing has closed, so a learner who scored 89% was shown a quiet office. Each ending is
  now a moment with people reacting in it - the handshake just released and someone laughing,
  or the room after everyone has gone - and the decision frames ramp from cool through
  warming to hot, named as behaviour a camera can see rather than as adjectives. What is
  felt is what is kept; a set held at one temperature gives a learner nothing to feel.
- **The skills page is heard as well as seen.** Four bars filling in silence was the barest
  screen in the product; it now reports its own band in the same voice the consequence cues
  use, taken from the class the bars already carry rather than worked out a second time.
- **A neutral consequence is no longer silent.** It was skipped on the reasoning that nothing
  much had happened - but the learner made a decision and it neither helped nor cost, which
  is a result, and a screen that reports a result in silence reports it as nothing at all.
- **The type fills a screen that has room to spare.** Only five of the ten sizes in the scale
  could be scaled at all, so on a card with space left over the prose grew and the label, the
  ring name, the figure and every heading beside it stood still - which is the fault a learner
  reports as "why is all the text so small with all the room you have". Every size in the
  scale now has a base the fit multiplies, and the ceiling is a third larger rather than half
  again, which uses the room without letting body copy overtake its own heading.
- **The two consequence cues no longer sound alike.** They were two quiet sine notes a third
  apart - near-identical shapes at near-identical volume, separated only by direction. Well
  judged is a major triad that builds and arrives, with a fifth underneath for body. Costly is
  thicker, slightly detuned and falls: firm rather than punishing, because this is a learner
  being told a decision cost something, not a buzzer telling them off.
- **The options answer the pointer.** Crossing a lettered option plays a short, quiet blip,
  pitched a step higher down the list so moving through them is a small scale rather than the
  same note four times. Once per arrival, silent on touch devices where hover arrives with the
  tap, and silent for anyone who turned narration off, muted the player or asked for reduced
  motion - like every other sound the player makes.

### Fixed
- **A page of the decision record read one card and stopped.** The debrief puts two decisions
  on a page and each has its own clip, but the page was handed
  `slice.find((entry) => entry.audiourl)` - the FIRST decision with a recording - so the
  second card was never read, on any page. A slide now carries a queue of clips and plays
  them in order, without re-holding Continue between them; one clip that will not load no
  longer silences the rest of the page.
- **The spoken line was read out twice.** The character's own recording was chained onto the
  end of the narration so a learner listening straight through would hear it - but the
  narrator's clip already reads the quoted line as part of the situation, so the same words
  played twice in two different voices, one after the other. The avatar plays it, which is
  what a control is for; and because it is now optional it no longer holds Continue either,
  which would have locked the screen for anyone who never pressed it.

## [v1.54.0] - 2026-09-14

### Changed
- **Images.** Every page of the debrief drew the same frame, scenes repeated between slides,
  and a character named in scene one could come back as a different person in scene three.
  The brief was also mostly governance: roughly two thirds of it described the rules of the
  set and about a tenth described what was actually in front of the camera, so the model
  filled the gap from the training mean - and the training mean for "meeting room,
  professionals" is the stock photograph. Specifically:
  - The cast sheet now travels with **every** frame. It used to be included only when that
    scene's own prose happened to name somebody, so a lawyer called Mark was simply absent
    from the brief for any scene that did not say "Mark" - and a model told to draw a lawyer,
    with nothing said about which one, draws a different one.
  - A scene that names nobody is drawn with named cast members rather than falling through to
    "one worker, seen from behind", which was how a set acquired people who were in no other
    frame.
  - Each frame names a physical object the scene is about, chosen from the words the scene
    already uses, because a model renders nouns and "the client cares about money more than
    timing" has no pixels.
  - The lighting rig is chosen from the setting rather than hashed from the scenario, so a
    night shift no longer gets late-afternoon west windows - a rig that contradicts its own
    location is rendered as mush, which is what read back as poor lighting. It then ramps
    across the set: open and even early, harder in the middle, hardest at a crisis, and
    resolving or dropping away at the ending.
  - The composition is chosen from how many people are in the frame and what kind of moment
    it is, rather than by hashing the node id, and each option carries a focal length, a
    camera height and something in the foreground.
  - Hands, eyelines, one incongruous detail and an explicit refusal of the stock photograph
    are now in every brief.
  - Removed: "with the people in the middle third of the frame" (a written request for the
    centred stock composition), "outwardly ordinary / routine work continuing / steady light"
    (asking for boring and receiving it), the abstract list of the learner's options (a model
    cannot photograph a proposition), and "cinematic, shallow depth of field" (the most
    diluted phrase available, and it asserted a lens and a light over the ones the frame had
    just chosen for the scene).
  - The blanket ban on text was fighting the props - the best props are paper - so paperwork
    is now present but unreadable rather than absent.
- The content standard gained a rule requiring the same named cast in every scene, since the
  illustrations are briefed from that text: a scene that stops naming someone is a scene that
  gets a picture of a stranger.
- **Work in progress is a modal.** It was a strip at the top of a long form while the teacher
  watched the button they had pressed at the bottom of it, and it left the page fully usable -
  so the button could be pressed twice and the browser navigated away mid-import, abandoning a
  half-written scenario or credits already spent. It now covers the page, says what is being
  made stage by stage, cannot be dismissed, and blocks navigation until the work is done.
  "Save and apply" was also missing from the set of buttons disabled while work is running.
- **Publishing says so.** It ended in a toast that had faded by the time the teacher looked
  up, on a page that looked exactly as it had before. It closes on a stated result that names
  the next step - switching to student view.

### Fixed
- The image brief was cut at the character when it ran long, leaving briefs ending mid-word.
  That is not a shorter instruction; it is a sentence the model has to guess the end of. It
  falls back to the last full stop.
- One verbose character record, or a paragraph-long setting, could spend the entire brief
  budget on its own. Both are capped, as is a teacher's own direction.
- The harness reported strings added in the current release as missing, because the string
  manager's cache was built before the plugin was synced in. It resets before it is asked.
- The browser sweeps inlined the stylesheet at build time and were measuring a page assembled
  two days earlier - reporting "0 findings" against CSS that was not the CSS being shipped.
  Every sweep now rebuilds the page first.

## [v1.53.0] - 2026-09-14

### Changed
- The debrief drew the same outcome photograph on every one of its pages - the lessons, the
  practice points, the takeaways, the decision record and the closing card. It showed a
  reader nothing the outcome page had not already shown, and it cost each of those pages
  half its width. The picture now appears once, on the ending, which is the page whose
  subject it is.
- With the picture gone, the lesson and takeaway lists pair up across the card above 900px,
  the way the decision record already did, and stack again below it.
- The closing sentence on the last page took the shell's base size, which made it the
  smallest text on the last screen a learner sees. It takes the lead size and a measure of
  its own.

- The closing card said the same thing after every run: a green ring, a green tick, a grey
  pill and an accent-blue figure. A tick means every decision was the best one available, so
  it is kept for the run that earns it; every other run is marked with the alert, and the
  mark, the outcome pill and the decision-quality figure all take the band the score earned.
  The heading now says how the run went instead of announcing that it is over.
- A mixed outcome's pill was grey. Everything else this product reports is banded red, amber
  or green, so a grey pill between a green one and a red one read as no reading at all.
  Mixed is the amber band.

- Every page of the debrief drew the same frame - whichever one the scenario opened on - so
  the lessons, the critical decisions, the practice points and the takeaways were illustrated
  by a photograph of a room nobody was talking about any more. Each of those pages is now
  briefed its own frame from its own words, through the same prompt builder every scene uses.
  A page whose frame is missing takes the next unused scene from the scenario rather than
  falling back to the opening one, so no two pages look alike.

### Fixed
- **Text was being cut off mid-sentence on decision cards, silently.** The blocks that carry
  words are flex children, and a flex child's default is to be shrinkable below its own
  content; with `overflow: hidden` on it, the text that no longer fits is simply not drawn.
  Measured on a reported card: the situation paragraph had a client height of ZERO with 339px
  of text inside it, while the slide body reported seven pixels of overflow. Seven pixels is
  two steps of the type scale, so the fit loop stepped down twice, saw the body fit and
  declared the screen done - with the sentence severed mid-word. Two changes: the blocks that
  carry words no longer shrink below their text, so whatever will not fit becomes honest
  overflow on the body; and the fit loop now asks the whole screen whether anything is cut
  off rather than asking the body alone.
- The celebration fired on every finish, so a learner who had just been told their decisions
  cost the deal got confetti and a chime for it. It is for a perfect run and nothing else.
- Confetti fell twelve pixels. The fall was `translateY(120%)`, and a percentage on
  translateY is a percentage of the piece rather than of the layer it falls through, so the
  effect was a band of colour along the top edge of the card that faded where it started.
  Each piece is now given the distance it has to cross, with drift and a longer fall.
- A duplicated block at the end of the stylesheet was cancelling the closing card's column
  layout, so below 900px the mark, heading, figures, sentence and buttons were laid out side
  by side. The duplicate is gone.
- The type scale only ever stepped down, so a slide with room to spare showed the same words
  with more white around them. Growth was gated on fullscreen, which is not where a learner
  meets a slide; the gate is gone and the type grows wherever the slide is drawn.
- On a decision's consequence the three readings came before the note explaining what the
  decision did. The note is the point of the screen and now comes first.

## [v1.52.0] - 2026-09-14

### Fixed — the opening slide still did not play

The clip was being generated and published, and then never asked for. v1.47.0 added the
narration; it did not add the playback. The slide is not a node, so no node payload covered
it, and its article carried no `data-audio` attribute to play one from. **Generation without
playback is silence that costs credits.**

`player.php` now builds the URL and the slide carries the attribute. Verified by rendering
the real template from a real revision: the attribute comes out with a live pluginfile URL.

### Fixed — the spoken line was never read aloud unless you clicked the avatar

A line said by a named character is deliberately left out of the narrator's clip — it has one
of its own, in that character's voice, which is what stops a scenario sounding like one
person reading a play aloud. **Nothing ever played that second clip.** It was reachable only
by noticing the avatar and pressing it, so a learner listening straight through heard the
title, the situation and the question, and never the line between them — the part the scene
turns on.

The narration now runs into the spoken line automatically. The avatar still plays it on
demand and still marks itself played. The way on already waited on `speechDone`, which is how
this was always meant to work.

### Fixed — the audio button was a stop button, not a mute button

Muting called `stopAudio()`, which pauses the clip **and discards it**, so unmuting had
nothing to resume and started the narration again from the top. A learner who silenced one
sentence lost their place in the whole scene and had to sit through it a second time — which
is not what a speaker icon promises.

Muting now silences the element and leaves it playing, so unmuting picks up exactly where the
voice had got to. Speech synthesis has no muted property, so it is paused and resumed, which
is the same thing from the listener's side. Muting still hands the way on back, as before.

### Changed — the paste screen stopped calling things definitions

"Definition" is the plugin's word for its own JSON, not a teacher's word for anything. The
screen where somebody arrives holding whatever an assistant just gave them asked them to
paste a "definition" and then to "import" it — two pieces of jargon for one plain act.

- **Paste the prompt output here** (was "Paste the definition here")
- **Save and apply** (was "Import definition")
- The section heading and the three error messages on that path were reworded to match.

## [v1.51.0] - 2026-09-14

Everything here was found by auditing my own work from today, and all of it is mine.

### Fixed — 1,197 lines of the stylesheet were duplicated

A whole section of `styles.css` appeared twice, byte for byte — roughly a fifth of the file.
It came from one of my own scripted edits earlier today splicing a block out and re-appending
it without removing the original.

It was not cosmetic: duplicated `@media` blocks and rules mean the later copy silently wins,
so any override written between the two copies had no effect. Two existing harness checks —
counting `aspect-ratio: auto` and the frame's media query — are what caught it, by reporting
"expected 2 got 3". Both were written as exact counts, which is usually a brittle way to
check anything and was exactly right here.

### Fixed — the closing card was laid out sideways on a phone

`.aibs-deckend-body` inherited a flex **row**. Above 900px the no-picture column treatment
overrode it; below, nothing did — so the completion mark, the heading, the three figures, the
sentence and the buttons were laid out side by side in 170px columns.

### Fixed — its confetti escaped the card

The layer is `position: absolute; inset: 0`, and the card was not a positioned ancestor, so
it measured itself against whatever further up the page was — spilling 46px past each edge at
every width. The other finish card had `position: relative; overflow: hidden` for exactly
this reason; this one was given the markup and not the containing block.

### Fixed — every deck slide lost a quarter of a phone screen to its arrows

`.aibs-deck-stage` reserves 46px each side so the arrows sit beside the card. At 1440px that
is three per cent of the width and invisible. At 380px it is 92px, and **every deck slide in
the product** — the lesson deck and the debrief both — was rendering into about 208px.

Below 600px the arrows now sit under the deck, where a thumb can reach them more easily than
a 34px target pinned to the screen edge, and the card gets the width back: 208px to 300px.

### Fixed — a dead line, and a check that required it

I added `this.audioEnabled = this.root.dataset.audio === '1'` to the debrief handler and a
harness check asserting it was there. It is a no-op — the property is set once from the same
attribute and nothing changes it at runtime — so the check was keeping dead code alive. Both
removed; the check now asserts the deck actually carries and plays the clips.

### Changed — the new breakpoint was not on the list

My phone rules introduced a fourth breakpoint at 640px. The project keeps a deliberately
short list — 420, 600, 899 — and a harness check enforces it. Moved to 600.

## [v1.50.0] - 2026-09-14

### Added — the closing card celebrates

**Confetti**, in the product's own palette rather than party colours, so the moment reads as
this product marking an ending rather than a widget bolted on. Forty elements and one
keyframe — no library, no image. It already existed for the no-debrief finish screen and had
never been fired for the debrief's own ending.

It is now scoped to the card that asks for it: `dropConfetti()` took the *first* confetti
layer on the page, which was fine while there was one and would have been wrong the moment
there were two.

**A short rising chime** — a major triad, arpeggiated, under half a second. Synthesised from
an oscillator rather than loaded: a sound file would be one more asset to ship, to serve
through pluginfile, and to have blocked by a theme. Each note is shaped rather than switched,
because an abrupt start on a sine is a click.

**Both stay out of the way.** The chime is silent for a learner who turned narration off — a
person who did not want a voice did not ask for a chime instead — and for anyone who asked
for reduced motion, since that request is for less going on rather than specifically for less
movement. The confetti was already guarded both ways. Neither plays twice, and a browser that
refuses to open an audio context simply makes no sound; nothing here is load-bearing.

## [v1.49.0] - 2026-09-14

### Fixed — most scenarios had no ending at all

The closing card was wrapped in `{{^hastakeaways}}` — it was drawn **only when a scenario
had no takeaways**. Every normal scenario has them, so every normal scenario simply stopped
on a list: no completion, no result, and the Try again button buried under the last takeaway.
A learner pressed the arrow to find out whether there was more.

The reasoning behind that had been sound as far as it went — a page whose only content is
"you have reached the end" tells someone what they can already see, so it was removed. The
mistake was removing the page rather than giving it something to say.

### Changed — the ending carries the verdict

The card is always drawn now, and it closes on what the learner actually did:

- a drawn completion mark
- **the outcome they earned**, in words
- **their decision quality**, as the headline figure
- **how many decisions it took**
- Try again, and keep a copy

Every one of those figures was already in the debrief payload. The result sat on page two of
nine and page nine had nothing on it.

The actions moved off the takeaways page, so the way on appears once, where the scenario
ends, rather than at the foot of the page before.

### Fixed — three labels were sentences with their subject removed

`decisionstaken` is `'Decisions taken: {$a}'`. Used as a bare label it rendered as
**"DECISIONS TAKEN:"** with a dangling colon. Three standalone labels added, and the harness
now fails any closing-card label that carries a placeholder or ends in a colon.

## [v1.48.0] - 2026-09-14

### Added — the ending and the whole debrief are narrated

A learner who turned narration on heard every screen of the scenario and then silence for
the half of the product that explains what just happened. That was not a fault to find: it
was a decision taken in `player.js` — *"the debrief is read, not listened to"* — and never
stated anywhere a teacher could see it. The ending was skipped too: `generate_for_definition`
skipped narration for `outcome` nodes, so the screen the whole scenario exists to deliver
arrived silent.

Now narrated: **the ending**, **What mattered**, **Apply it in practice**, **Key takeaways**,
and **each decision record**.

**The decision records cost nothing extra.** The clip for a decision already exists — it is
the one played on the consequence screen when the learner made that choice, stored under the
choice's own id. The record of the decision plays that same clip rather than a second being
generated for the same words. Only three new clips per scenario are generated, one for each
fixed debrief screen.

**Proved by running it, not by reading it.** With a stub provider on a real finished attempt:

```
activity 51, revision 13: narrations 21/21

--- THE DECISION CARDS ---
  decision 1  choice n1_a      READ OUT
  decision 2  choice n2a_a     READ OUT
  decision 3  choice n3_a      READ OUT

--- THE OTHER DEBRIEF SCREENS ---
  the ending           READ OUT
  what mattered        READ OUT
  apply in practice    READ OUT
  key takeaways        READ OUT
```

**Existing activities need their media regenerated** to pick up the clips these screens now
ask for. Nothing breaks without it — a screen with no clip is simply silent, as before.

### Changed — two harness checks that held the old behaviour in place

One asserted the debrief was *not* narrated, quoting the comment that made it so. The other
counted one exact attribute order and failed because `data-audio` was added between the two
attributes it matched. Both now check the intent rather than the spelling.

## [v1.47.0] - 2026-09-14

### Fixed — THE ROOT CAUSE: every media file destroyed the one written before it

This is the fault behind "no images" and "no voiceover", and it is a single line.

`media_manager::store()` deleted **every file sharing the item id** before writing, and every
asset belonging to one node shares one item id. So each write destroyed its predecessor:

| Written for node `n1` | Effect |
|---|---|
| scene `n1` | stored |
| crisis scene `n1_crisis` | **deletes `n1`** |
| narration `n1` | stored |
| speaker's line `n1_said` | **deletes `n1`** |
| choice clip `n1_a`, then `n1_b` | **each deletes the last** |

Exactly one image and one narration survived per node, and the survivor was whichever was
written last — a *choice* clip. **The narration a learner hears when a screen opens never
survived at all.**

Measured on a seven-node scenario with a stub provider, so the numbers are the plumbing and
not the service: **15 narration clips generated and charged for, 7 files left**, none of them
node narration; and a node with a crisis variant lost its ordinary scene image too.

Everything about this looked like media that had never been generated — which is why the
first four explanations I offered were all wrong.

The comment on the principle clips already said *"storing a file clears whatever else shares
its item id"*. That was known, worked around for the principles by giving each its own id,
and left in place for everything else.

`store()` now replaces the file it is writing and nothing else. Same scenario after the fix:
**8 of 8 images and every node's narration present.**

**Guarded by behaviour, not by reading.** The harness stores two assets under one item id and
fails if either disappears, then rewrites one and fails if the count changes.


### Fixed — a reading measure was applied to screens made of record cards

My own regression, introduced in v1.45.0 and reported three times before I caught the shape
of it. That release gave a no-picture slide one centred column at a 76ch reading measure,
which is right for a paragraph and wrong for every screen whose content is a list of cards.
The decision record, the lessons, the skills, the takeaways and the two-column consequence
were all squeezed into a single column's width with roughly a third of the card left empty
down each side.

A measure is for a line of prose. A screen of records uses the card. Named by the slide
classes the template already writes rather than worked out with `:has()`, so the decision
comes from the data.

**Guarded by measurement, not by eye.** The alignment sweep now measures how much of its
card each no-picture slide actually uses and fails any record screen under 85%. The fault it
catches left them at about 55%. The two-column consequence had been *exempted* from that
sweep — which is precisely why three releases went by without it being caught.

### Changed — the decision records sit two across

They were two to a page but stacked, so on a wide card the second sat under the fold with
the right-hand half of the slide empty. A decision record is short and self-contained, so
two read comfortably as a pair. Below 900px they stack again.

### Fixed — the opening situation was never narrated

The first screen a learner sees is built from the scenario's `hook` and `role`, and it is
not a node — so the narration loop, which walks nodes and principles, never reached it, and
the deck slide carried no audio attribute to play one from even if it had. A learner who
turned narration on met silence, then heard every screen after it, which reads as the
narration being broken rather than as one screen missing it.

## [v1.46.0] - 2026-09-14

### Fixed — media could fail completely, on either route, and leave no trace anywhere

Reported as "no images or voiceover was generated at all" on the paste route. The root cause
is not one fault but four, and all four are mine. Together they made the symptom impossible
to diagnose from the outside — which is why the first response was to guess at cron.

**1. Every failure was swallowed into silence.** Five separate `catch` blocks in
`media_manager` funnelled every refused illustration and every refused clip into
`debugging(..., DEBUG_DEVELOPER)` — which writes *nothing at all* on a production site. A run
in which the service refused all fourteen requests finished "successfully", reported zero,
and left no record anywhere a site owner would ever look. Each failure now records the
service's own error identifier and mtraces at normal level.

**2. The import route wrote no job record.** The whole reporting chain hangs off the job: the
wizard polls it, `get_job_status` turns a count into "3 of 14", a failure is stored where it
can be read. Generation writes one. The media task on the **import** route wrote none, so
none of that machinery ever ran for a pasted scenario. It writes one now, and records nothing
made against something wanted as a failure rather than a success.

**3. The status check counted illustrations and not narration.** A scenario that came back
completely silent — every clip refused — reported nothing at all, which is half of exactly
what was reported broken. It counts both now, and says what the service refused rather than
only how many are missing.

**4. The teacher was never told media was still coming.** The import service returns how many
illustrations and clips were queued, and **nothing read it**. So a teacher who pasted a
scenario in landed on a review page with no pictures, no sound and no indication any were on
their way — media is made by a background task minutes later. That is indistinguishable from
a scenario that failed, which is what it was reported as. The page now says so.

### Fixed — a failed media run destroyed the media that was already there

`clear_working_media()` ran *before* the first request. A run in which the service refused
everything therefore deleted the existing pictures and narration and put nothing in their
place — so trying again to fix an activity made it strictly worse. The old set is now cleared
at the moment the first new asset is ready to replace it, and not before.

### What this does and does not fix

It does not make media appear where the service is refusing it. **It makes the reason
visible** — on both routes, in the job record, in the cron log, and on the teacher's screen.
The underlying refusal is now answerable rather than a guess.

## [v1.45.0] - 2026-09-13

### Fixed — a slide with no picture had six different left edges

Reported with a screenshot, and the measurement was worse than it looked: on a decision card
at 1440px, **six blocks sat at six different left edges, 467px between the outermost two.**
The pill started in one place, the heading in another, the prose in a third, the spoken line
in a fourth, the question in a fifth, the choices in a sixth.

Every rule that produced it was defensible on its own. The body centred its children — and a
centred flex child shrinks to fit its own content, so each block ended up a different width
and therefore at a different left edge. An earlier fix widened the prose to the full card to
stop it reading as a narrow strip, which was right about the strip and made the alignment
worse: the prose then began further left than everything beneath it.

A reader follows one left edge down the page. So **the column is now the thing that is
centred**, once, on the body — and the children fill it. They no longer have widths of their
own to disagree about. The measure is wide enough to use the card and short enough to still
read as prose.

Two exceptions, both deliberate and both stated rather than discovered: the finish screen is
a statement and stays centred, and a consequence with no picture keeps its readings in the
column the picture would have had.

**Guarded permanently.** A new sweep measures the left edge of every block on every
no-picture slide at five widths and fails on a second edge or on centred text, with only
those two exceptions allowed.

## [v1.44.0] - 2026-09-13

### Fixed — every choice in a pasted scenario led nowhere

Reported live. A scenario drafted through the paste route came back with **`next` missing
from every choice on every node**, so nothing was reachable, no ending was reachable, and the
validator rejected the whole document:

> Choice A on node n1 does not lead anywhere … No ending can be reached from the start of the
> scenario.

The cause was in the prompt, not the model. The prompt carries one fully written-out decision
node as its example, and **none of that example's three choices had a `next`**. A sentence
above it explained that `next` had been abbreviated away along with three other fields — but
a model copies the example it can see, it does not reconstruct what a caveat says is missing.

Every choice in the worked example now carries `next`: two pointing at a named node and one
showing the `"__auto__"` pattern the last stage needs, so both forms are demonstrated rather
than described. The caveat now says the opposite — that `next` is present and that a choice
without one leads nowhere.

**Guarded permanently.** The harness parses the worked example as JSON and fails if any
choice is missing any field a choice needs, and if it does not show both a named target and
the automatic one. Prose about the example is no longer a substitute for the example.


### Fixed — the content standard would have shipped and done nothing on most sites

v1.43.0 sends the full standard only where the service advertises that it accepts the field,
which is right. But the only thing in the plugin that ever read that advertisement was the
**settings page**. On any site where no administrator happened to open plugin settings after
upgrading, nothing was ever recorded, the budget stayed at zero, and the standard was
silently never sent — the feature would have shipped and quietly done nothing.

The generation path now asks for itself, at most once a day, on a request that is already
about to be far larger. An unreachable service is left to the generation to report: the probe
is marked done either way, so a service that is down does not add a failing request to every
generation, and an absent answer keeps the field switched off, which is the safe direction.

Verified live by LMS Labs the same day: the status route currently exposes
`limits.contentStandardCharacters: 20000` and **no `capabilities` block at all**, so the
legacy positive-limit path is the one actually in use for generate, and populate stays
correctly disabled.

## [v1.43.0] - 2026-09-13

### Fixed — pressing Generate again returned the scenario you had just rejected

LMS Labs added retry protection: a repeated request handle reuses the saved result rather
than charging a second time, and they confirmed the handle they key on is the `X-Request-Id`
header the plugin has always sent.

The plugin built that handle from a hash of the request payload. Two deliberate presses of
Generate on unchanged wizard inputs produce byte-identical payloads — so they produced the
same handle, and the teacher would have been handed back the scenario they had just rejected
with nothing on screen to say nothing had regenerated.

The handle now identifies the **job**, not the words. One generation has exactly one job row
(the plugin already refuses to open a second while one is queued or running for the same
activity), and a retried background task re-runs against that same row. So:

- a retry of one generation keeps its handle — still recognised, still not charged twice
- a deliberate regeneration gets a new handle — fresh scenario, charged once

The key travels in the header only; the request body is built from an allow-list of named
fields and cannot carry it.

### Added — the job's outgoing request is kept and replayed

On LMS Labs' own recommendation. The service matches a repeated handle against the request
body it saw the first time, so a retry that rebuilt the body — with, say, an upgraded
plugin's content standard in it — would be refused as a conflict rather than resumed. The
body is now built once, stored on the job **before** the first call so an attempt that dies
mid-flight still has something to replay, and replayed on every later attempt.

New column `aibranchedscenario_jobs.payloadjson`, declared to the Privacy API.

### Added — a handle conflict is a message, not a stack trace

`409 IDEMPOTENCY_CONFLICT` is recognised by its error code rather than its wording, as the
service asks. It neither generates nor charges, so the teacher is told exactly that and
asked to generate again.

### Added — the two routes finally state the same standard

LMS Labs added a `contentStandard` field to the generate route, capped at 20,000 characters
and sitting alongside `instructions` rather than replacing it. The plugin now sends the
twelve rules there **at full length** — 3,584 characters, the same wording the pasted prompt
uses — instead of the 1,464-character compressed form that had to share one small field with
the teacher's own brief.

The `instructions` field goes back to what it was always for: the teacher's words and the
cast, which had been competing with the standard for the same 2,000 characters and losing.

**Sent only where the service says it is accepted.** The routes validate the request body
before authenticating it, so a field a route does not recognise fails the whole request with
`400 INVALID_REQUEST` — a wrong guess here would break every generation on the site rather
than degrade quietly. So the plugin reads the service's own signal: the explicit
`capabilities.contentStandard.<route>` flag where present, a positive
`limits.contentStandardCharacters` as the older generate-only signal, and otherwise nothing
at all. A fresh install that has never spoken to the service sends nothing, which is correct.
The standard is trimmed to the advertised limit rather than assumed to fit.

The same field is wired for `/populate` — which until now carried no writing guidance
whatsoever, though the wizard fields it fills are the first thing a teacher sees of the
product — and stays switched off there until the service advertises that route, because a
shared character limit is not evidence that the route takes the field.


## [v1.42.0] - 2026-09-13

The self-audit continued for three more rounds. Everything below was found by checking my
own work rather than by anyone reporting it, and all of it is on the two teacher-facing
screens that no automated sweep had ever covered, because every sweep I had written pointed
at the player.

### Fixed — the teacher report had no gutter at all

The shell that every screen in the product sits in carries the border and the canvas; the
inset that keeps content off that rounded edge lives on `.aibs-shell` inside it. The report
opened the first and never the second. Its masthead, its figures and its table sat hard
against the border - the only screen in the plugin with no padding whatsoever, and it has
been shipping that way.

### Fixed — the single-attempt view rendered outside the shell entirely

Opening a learner's attempt from the report drew buttons and decision cards with no shell
around them. Every design token in this plugin is declared on the shell, so those cards were
asking for custom properties that did not exist on that page, and the browser discards the
whole declaration when that happens: no surface colour, no border colour, no radius. The
attempt list had been given the shell; the attempt underneath it had not.

### Fixed — the report and the draft review never went dark

`scheme.js` measures the page behind the activity and matches it, which is what stops the
plugin being a white slab on a dark Moodle theme. The player and the wizard each call it
from their own module. The report and the draft review load no JavaScript at all, so they
were the two screens left white - and they are the two a teacher opens most. The module now
has an `init` that watches every shell on a page, and both call it.

### Fixed — the report built its accent class by hand, and its fallback was a retired one

Three renderers resolve a stored theme through `schema::theme_class()`. The report did not:
it concatenated the class itself and fell back to `indigo`, which was retired from the
palette and has no block left in the stylesheet. It drew in the base blue by luck, because
the default tokens happen to be slate's.

### Changed — the slide rules are now checked at phone widths

The four layout rules - nothing scrolls, nothing is clipped, text uses its column, nothing
runs outside its card - were measured at 1440, 1280 and 980 only. They are now measured at
900, 600 and 380 as well, which is where a slide is most likely to break all four. All four
hold at every width, with a picture and without.

### Changed — three harness checks that could not fail

One asserted that a hard-coded array contained a hard-coded string. Two others were written
against the exact spelling of code that turned out to be wrong, so they held the plugin to
the fault. All three now check the behaviour they were meant to guard, and a new one covers
the picker words sent to the service, so an option added without its string is caught rather
than silently sent as a schema key.

## [v1.41.0] - 2026-09-13

### Fixed — the cast was never read off the wire

LMS Labs flagged that their new fields would not reach Moodle because the mapper omits them.
Checking that turned up something older and worse: **`facilitator` and `characters` were
never mapped at all.** Not since the first release.

Everything that needs to know who is in the scenario had been silently running on its
fallback ever since:

- **Every spoken line was read by the narrator.** A node's `speaker` is matched against the
  cast to find the voice it should be read in. With no cast, nothing ever matched.
- **Every scene image was drawn to the no-cast fallback** — *"one worker, seen from behind or
  in three-quarter view, face not the subject of the image"* — because the illustrator brief
  names the people in frame from that list.
- **The avatar a learner clicks to hear someone speak had nobody to show.**

None of it ever looked like a fault. It looked like the pictures being impersonal and the
narration being flat.

Also never mapped, and now carried: a node's **`imageprompt`** (so the illustrator was
briefed without the one line written for it) and **`imagealt`** (so every picture fell back
to alt text condensed from the situation prose), plus **`bottleneck`**.

**This does not repair existing scenarios.** A definition stored before this release has no
cast, because none was ever read. Regenerating or re-importing is what fills it in.

### Verified — the grade spread agreed with LMS Labs

Their new server-assigned scores are 87.5 all-strong, 50 neutral, 12.5 all-weak. Checked
against the bands the plugin actually draws: strong / mixed / high-risk endings, and green /
amber / red colours, all three agree. The spread it replaces did not — an all-weak path
scored 37.5, which is the high-risk *ending* but the amber *colour*, so the red band was
unreachable. Six checks pin it.

## [v1.40.0] - 2026-09-13

Everything on the outstanding list, less the three that need somebody else.

### Fixed — nothing anywhere asked for a character's gender

It chooses the voice a character's spoken line is read in, and it is what keeps their face
the same from one scene image to the next. The wizard had no field for it, the suggest brief
never mentioned it, and the generate request never sent it — so on **every scenario ever
generated** the narrator read the line and the illustrator was given nothing to hold to.

The wizard now asks for it, the suggest brief asks for it as a fifth bar-separated part, and
it is stored and validated to the two values that resolve to a voice.

### Fixed — the suggest route never asked for a principle's example or pitfall

The brief asked for "one short line each", so on the path a teacher uses to draft principles
the two fields the validator requires were never requested. It now asks for all three parts
with a worked example.

### Fixed — the teacher's cast was being dropped on its way to the service

The wire carries a name and a role. Trait, appearance and gender — all typed into the wizard
— were discarded. They travel in the free-text field the route already accepts, rather than
in keys it has never agreed to, so this ships without a contract negotiation.

### Fixed — narration labels were spoken in the site language

A French scenario had "Sounds like this", "Not this" and "Why this mattered" read aloud in
English in the middle of French narration. They are fetched in the scenario's language now,
falling back to English only where the site has no pack for it.

### Fixed — the image brief promised a light it never named

"the same time of day, the same source of light" — without ever saying what either was, so
each frame invented its own and a set came back in six different lightings. One of five is
now named, derived from the scenario's own title and setting so it is stable across
regenerations, along with an explicit landscape framing.

### Changed — the palette is closed in code, not just in intent

Indigo, ocean and violet were selectable accents in a product whose palette is one blue plus
green and amber. They are retired: no longer offered, no longer in the stylesheet, and a
stored value still **validates** — an activity saved with one keeps working and draws in the
default rather than failing. Three accents remain.

### Changed — the plugin states its own typeface

There was no `font-family` anywhere, so the plugin took the site theme's and the same
scenario looked like a different product on every site. A system stack now: no external
request, nothing to block on, nothing for a privacy review. The hostile-theme sweep is at
**0 of an original 23**.

## [v1.39.0] - 2026-09-13

### The two routes are held to the same standard

v1.38.0 closed the gap by writing a short standard for the generate route. That fixed the
symptom and left the cause: the rules existed twice, in two files, in two wordings, and
nothing stopped them drifting apart again — which is exactly how the paid route came to be
held to a lower standard than the free one for four releases.

There is **one list** now. `content_standard` holds twelve craft rules in priority order,
each with a long form and a short one. The pasted prompt renders the long forms — the text
it has always carried, moved rather than rewritten. The generate request renders the short
forms. A rule cannot exist on one route and not the other, because there is only one place
to add one.

All twelve fit. A realistic request carries the complete standard **and** the teacher's full
brief in 1,855 of the route's 2,000 characters. Where a teacher writes more than that, whole
rules drop from the bottom of the priority list — never a sentence cut in half — and the top
of that list is the rules whose absence was watched to produce bad scenarios: option position,
example and pitfall as their own fields, spelling variety, and feedback that explains the
mechanism. The teacher is never squeezed below a 500-character floor, because a perfectly
written scenario about the wrong subject is worth nothing.

Eleven harness checks cover it, including that the pasted prompt states every rule in full
and that a squeezed standard still ends in a full stop.

### Fixed — the pressures were sent as schema keys

"What makes this hard" and "What is at stake" are pickers, so what is stored is a key. The
request was sending the key: `timepressure; conflictingpriorities`. That is not English, it
is not what the teacher chose from, and it reads to a model as a tag rather than as a
description of the situation. It now sends the words the teacher saw — *time pressure;
conflicting priorities*. An unknown key is still sent as itself rather than dropped.

### Fixed — prose on a card with no picture uses the card

The 62-character measure is right where text sits beside a picture; on a card with no
picture it left a block of words half the width of the card with empty card either side.
The layout audit is now at zero findings.

## [v1.38.0] - 2026-09-13

### Fixed — the paid route was held to a lower standard than the free one

The prompt a teacher pastes into ChatGPT carries about **14,500 characters** of craft
instruction: second person and present tense, no option obviously correct, feedback that
explains the mechanism rather than restating the choice, no verdict language, the challenge
ending in a question mark, the spelling variety held throughout, and a fully worked decision
node showing the register.

The generate route carried **none of it**. It sent the teacher's typed words, a handful of
context fields, and — since 1.32.0 — 373 characters about principles. Every question of craft
was left to the service's own prompt, which this plugin does not control and cannot see.

That gap is visible in what came back, and it explains three faults separately reported:

- **The best option arrived first at every decision**, because nothing said not to.
- **Examples and pitfalls arrived folded into the summary as prose**, because nothing said
  they were their own fields.
- **Australian spelling did not survive**, because the rule asking for it only ever went
  down the other path.

`CONTENT_STANDARD` now goes with every generate request. The route caps the field at 2000
characters and the teacher's own words share it, so this is not the whole standard — it is
the rules whose absence has been *watched to fail*, in 884 characters, leaving 1114 for the
teacher. The rest belongs in the service's prompt, where there is room for it. A harness
check lists those rules by name and fails if any is dropped.

### Fixed — the consequence with no picture, and what a site theme can reach into

**The consequence card.** The readings take the column the picture would have had and the
words sit beside them, so the screen keeps the same silhouette as every other one. The first
attempt was wrong and looked it: a grid item spanning several rows stretches those rows to
its own height, so the four things beside the readings were spread from the top of the card
to the bottom with a hole in the middle. The words are one block now, and a block cannot be
stretched apart. On the card that *does* have a picture the wrapper is `display: contents`
and `order` puts every piece back where it was, so that card is unchanged.

**Theme overrides.** Tested against a stylesheet built from the rules Boost and its children
actually ship — button hovers, heading fonts, list padding, link colours, image borders, a
base font size — 23 properties moved. The colours, radii, fills and hovers all held, because
they are stated. Three things did not, because they were never stated and the browser default
was being relied on instead:

- **List item margins.** Every reading, skill bar, lesson card and decision record is a list
  item, spaced by its list's `gap`. A theme's `li { margin-bottom }` added a margin on top of
  that gap and the whole rhythm of the debrief moved.
- **A border on the scene photograph**, from a theme's `img { border }`.
- **The typeface** — see below.

Two of the three are fixed and checked; 23 exposures are now 2, and both remaining are the
same one.

### Known — the plugin sets no typeface of its own

There is no `font-family` anywhere in the stylesheet, so the plugin takes the site theme's
font. That is normal for a Moodle plugin and wrong for a product whose look is meant to be
consistent, and it is a decision rather than a bug, so it is recorded rather than changed.

Worth knowing alongside it: the review page had been imposing IBM Plex Sans on the plugin
frame, so every screen reviewed there was shown in a typeface that will not be on a real
site. The frame now takes the browser default, which is the honest stand-in for "whatever
the theme says". What is reviewed is what ships.

### Changed — a screen with no picture centres its column and left-aligns its words

Two different things that had been conflated. Centring the block is right: left-aligning the
whole card leaves the words hard against one edge with the rest of the card empty beside
them. Centring every *line* inside the block was wrong — a centred paragraph gives the eye no
left edge to come back to, and a list of records centred line by line reads as a poster
rather than a record. The one exception is a card carrying a single heading and sentence, the
way in and the withheld debrief: a statement centres.

## [v1.37.0] - 2026-09-13

### Fixed — a scenario imported from the pasted prompt had no pictures and no voice

The two routes into a scenario behaved differently and only one of them was safe.
Generation runs its media inside the same task, so the pictures and the narration are
finished before the teacher ever sees a result. Import cannot: it returns the moment the
definition validates and leaves the media to a task waiting on cron. A teacher who pastes a
scenario in and publishes it — the obvious thing to do, since the scenario is right there
and looks finished — publishes first.

`publish_media()` was the only thing that ever copied working media into a revision, and it
runs at publish time. So the task finished minutes later, wrote its work into an area no
learner reads, and **the media was lost permanently** — not delayed. Whichever of the two
finishes last now does the copying, guarded so media only joins a revision holding the same
scenes it was made for.

### Fixed — the debrief pages, against a written checklist

The same four faults kept coming back one screenshot at a time, so they are now measured on
every screen at three widths in both picture states — see the slide quality checklist.

- **`.aibs-lesson-list` was missing from the full-width exemption**, so "Lessons learnt",
  "How to apply this" and "Key takeaways" were capped and centred in a card twice their
  width. This was the "text is not full width" fault.
- **The debrief read from the middle out.** With no picture its pages took the centred
  statement-card treatment, which suits a heading and a sentence and not a list of records:
  every line of the decision list was centred, including the note inside the amber panel and
  a "Principle tested" chip that ran off the end of the card. Records line up now.
- **"Critical decisions" has its own page.** Two headings and two numbered lists on one card
  overran the frame and grew a scrollbar.
- **"Source material" is gone.** It was a second subject on a page that already had five
  numbered items, and it told the learner about the scenario's provenance rather than about
  their own performance.
- **No debrief page scrolls.** The fit floor moved from .74 to .68 so a page steps down to
  fit rather than overflowing, and the debrief body clips nothing.

### Fixed — the round controls took the theme's hover fill

The icon buttons pinned the mark's colour and left the fill to the cascade — the same
half-a-pair fault as the wizard steps, the other way round. A theme painting dark grey on
every hovered button turned the fullscreen and narration controls into a dark disc under a
dark mark. The sweep that missed it has been widened: it only looked at rules that repainted
a background and forgot the text, and a control that pins *neither* half is equally exposed.

### Fixed — a comment split a selector list in half

Inserting a rule in the middle of a multi-line selector list silently cut it in two, and the
readings, skill bars, decision list and takeaways lost their full width. The harness check
for comment-interrupted selector lists caught it before it shipped — the same class of fault
it was written for after the last one.

## [v1.36.1] - 2026-09-13

### Fixed — a wizard step vanished when you pointed at it

The step tabs pinned their hover *background* and left the text colour to the site theme.
The theme paints white text on every hovered button, so the label went white on the step's
own white surface.

The rule worth keeping: **a rule that repaints one half of a colour pair and leaves the other
to the site theme works until it meets a theme.** Both halves are stated now, on the steps
and on the speaker avatar, and the sweep is a harness check rather than a memory — every
hover or focus rule that sets a background must state a colour beside it, and the check names
the offending selector when one does not. Measured in a browser against a stand-in theme
forcing white on hover: white-on-white before the fix, readable after.

Focus now matches hover, so a keyboard user meets the same states as a mouse user.

## [v1.36.0] - 2026-09-12

### The flow review

The screens were each careful on their own and the sequence did not hold together. Three
faults compounded into that, and they are separable.

**Two decision counters, disagreeing on screen at the same time.** The chip on the card
printed the node's own narrative stage. The bar counted *events* — and an event is logged
for a beat as well as for a decision, so four clicks through two beats had the bar reading
"Decision 5 of 5" beside a card saying "Decision 3". Both said "Decision" and neither was
wrong by its own definition, which is the worst kind of wrong. There is one number now and
it is the node's, because that is the one the learner can see.

**A screen in the middle of the scenario that said nothing.** A beat carries a synthesised
choice with no consequence prose, no feedback and no effects, and it went through the same
path as a real decision — so pressing a beat's Continue drew a consequence screen showing
the word "Neutral", three readings that had not moved, and a second Continue. Two clicks to
be told nothing. Where there is genuinely nothing to report the screen is skipped and the
learner goes straight on to what happens next. The test is the story, the teacher's note and
the three readings; if any of them has something, the screen is drawn as before.

**Joins with no transition.** Every screen animated *in* and none animated *out*: the
decision vanished in the same frame the consequence appeared. A screen settles back the way
it came up, over 160ms — a fade rather than a slide, because the picture on the left does not
change between a decision and its consequence and sliding it out would say that it had. A
leaving screen cannot be clicked, and anybody who has asked for less motion does not wait for
it.

Nine harness checks cover the three; four of them fail on the previous code.

## [v1.35.0] - 2026-09-12

### Fixed — the example and the pitfall were in the summary all along

The service does not always fill the `example` and `pitfall` fields. It often writes one
paragraph instead — *"Seek opportunities where both parties can benefit. Example: "If we
extend the contract duration, could we discuss a price adjustment?" Pitfall: Viewing
negotiation as a zero-sum game."* — and leaves both fields empty.

Everything downstream then behaved as though the principle taught a rule and nothing else.
The two cards on the teaching slide are drawn only when their field has something in it, so
they never appeared; the slide showed a heading and a wall of prose with the good bits buried
in the middle of it; the narration read it as one undifferentiated block; and for three
releases the definition was refused outright for fields whose content was sitting right there
in the summary.

The words are now put where they belong. A field the service did fill is never overwritten,
a summary with no label in it is returned exactly as it came, and a label mid-sentence — "for
example the deadline" — does not cut the summary in half. The cards, their icons, their
banding and their hover were already built and simply had no data; they fill the slide now.

### Fixed — the metrics legend opened the course index

The "?" beside the readings was a `<details>`, and on a live theme clicking it opened the
course index drawer instead of the panel. A native disclosure carries behaviour of its own
and themes bind to its summary. It is a button now, holding its state in `aria-expanded`,
closing on Escape or a click elsewhere — and every click inside the player now stops at the
player, so no handler on the page can act on one again.

### Fixed — option A was the right answer every time

The generator writes the best option first, consistently, so A was the strongest answer at
every decision. A learner notices that within two screens and stops reading the options, and
the scenario then measures whether they spotted the pattern rather than whether they know the
material. The order is now settled once, at generation, seeded from the node's own id — so it
is stable across regenerations and the teacher edits the same order the learner sees.

### Fixed — fullscreen gave the same words with more white around them

Two faults, one symptom. The fit scale only ever stepped *down*, so in fullscreen the frame
grew to the whole screen and the type did not follow it. And the type scale was declared in
`rem`, so even where the fit did apply, only the prose moved — every chip, label and
sub-heading stood still. The scale now grows into a screen that has room to spare (capped at
1.5×, and only where nothing had to be stepped down first), and the scale has a base the fit
multiplies, so a screen scales whole. Measured in a browser: chip and body text both move by
exactly 1.3× at fit 1.3.

### Fixed — a long title took the controls onto a second row

The bar is a title on the left and the controls on the right. A long scenario title wrapped
and took the whole bar with it. The title is held to one row now and steps its type down to
stay there, as far as 72% before it is allowed to wrap — smaller than that and the heading is
smaller than the text beneath it, which reads as a mistake.

## [v1.34.0] - 2026-09-12

### Fixed — a thin principle no longer throws away a paid generation

v1.32.0 added the missing example and pitfall to the generate request, which was the right
fix for the wrong half of the problem. The other half is that refusing the whole definition
was never the right lever. By the time the definition is read the teacher has been charged;
rejecting a scenario that is otherwise sound — the nodes, the branching, the scoring, the
writing — over two sentences a human can type in seconds costs them their credits and gives
them nothing.

So the check moves from the validator to the review step. A principle with no example or no
pitfall is now raised where the teacher is already reading the scenario, naming the principle
and saying what to add, and they fill it in before publishing. The player has always drawn
the slide without them, so nothing breaks in the meantime.

The reasoning behind v1.20.0 stands and is unchanged: a slide that states a rule and stops
teaches nothing a learner can use. It is enforced at the point where a person can act on it
rather than at the point where the only available action is to pay again.

Five harness checks replace the two that only asserted the error strings existed: a thin
principle validates, both gaps are raised at review, the review names the principle, and a
complete principle is not mentioned at all.

## [v1.33.0] - 2026-09-12

Version bump only. 1.32.0 had already been promoted under exactly these bytes, so the
pipeline had nothing to write; this is the version number it publishes the same work under.
No code, schema, string or stylesheet change.

## [v1.32.0] - 2026-09-12

### Fixed — generation was being refused for a field we never asked for

Since v1.20.0 a principle has had to carry an **example** and a **pitfall**, and a definition
that omits either is refused with the principle named. The reasoning stands: a teaching slide
that states a rule and nothing else gives a learner nothing they can use. But the generate
request never asked the service for either field. The paste-and-import path asks for them at
length, which is why that path produced them and generation did not — so a teacher who used
the wizard was charged, waited, and was told:

> The principle "Preparation and Planning" has no example. … has no pitfall.

This is the same shape of fault as the v1.6.4 one: a contract that differed across the
boundary, on our side of it. The requirement is now part of every generate request, and it is
the one part of the brief that survives the route's 2000-character ceiling — the teacher's own
words are trimmed to what is left rather than the requirement being cut off the end of them.
Four harness checks cover it, and all four fail on the previous code.

### Fixed — the controls at the end of the top bar

Making the decision-history control a round mark like the two beside it turned all three into
blocks, and they stacked. The bar had never actually said they were a row; while the first of
them was text, the row happened by accident. It is a row on purpose now. An older hover rule
that repainted the history control was also still in the stylesheet, against the rule that
hover lifts and never repaints; it is gone, and the harness checks for both.

### Changed — the decision history is a mark, not a heading

The rest of the top bar is icon-only, and this one control still spelled itself out in
capitals. It carries a list mark now, with its name as hover text and on the control for a
screen reader.

### Fixed — padding under a feedback panel

The amber panel's text sat hard against its bottom edge. Its padding is set in `em` now, so
it scales with the slide's type rather than staying at a fixed pixel value while the text
around it grows.

## [v1.31.0] - 2026-09-12

### Changed — the settings page explains itself

Twelve settings had no description at all: the page showed a name, the component key, and
nothing else, so an administrator met "Default \"good from\" threshold" with no way to find
out what it set. Every setting now carries Moodle's help icon, with the explanation behind
it rather than as an inline paragraph - a page of twenty-six inline descriptions is a wall
of text nobody reads, and core appends a help icon to a setting name in exactly this way on
the media players page. Thirty of the thirty-four entries have one; the four without are the
three section headings, which keep a lead paragraph, and the connection status, which
renders a live report.

Several names were ours rather than plain English. "Default \"good from\" threshold" is
**Green band starts at**, "Default \"a problem below\" threshold" is **Red band below**, and
"Hear each screen out by default" is **Require narration to finish before continuing**.
"Defaults" is **Defaults for new activities**, and "Limits" is **Limits and permissions**,
each now saying whether a teacher can override what is set there.

### Fixed — the upgrade step order

The 1.30.0 upgrade step was written above the step before it rather than below. `$oldversion`
is read once and does not change as the steps run, so both matched: the later step moved the
stored version forward and the earlier one then tried to set it back, which Moodle refuses
with "Cannot downgrade". The install died half way through. Steps are ordered, and the
harness now checks that they ascend, that each closes with a savepoint for its own version,
and that the last one matches `version.php`.

## [v1.30.0] - 2026-09-12

### Changed — every figure counts to its value

One count-up function now drives the three readings in the bar, the three rings on a
consequence and the four skills in the debrief, so they all move with the same curve over
the same time and arrive eighty milliseconds apart. Previously the rings counted and
everything else snapped, which put two different ideas of how a number arrives on the same
screen. The skill bars are staggered rather than all filling on one frame.

### Changed — hover lifts, it does not repaint

The dark inverse fill is now worn only by a control that is actually switched on. Every
hover is the lift and the border: the primary action keeps its own accent rather than
darkening, an option that is already chosen keeps its accent, and a choice card no longer
takes a tinted wash that made it look selected while the pointer was over it.

### Changed — thin rings

The consequence dials were drawn at a tenth of their own diameter. They and the readings
in the bar are now thin strokes.

### Fixed — pictures ran the full width of the card

The rule that puts a picture beside its text was the only rule in the stylesheet scoped to
the body class alone; anywhere that class was absent, every deck slide fell back to
stacking and the picture spanned the card. The "way in" slide had the same result from a
different cause — a centred flex column written for a slide with no picture, applied to
one that had it.

### Fixed — a slide with no picture reserved the column for one

Its text was centred inside the left 45% of the card, which reads as left-aligned. A slide
without a scene is now one column.

### Fixed — the readings were grey until the first decision

The band was applied only by JavaScript, so the three marks painted in muted grey and
stayed there until something moved them. They are banded server-side too, against the same
thresholds the teacher set.

### Changed — the three readings got marks that mean something

Engagement was a heart-monitor trace, which said "vital signs" rather than "the room is
with you"; it is now three figures. Tension was a lightning bolt, which reads as a hazard
sign when a high tension is a reading rather than an alarm; it is now a thermometer. The
shield is unchanged. All three are drawn once in the shared partial, so the bar and the
consequence screen cannot disagree.

### Changed — one shape for every progress indicator

The mark sat outside the ring with the figure inside it, so a reading was two objects with
nothing tying them together and the row of three read as six. Everywhere a reading is
drawn — the bar and the consequence screen both — the mark is now centred in its own ring,
so the ring is that reading's dial and the arc around the mark is where the reading stands,
and the figure sits beside it with room to be read at a size worth counting up to. On the
consequence the name moved to its own line beneath. In the bar, the gap inside a reading is
smaller than the gap between two, so the row reads as three pairs.

The third indicator, the debrief's skill bars, was drawn at a 10px track with a gradient
fill while the dials had gone to thin flat strokes — two visual languages on adjacent
screens. The track is 6px and the fill is the flat band colour.

### Changed — the debrief lists are cards, one to a row

Lessons learnt, Critical decisions, How to apply this and Key takeaways were bullet points
and bare text. Each item is a card now, numbered from one, on the tinted surface with the
same thin outline, the same lift on hover and the same body colour as every other card in
the debrief. One to a row at every width, so there is no breakpoint to get wrong and the
phone layout is the desktop layout.

The four skills are cards too, each outlined and tinted by its own band, so a strong skill
is a green card and a middling one amber. The bar track goes white inside a tinted card so
it keeps its groove.

### Changed — standard names

Six labels were ours rather than the industry's: "What mattered most" is Lessons learnt,
"Decisions that changed the outcome" is Critical decisions, "Apply it in practice" is How
to apply this, "Back to the source material" is Source material, "Worth taking with you"
is Key takeaways, and "That is the debrief" is Debrief complete. "Middling" is Moderate
and "Understandable" is Neutral. The button that starts a scenario says Start; the heading
above it already said Begin the scenario, so the screen was saying it twice.

### Changed — one shape, one meaning, for every icon

The tick in a circle marked both a well-judged decision and the "Say this" example, and
the warning triangle marked both a costly decision and a pitfall. Two shapes were each
carrying two meanings. "Say this" is a speech bubble and a pitfall is a no-entry mark.

### Fixed — hover applied a grid to the card it was over

A dangling selector left a rule reading `.aibs-lesson:hover, .aibs-takeaways`, so hovering
a card applied the takeaways grid to it: the text moved and the card resized, and the
intended hover never existed. Three more comment-interrupted selector lists were found by
the check written for it, one of which had merged the finish screen's block into the
no-picture rule and was turning every child of every centred slide into a flex column.

### Fixed — touch targets and a sideways scroll

Every round control was a 34px disc, comfortable with a mouse and too small for a finger.
Where the pointer is coarse they are 44px, and the teacher's wizard steps have a 44px
minimum height; the marks inside are unchanged, so only the hit area grows. A fixed 42px
speaker badge could not shrink on a 320px screen.

### Changed — the debrief

"Why this mattered" was marked with a light bulb, which is the mark every product uses for
an optional tip. It is not an aside — it is the point the decision was testing — so it is
now an exclamation in a circle, drawn once and shared by the consequence screen, the
debrief and the teacher's draft review. The panel it sits in is the amber sibling of the
green outcome badge rather than another flat grey box.

The grade is coloured by its band. It was accent blue on every run, so a strong outcome
announced itself with a green badge and then reported its figure in the brand colour — the
only banded number in the player that was not banded.

Amber was `#b45309`, about twenty degrees from the negative red, so at 6px a middling skill
bar and a bad one were the same colour. It is a real amber now.

Each debrief page centred its own contents, so the heading sat at a different height on
every one of the six and the column jumped on each arrow press. They share one baseline.

The decision principle was bare accent-blue running text with a bold lead-in — the only
blue prose in the product, and it read as a link that had failed to render. It is a chip.
The skill rows had the same gap inside a row as between rows, so each description sat as
close to the next skill as to its own bar and four rows read as eight lines.

### Fixed — the skill bars animated behind a hidden slide

A deck holds every page in the DOM and hides all but one, so the bars and their
percentages ran their animation when the deck was built and were finished long before the
learner arrowed to "How you handled it" — the one screen whose job is to deliver a result
just sat there. Each slide now runs its arrivals at the moment it is shown, once. The
grade counts up with them; it was the only figure on the debrief that simply appeared.

### Changed — the finish, where the debrief is switched off

A heading, a sentence about the missing breakdown, and a quiet button. The tick now draws
itself on, confetti falls once in the player's own palette, and the way on is the blue
primary action. Anyone who has asked for reduced motion gets the same screen, still.

### Fixed — panels left over from removing the left-hand rules

The "Why this mattered" panel and the teacher's speech panel were left rules with the text
tucked in beside them. When the rules came off and the panels went to a thin outline on all
four edges, the padding stayed on the one side it had always been on, so the text sat hard
against the top, right and bottom of its own border. The takeaway cards were worse: they
carried only a hover transition, so at rest they were unpadded text on the page background
that grew a shadow when the pointer crossed them.

### Fixed — the consequence marks were mangled

The rule that turns a ring so its arc starts at twelve o'clock was a descendant selector,
so it also caught the mark once the mark moved inside the dial: all three icons were sized
to 72px and rotated onto their sides. It now applies to the ring element alone.

### Changed — the benefit cards are three across

Six cards packed four to a row left the last two stretched across the width of four.

## [v1.26.0] - 2026-09-12

### Changed — the readings carry their mark on both screens

- Engagement, trust and tension got a drawn mark in the top bar in v1.23.0 and kept plain
  words under the rings on the consequence screen, which is the one place a learner actually
  studies them. Each ring now shows the same mark beside its name, and both take the colour
  of the band the reading falls in — so the three readings on a consequence are coloured by
  the same thresholds, in the same way, as the three in the bar above them.
- **The three marks are defined once**, in a shared partial, rather than written out in both
  templates. Engagement cannot end up as one shape in the bar and a different one underneath
  a ring.

## [v1.25.0] - 2026-09-12

### Added — green, amber and red, with the thresholds in the teacher's hands

- **Every figure the learner is shown is now banded.** The engagement, trust and tension
  readings in the bar; the three rings on a consequence; and the four skill bars in the
  debrief — which were the only numbers in the product with no colour on them at all, on the
  one screen where the learner is actually being judged.
- **Two thresholds, set on the activity**: the value a reading is good from, and the value
  below which it is a problem. Everything between is amber. They were two numbers written
  into the JavaScript, which meant a de-escalation exercise and a sales conversation were
  told the same thing about a trust of 55. Site-wide defaults sit behind them, and the form
  refuses a pair that cannot be true.
- **One function does the banding for all three**, so a scenario cannot call a value good in
  one place and middling in another. Tension is turned the right way up first, because a low
  tension is a good one — a tension of 20 is green.
- Each figure also says its band in words for anyone who cannot tell the colours apart.

### Changed — where a reading stands beats which way it moved

- The bar used to colour a reading by its last movement and by its standing, both written
  onto the same ring, so a trust of 12 that had risen by two showed green — "improving"
  beating "nearly gone". Movement is said on the consequence screen, by an arrow, at the
  moment it happens. The bar says where things are.

## [v1.24.0] - 2026-09-12

### Changed — a change is an arrow, not the word "up"

- The three readings on the consequence screen said "up 12", "down 12", "reduced by 8". They
  now draw an arrow and the number. The arrow says the direction the reading moved; the
  colour beside it says whether that direction was the good one — which is a different
  question on tension, where falling is the gain. The worded form is what a screen reader
  gets, because an arrow and a digit say nothing to somebody listening.
- **A gain and a cost are finally coloured differently at all.** The classes that were
  supposed to do it were being written onto the element and had no styles behind them, so
  every change on every screen was the same grey whatever it meant.

### Fixed — no picture means no column

- Making every screen picture-left in v1.23.0 left one case wrong: a slide that has no
  picture is not a column at all, and left aligning it puts the words hard against one edge
  with the rest of the card empty beside them. A slide now says whether it has a scene, and
  the ones that do not centre — the words, the measure and the actions. The class is written
  by the template rather than worked out with `:has()`, so it is decided by the data rather
  than by whether the browser supports the selector.

### Fixed — the wizard lost its breathing room

- Removing the shell's gutter for the player's sake took the wizard's padding with it, and
  its first and last elements sat against the card edge. The wizard is a document, not a
  fixed frame — it has no stage to carry the inset, so it carries its own.

## [v1.23.0] - 2026-09-12

### Changed — one shape for every screen

- There used to be two: a slide with a picture beside its text, and a card of text across the
  full width. Centring the second made it tidy in isolation and made the product read as two
  products — the silhouette changed underneath the learner as they moved from a decision to
  its consequence to the debrief, and the picture that had been on the left simply vanished.
  **Every screen is now the picture on the left and the reading beside it.** Nothing runs the
  full width.
- **The consequence shows the scene the decision was taken in.** The room has not changed
  because the learner chose something in it.
- **The debrief borrows the scene the scenario opened on**, rather than being the one set of
  screens with a different silhouette.
- Text stays left aligned, because that is how a paragraph is read; the actions are centred
  in their own column — the middle of the thing they belong to, rather than the middle of a
  screen they occupy half of.

### Changed — the top bar, again

- **The narration control is a mark, not a pill.** The word said nothing the icon does not:
  the control already draws a different mark for on, off, blocked and nothing-to-play. It is
  now the same round shape as the fullscreen control beside it, and the word stays for a
  screen reader, which cannot see which mark is drawn.
- **The three readings are marks too.** "Engagement", "Trust" and "Tension" took more of the
  bar than the readings themselves did. Each is now a drawn mark with its name on hover, in
  the legend in full, and spoken to a screen reader.
- The mark inside a filled control reverses out at every level — the chip behind the narration
  icon used to carry its own accent colour, so the disc went dark and the mark stayed blue.

### Changed — no card is coloured on one edge

- A coloured rule down one side of a box that has a border on the other three reads as damage
  rather than as emphasis. **A card that carries a signal is now outlined in it, thinly, on
  every edge**, in the soft token rather than the strong one — a tint on an edge, not a
  warning. The same goes for the quoted blocks, which used the same device.

## [v1.22.0] - 2026-09-12

Every open item from the screen audit, and one install bug found on the way.

### Fixed — the upgrade could die on a real site

- `$dbman` was fetched inside one of the version blocks, so any site whose stored version was
  already past that block never ran the line — and the next block that needed it called
  `field_exists()` on null and the install stopped mid-upgrade. A helper every step may need
  belongs to the function, not to the one step that happened to need it first.

### Fixed — a control that looked live and did nothing

- The speaker's play button was rendered whenever a node had a recording, without asking
  whether narration was on for the activity at all. `playSpeech()` then refused to act on it.
  So on any scenario whose teacher had turned narration off while the clips still existed,
  every speaking line showed an armed play button that did nothing when pressed — no state
  change, no message. It is now gated on narration being on.

### Fixed — two settings that did not do what they said

- **"Show metrics" hid the readings in the bar and left them on the consequence screen**,
  which is where the numbers matter most and where, after the bar was hidden, the legend
  explaining them was no longer reachable. It now means what it says on every screen.
- **"Show progress" gated nothing at all.** It used to gate the row of numbered dots; the
  dots went in v1.21.0 and it was left gating nothing, so turning it off had no effect. It
  gates the position indicator now.

### Fixed — the grade arrived unexplained

- The four graded skills had no description anywhere in the plugin, while the three readings
  that are explicitly *not* the grade had a full legend. A learner watched engagement, trust
  and tension all the way through, was told those were not the score, and then met four new
  percentages that reconciled with nothing. Each skill now says what it measures, and the
  page says plainly which numbers are the grade and which describe the room.
- **The score had no visible label.** The only thing saying that "74.5%" was a decision
  quality score was an `aria-label` — which everyone who can see the screen cannot read.

### Fixed — things that did not say what they were

- The principle chip was a bare phrase that could equally have been a tag, a quote or a
  category. It now says what it is.
- **Each reading says its standing in words, not only in colour.** The ring was coloured by
  where a reading stands while the text under it only ever described the movement, so "trust
  is currently low" was communicated by colour alone.

### Added — looking back

- There was no way to see what you had already decided. Going back to *change* a decision is
  not the answer — undoing a consequence would make the score mean nothing — so this is a
  read-only record, opened from the top bar and populated from the server on resume, so it is
  the attempt's history rather than the browser session's.

### Removed — two screens that were not worth a screen

- **The debrief's closing page is gone.** Its content was a sentence telling the learner they
  had reached the end of something they could see they had reached the end of, plus two
  buttons. The buttons moved to the foot of the last page that has something on it.
- **The takeaways no longer have a page of their own** directly after a page of advice. Two
  pages of advice at the end of a debrief is one too many.

## [v1.21.0] - 2026-09-11

### Changed — the top bar, redesigned rather than patched

- **One heading, not two.** The scenario's name sat above a second line repeating the
  setting and the subtitle, on a page whose own banner has already named the activity.
- **One progress indicator, not two.** A row of numbered dots counted decisions while a
  line under the deck counted slides — two counts of different things on one screen, which
  is a question rather than information. There is now a single position chip at the top
  right, saying where the learner is in whatever they are looking at: "1 of 7" through the
  lesson, "Decision 3 of 5" through the scenario. The number they are on carries the weight
  and the total sits behind it.
- **The arrows moved onto the slide's own left and right edges**, vertically centred, where
  a hand reaching for "next" already is. They used to sit in a row underneath, which spent
  a band of height on two buttons and put the way forward as far from the slide as it could
  be. Nothing is reserved for them any more, so that height goes to the slide.
- **The card is inset by one value on all four sides.** Removing the old gutter in v1.20.0
  left ten pixels above the card and none anywhere else, so it sat hard against three edges.
  The inset is now a single declaration on the stage and even by construction.

### Fixed

- **Fullscreen did not scale.** The frame is capped so a tall window does not crop a 16:9
  photograph into a letterbox on its side — but the cap was still applying in fullscreen,
  leaving a 620px card in the middle of a 1080px screen, which is the opposite of what the
  button promises. Fullscreen is the one place the cap does not apply, and the picture grows
  with the frame instead of holding a fixed ratio inside it.
- **A switched-on control now reverses its mark out of its fill.** The fullscreen button
  goes dark when it is active and the icon kept its resting colour, so it sat accent-blue on
  a dark grey disc. Every held state inverts the same way the hover does.

### Changed — one rule for where things sit

- Two kinds of screen were not being told apart. A slide with a picture beside it is a
  reading column: its text is left aligned because that is how a paragraph is read. A slide
  with **no** picture is not a column at all — it is a statement on a card — and left
  aligning it left the words hard against one edge with a third of the card empty beside
  them, and the button that answers them floating off in a corner. Every screen without a
  picture is now centred: the block, the words, and the actions under it. Continue was
  bottom-left on the consequence screen for no reason other than that a div starts at the
  left; it is bottom-centre, like every other primary action.

### Fixed — the start screen was standing in front of five different situations

- "Ready when you are — you will make a series of decisions" was printed unconditionally,
  while the buttons underneath it branched five ways. So it appeared over "See how you did",
  over "Carry on where you left off", over "not published yet", over "preview only" and over
  "no attempts left" — describing none of them. The heading and its description now change
  with the state: **Begin the scenario** before a first run, **Pick up where you left off**
  on an open one.
- **Reviewing a finished run is no longer the blue button on the start screen.** This screen
  starts the scenario; reading a past result is a quiet second option, and it is not offered
  at all when the teacher has turned the debrief off — which previously led to a button
  promising a result and a screen saying there would not be one.

### Added — hear each screen out before moving on

- A new activity setting, off unless asked for. The way forward normally dims while a screen
  is being read but can still be pressed; with this on it is genuinely held closed until the
  reading finishes, so a learner cannot click past the teaching.
- Previous is deliberately left open: going back is not skipping anything, and a learner who
  wants to hear a screen again has to be able to reach it.
- It cannot strand anyone. A recording that fails, or never finishes, releases the way
  forward on its own, and anyone who has muted the narration is never held at all.

## [v1.20.1] - 2026-09-11

### Changed — the three readings are dials now

- Each reading was a name, a run of track and a number. The track is the expensive part:
  it has to be wide enough to read a proportion off, and three of them took most of the
  width of the bar, which is why the readings wrapped onto a second line and pushed the
  title around. A ring says the same proportion in the space of its own diameter and puts
  the number inside itself. **Measured: the readings went from about 560px of the bar to
  368px, and the whole top bar from 68px to 51px** - which, with the gutter and the
  single-row bar from v1.20.0, is the difference between a 520px slide and a 620px one at
  1920x945.
- **They sweep up from empty when the screen opens**, staggered eighty milliseconds apart,
  the same interval the choices and the outcome rings use. Under `prefers-reduced-motion`
  they are simply drawn at their value.
- The ring takes the colour of a move as well as the number, so a rise or a fall is visible
  without reading the digits.
- A ring is a picture, so each one also carries its reading in words for anyone listening to
  the page rather than looking at it. The old markup put `role="img"` with a label that had
  no value in it on the track, which announced "Trust, image" and left the number to a
  separate element beside it.

### Verified

- The fit sweep was re-run after the change: fifty-one window and banner combinations, none
  below the fold. The frame is still identical on every screen - 620/620, 570/570, 438/438.

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
