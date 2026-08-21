---
name: sync-translations
description: >
  Synchronize translation catalogs across maintained Symfony branches:
  find messages that newer branches added to the English catalogs but
  that are still missing from the oldest maintained branch, then add
  them (with a translation per locale) on that oldest branch so they
  cascade up via merges. Use when the user says "synchronize
  translations", "sync new translations", "sync the translation
  catalogs", "add the missing translation messages", or "propagate new
  xlf messages".
---

# Symfony Translation Catalog Synchronization

New messages get added to the English catalog only (e.g. `validators.en.xlf`),
usually on the newest branch where a feature lands. This skill discovers every
such message missing from the **oldest maintained branch** and adds it there, in
all locales, so it cascades up through the regular merge-up.

## Core convention

Translation messages always land on the **oldest maintained branch**, even when the
feature that introduced them only exists on a newer branch. Catalogs are append-only
with sequential ids, so the newer branch is a superset of the oldest: the missing
messages are exactly the trailing entries whose id is greater than the oldest
branch's current maximum. Adding them on the oldest branch with the **same ids**
keeps the eventual merge-up conflict-free.

## Progress checklist

- [ ] Step 1: Get maintained branches and fetch them
- [ ] Step 2: Create the branch off the oldest maintained branch
- [ ] Step 3: Discover the missing messages per catalog
- [ ] Step 4: Add each missing message to every locale
- [ ] Step 5: Verify
- [ ] Step 6: Commit per component (do not push)

---

## Step 1 — Get maintained branches and fetch them

Use the same endpoint as the `merge-up` skill:

```bash
curl -s https://symfony.com/releases.json
```

Read `maintained_versions`, already sorted oldest → newest (e.g.
`["6.4", "7.4", "8.0", "8.1"]`). The first is the **TARGET** (oldest); the rest are
the **NEWER** branches. Fetch all of them from upstream:

```bash
for b in <all maintained versions>; do git fetch upstream "$b"; done
```

---

## Step 2 — Create the branch off the oldest maintained branch

Branch off the upstream ref, never a local branch. Suffix the branch name with the
current date (`date +%Y-%m-%d`) so it stays unique across runs:

```bash
git checkout -b "sync-translations-$(date +%Y-%m-%d)" upstream/<TARGET>
```

---

## Step 3 — Discover the missing messages per catalog

The English catalogs to compare (one per component that ships translations):

```bash
git ls-tree -r --name-only upstream/<TARGET> | grep -E 'Resources/translations/.*\.en\.xlf$'
```

(Today: `Validator/.../validators.en.xlf`, `Form/.../validators.en.xlf`,
`Security/Core/.../security.en.xlf`. A NEWER branch may also introduce a brand-new
catalog file absent from the TARGET; pick those up by listing catalogs on the
newest branch too.)

Helper to extract `id<TAB>source` pairs from a catalog at any ref:

```bash
extract() { # $1 = git ref, $2 = path
  git show "$1:$2" 2>/dev/null | php -r '
$c = stream_get_contents(STDIN);
preg_match_all("#<trans-unit id=\"(\d+)\">\s*<source>(.*?)</source>#s", $c, $m, PREG_SET_ORDER);
foreach ($m as $x) { echo $x[1]."\t".$x[2]."\n"; }'
}
```

For each catalog file, go over **every NEWER branch** (oldest → newest) and collect
trans-units whose id is greater than the TARGET's current maximum id for that file:

```bash
TARGET_MAX=$(extract upstream/<TARGET> "$f" | cut -f1 | sort -n | tail -1)
extract upstream/<NEWER> "$f" | awk -F'\t' -v max="$TARGET_MAX" '$1 > max'
```

Union the results across NEWER branches and dedup by id (newer branches are
cumulative, so the newest usually already contains the full set). **Cross-check**:
every collected source must be absent from the TARGET catalog. If a source already
exists in the TARGET under a different id, the catalogs have diverged or been
renumbered: **stop** and ask the user.

Report the discovered messages (component, id, English source) before editing.

---

## Step 4 — Add each missing message to every locale

For each missing message, write a JSON file mapping **every non-en locale** of the
catalog to its translated target, then run the bundled script. Translate the source
yourself (a real translation per locale, never the English fallback); the script
adds the `state="needs-review-translation"` flag that marks them for native-speaker
review.

```bash
# translations.json: {"af": "...", "ar": "...", ..., "zh_TW": "..."}
php scripts/add_message.php \
  --dir src/Symfony/Component/Validator/Resources/translations \
  --id 146 \
  --source "This value is not a valid cron expression." \
  --translations translations.json
```

The script inserts the `<trans-unit>` before `</body>` in every `*.xlf` of the
directory: the English catalog gets the source as its target, every other locale gets
its translation. It is idempotent (a locale already holding the id is skipped) and
exits non-zero if the JSON doesn't cover exactly the catalog's non-en locales. Pass
`--source` already XML-ready (escape `&`, `<`, `>` if present) so it is reused
byte-for-byte. Run it once per catalog and per missing id.

Resulting entries look like:

```xml
            <trans-unit id="146">
                <source>This value is not a valid cron expression.</source>
                <target>This value is not a valid cron expression.</target>
            </trans-unit>
```

```xml
            <trans-unit id="146">
                <source>This value is not a valid cron expression.</source>
                <target state="needs-review-translation">Cette valeur n'est pas une expression cron valide.</target>
            </trans-unit>
```

---

## Step 5 — Verify

Well-formedness of every catalog touched:

```bash
for f in <catalog dir>/*.xlf; do xmllint --noout "$f" || echo "BAD: $f"; done
```

Catalog consistency (same ids/sources across all locales). Run only the
`TranslationFilesTest` of the components you actually touched, e.g. for Validator:

```bash
./phpunit src/Symfony/Component/Validator/Tests/Resources/TranslationFilesTest.php
```

Each touched component has its own test under
`src/Symfony/Component/<Name>/Tests/Resources/TranslationFilesTest.php` (Validator,
Form, Security/Core). All must be clean. An unrelated PHPUnit-config schema warning
is fine; a test failure is not.

---

## Step 6 — Commit per component (do not push)

One atomic commit per affected component, staging only that component's translation
directory:

```bash
git add src/Symfony/Component/Validator/Resources/translations/
git commit -m "[Validator] Add translated messages for the <Feature> constraint"
```

Name the commit after the feature when the new messages map to one (e.g. the Cron
constraint); otherwise describe the synchronization. Then **stop**: never push or
open the PR, hand control back to the user.

---

## Gotchas

- The feature (constraint, attribute) introducing a message may not exist on the
  oldest branch at all. That is expected: only the catalog message goes there, never
  the feature code.
- Every locale gets the entry, not just `*.en.xlf`, otherwise `TranslationFilesTest`
  fails on a missing id.
- Reuse the id and byte-for-byte English `<source>` from the NEWER branch, including
  placeholders like `{{ size }}`; a different id or altered source breaks the
  conflict-free merge-up and the consistency test.
- A catalog may legitimately have no missing messages; skip it silently.
- Handle each catalog (`Validator`, `Form`, `Security/Core`) independently, with its
  own commit and `[Component]` prefix.
