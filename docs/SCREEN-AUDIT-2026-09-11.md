# Screen-by-screen display audit — what is still clunky

Written after walking every template against `classes/output/player.php` and the lang
strings. Everything marked **fixed in v1.21.0** is done and verified; everything else is
open, and ranked by how badly it undermines the product.

---

## Fixed in v1.21.0

- **The start screen was standing in front of five different situations.** "Ready when you
  are — you will make a series of decisions" printed unconditionally while the buttons
  under it branched five ways, so it appeared over "See how you did", over "not published
  yet", over "preview only" and over "no attempts left". Heading and description now change
  with the state.
- **"See how you did" was the blue button before the learner had done anything.** It is now
  a quiet second option, and it is not offered at all when the debrief is switched off.
- **Alignment had no rule.** Screens with a picture read from the left; screens without one
  are statements on a card and are now centred, actions included. Continue was bottom-left
  because a div starts at the left.
- **Two progress indicators counting different things.** One position chip now.
- **Two headings.** The setting and subtitle line is gone.
- **The arrows were under the slide.** They are on its left and right edges.

---

## Open — ranked

### 1. A dead control that looks live

`templates/node.mustache:76-86`. The speaker avatar renders as a play button whenever the
node has a speech recording — `hasspeechaudio` is set from `Boolean(node.speechurl)` in
`amd/src/player.js` and does **not** check whether narration is enabled for the activity.
But `playSpeech()` returns immediately when `audioEnabled` is false. So on any scenario
where the teacher turned narration off while recordings still exist, the learner sees an
armed play button on every speaking line, clicks it, and nothing happens at all. No state
change, no message.

Worse than unclear — it is a silent no-op. And there are three states rendered with two
visual variants: playable, silently-disabled-but-looks-playable, and nothing-to-play.

**Fix:** fold `audioEnabled` into `hasspeechaudio` so the control's appearance matches
whether pressing it can do anything.

### 2. The "show metrics" setting is defeated on the screen where the numbers matter most

`showmetrics` gates only the top-bar dials and the legend. The consequence screen's three
rings are built unconditionally in `renderConsequence()`. So a teacher who turns metrics off
— to keep the framing on the grade rather than room mood — gets the exact same numbers back
after every single choice, now with no legend reachable anywhere, because the legend lived
in the bar that the setting just hid.

**Fix:** pass `showmetrics` through to the consequence render and honour it.

### 3. The grade is four numbers nobody explains

The three dynamics (engagement, trust, tension) have a full legend and are explicitly
disclaimed as *not* the grade. The debrief then shows four different skills — presence,
adaptability, empathy, clarity — as the actual grade, with **no description strings at all**
in the lang file, no legend, and nothing tying a skill bar to any decision the learner made.
The one sentence connecting the two systems is buried in a disclosure on a screen the
learner has already left.

A first-time learner will reasonably believe the rings they watched all game *are* their
score, then meet four unexplained percentages that reconcile with nothing.

**Fix:** write `skill:*desc` strings, and put a short explanation on the debrief itself.

### 4. The score on the debrief has no visible label

`templates/debrief.mustache:85-89`. "62.5%" sits next to the outcome badge, and the only
text saying it is *decision quality* is an `aria-label` — invisible to everyone who can see.

### 5. A dead teacher setting

`showtimeline` is in the form, the site defaults, the backup and the template docblock, and
`{{#showtimeline}}` appears nowhere in `player.mustache`. Turning it off does nothing. It
used to gate the rail; the rail is gone. Either gate the position chip on it or remove it.

### 6. Unlabelled chips

The principle chip on the consequence screen and in the debrief journey renders a bare
phrase — "Report faults before the next shift" — with no lead-in. A learner has to infer it
is the lesson their choice was scored against rather than a tag or a quote.

### 7. Ring colour says something the words never do

The consequence rings are coloured by *standing* (green above 67, red below 34) while the
text under them only describes *movement* ("up 12"). "Trust is currently low" is communicated
by colour alone, with no text equivalent — and the debrief's four skills get no colour at all,
so the two systems disagree about their own visual language.

### 8. Navigation

There is no way back to a previous decision. The deck arrows only exist on the lesson and
debrief decks; a decision screen has no back or forward. Whether that is a bug or the design
is a product decision — a branching scenario that lets you step back lets you undo a
consequence, which changes what the score means. **I have not changed this, because it is
your call, not mine.** If you want it, the honest version is "review previous decisions"
read-only, not "go back and choose again".

### 9. Redundant screens

You mentioned the link screens. My read of what is genuinely load-bearing:

| Screen | Verdict |
|---|---|
| Opening situation slide | Keep — it is the setup |
| One slide per principle | Keep, **but only if it teaches three things.** As of v1.20.0 an example and a pitfall are required, so a one-line principle slide can no longer be generated |
| Closing "begin" slide | Keep — it is the way in, and it now says so |
| Decision slides | Keep |
| Consequence screens | Keep — this is where the learning lands |
| Debrief outcome + score | Keep |
| Debrief skills page | Keep only once it explains itself (see 3) |
| Debrief journey pages | Keep |
| Debrief lessons page | **Candidate for removal** — it largely restates the opening principles the learner has already read |
| Debrief takeaways page | **Candidate for merging** into lessons; two pages of advice at the end is one too many |
| Debrief closing "That is the debrief" page | **Remove.** It tells the learner they have reached the end of something they can see they have reached the end of, and its only content is two buttons that could sit on the takeaways page |

That last one is the clearest win: it is a whole screen whose content is "this screen is
over".

---

## What I would do next, in order

1. The dead play button (1) — it is the only thing here that is actively broken.
2. Honour `showmetrics` on the consequence (2) and kill or wire `showtimeline` (5).
3. Explain the grade (3) and label the score (4) — the two that make the product feel
   arbitrary at the exact moment it is being judged.
4. Cut the debrief's closing page, merge takeaways into lessons (9).
5. Label the chips (6) and give ring standing a text equivalent (7).
6. Decide navigation (8) — your call.
