---
name: symfony-security-review
description: >
  Review a change (a PR, the current branch diff, or a set of files) or audit a
  component or the whole tree for missing or incorrect security hardening. Reasons
  about trust boundaries from first principles, then checks the code against
  Symfony's hardening-invariant families and runs the .github/sa-tools gates. Use
  when the user says
  "security review", "security audit", "check hardening", "review this PR/branch
  for security", "audit <component> for <vuln class>", "is any hardening
  missing", or names a vulnerability class to hunt for.
---

# Symfony Security Review

Finds hardening that is missing or wrong, grounded in code. It runs in two modes:

- **Review a change** (default): a PR, the current branch vs its base, or named files.
- **Audit a target**: a component path or the whole `src/` tree, for one or all families.

Both modes run two passes: first reason about the change's trust boundaries from first
principles (to catch novel issues), then check it against the hardening-invariant families
catalogued in [`hardening-families.md`](hardening-families.md). The catalogue is a checklist
of known classes, not the search space.

## Scope and non-goals

- This skill **spots** missing hardening. It does not triage an incoming report
  end to end, assign a CVE, or merge a fix. Those are separate workflows.
- **Every finding must point at a real sink** (a concrete file and line). No
  speculative findings. A family with no sink in scope is not in scope; but a sink that
  matches no family is still in scope (that is what Step 1 is for).
- Respect the decision boundaries in the catalog: several plausible-looking
  shapes were ruled "not a security issue". Do not raise those.

## Progress checklist

- [ ] Step 0: Pick mode and resolve the scope
- [ ] Step 1: First-principles boundary pass (open-world)
- [ ] Step 2: Map to families (known-class checklist)
- [ ] Step 3: Run the automated gates
- [ ] Step 4: Manual per-family review
- [ ] Step 5: Report findings

## Confirmation rule

Whenever this skill says **"Wait for confirmation"**, treat anything other than an
explicit affirmative as **no**: stop and ask the user how they want to proceed.

## Parallelism (large audits only)

When auditing the whole `src/` tree or a large multi-file component, fan Step 1 and Step 4
out to subagents: one per family, or one per file-batch, each returning findings that point
at a concrete file and line (no speculative findings leaking in through parallelism). Keep
the rest in the main loop: aggregation, de-duplication, the completeness check, and the
report are synthesis and must see every finding at once. For a single PR or a small diff,
run every step inline; subagents are pure overhead there.

---

## Step 0 — Pick mode and resolve the scope

**Review a change.** Resolve the diff and the changed non-test files:

```bash
# A public PR
gh pr diff <n> --repo symfony/symfony
# The current branch against its base (e.g. 6.4)
git diff <base>...HEAD --stat
```

**Audit a target.** Take a component path (e.g. `src/Symfony/Component/Mailer`)
or the whole `src/` tree. There is no diff; the "changed files" are the target files.

In both modes, build the file list and **drop tests** (`*/Tests/*`). Hardening
lives in the implementation; tests are checked separately in Step 3.

State the resolved mode and scope back to the user in one line before continuing.

## Step 1 — First-principles boundary pass (open-world)

This pass finds what the catalogue does not list. Do it before mapping to families, and do
not let the anchors narrow it. For each file in scope, reason as an attacker, independent of
any known family:

1. **Inputs**: what untrusted data can reach this code? Request (query/body/headers/cookies),
   uploaded files, environment, a broker/message payload, a stored blob later deserialized, a
   webhook, a third-party API response, a database value that was originally user-set.
2. **Flow**: follow each input to where it is used. Does it reach a sink (filesystem, process,
   SQL, deserialization, object construction, HTML output, a redirect, an XML/URL parser, a
   comparison of a secret)?
3. **Boundary**: which trust boundary does it cross, and who is the expected actor
   (unauthenticated, lower-privileged, a malicious broker)?
4. **Worst case**: if the attacker fully controls the input, what is the maximum impact?
   State it concretely (RCE, auth bypass, SSRF, file read/write, XSS, info leak).

Record every input-to-sink path with a non-trivial worst case as a candidate finding,
**whether or not it matches a catalogued family**. An unguarded path from untrusted input to
a dangerous sink is a finding even if no anchor names it. This step uses no greps by design;
it is meant to see sinks the dictionary misses.

## Step 2 — Map to families (known-class checklist)

Now apply the catalogue as a checklist, to confirm no *known* class slipped past Step 1.
**Treat each anchor's listed APIs as seed examples of an abstract role** (an inbound
authenticator, a secret comparison, a deserialization entry, an XML/URL sink, a file or
process sink); extend to anything in scope that plays that role, including classes the grep
does not name.

For each file in scope, decide which families it touches using the grep anchors in
[`hardening-families.md`](hardening-families.md). Run the anchors against the scope, not
the whole tree, when reviewing a change:

```bash
# Example: which families does this branch touch?
git diff <base>...HEAD --name-only | grep -v /Tests/ > /tmp/scope.txt
grep -lf <(printf 'extends AbstractRequestParser\nfunction __unserialize\nhash_hmac(\nvalidateOnParse\nloadXML(\nescapeshellarg(\npreg_match') $(cat /tmp/scope.txt) 2>/dev/null
```

Carry forward both Step 1's boundary findings and the families with a sink in scope; they
proceed to Step 4. List them.

## Step 3 — Run the automated gates

These encode the already-shipped invariants. Run them first; anything they catch
needs no manual argument.

**Hardening-test convention** (tokenizer only, always runs locally):

```bash
php .github/sa-tools/check-hardening-tests.php
```

Fails if a concrete `AbstractRequestParser` lacks a `RejectWebhookException` test,
or a class with `__unserialize()` and a string property lacks a `__toString`
gadget test. Accepted gaps live in its `ALLOWLIST` const.

**Custom PHPStan rules** (`HardenedComparisonRule`, `UnserializeToStringTrampolineRule`,
`UnserializeMissingAllowedClassesRule`). In CI these run base-vs-PR through
`phpstan-diff.php`, which only fails on errors **new to the PR**. To reproduce
locally you need PHPStan installed (it is not in `composer.json`; CI installs it
ad hoc). The source of truth for a rule's logic is a `RuleTestCase`, **not** an
ad-hoc `phpstan analyse` run:

```bash
# Only meaningful if phpstan is installed in the project
./vendor/bin/phpstan analyse --error-format=json --no-progress \
  --autoload-file=.github/sa-tools/rules/bootstrap.php
```

Do **not** trust raw `phpstan analyse` counts for these rules: the result cache
plus parallel workers make them nondeterministic (a rule can report 0 then N for
the same input). See the gotchas.

## Step 4 — Manual per-family review

For each in-scope family, apply its invariant from [`hardening-families.md`](hardening-families.md).
The catalog gives, per family: the grep anchor, the invariant, the automated
coverage (if any), and the decision boundary.

Work the sinks, not the families in the abstract: for every sink site the anchor
found, answer the family's check question. If the answer is "no guard / wrong
guard", it is a candidate finding; confirm it is not excluded by the decision
boundary before reporting.

## Step 5 — Report findings

Output a table, highest severity first:

| Location | Family | Severity | Invariant at risk | Suggested hardening | Hardening test |
|---|---|---|---|---|---|
| `Component/.../Foo.php:NN` | webhook-verify | High | signature compared with `!==` | `hash_equals()` | extend parser reject test |

**Severity rubric** (match the corpus, not CVSS theatre):

- **Critical**: pre-auth RCE or full auth bypass (e.g. verify-after-deserialize, gadget chain).
- **High**: conditional RCE, SSRF, signature/secret bypass, XXE with file read.
- **Medium**: stored/reflected XSS, open redirect, sensitive info leak.
- **Low**: DoS / resource exhaustion / defense-in-depth only.
- **Not a finding**: excluded by a decision boundary (say which one).

When a finding moves on to `security-triage`, Critical and High both map to its
`high` label; Medium and Low map to `medium` and `low`.

For each real finding, state whether a **hardening regression test** is required so
`check-hardening-tests.php` (or a component test) keeps it from being dropped later.

**Completeness check (before you finalise).** State explicitly: is there an untrusted input,
a sink, a deserialization, an authentication, or a trust boundary in scope that matched **no**
anchor and was not already raised in Step 1? If so, reason about it from scratch before
reporting. A clean family sweep is not a clean review.

The table holds findings from both passes: the Step 1 boundary pass (Family column = the
vulnerability class, or `novel`) and the Step 4 family review.

Separate **confirmed** findings from **needs-human-judgement** ones. Do not inflate.

---

## If a finding is real: fix handoff

This follows the house conventions:

- **TDD**: write the failing hardening test first, then the fix.
- **Component-scoped tests only**: `./phpunit src/Symfony/Component/<Name>` (never the whole suite).
- Add the regression test at the boundary so the convention gate covers it; for a
  durable implementation-shape check, see the `hardening-rule` skill.
- No em-dashes, no `Co-Authored-By`, no Claude/Anthropic credit. Comments sparingly,
  and do not reference issue numbers in code or tests.

## Gotchas

- **Decision boundaries are real.** An empty `$secret` that disables webhook
  verification is documented behaviour, not a bug. A literal `allowed_classes` on
  a first-party cache file was ruled not-a-security-issue. Pure DoS / memory
  exhaustion is generally outside the CVE pipeline (treat as Low). The catalog
  lists these; respect them or you will cry wolf.
- **Only string-typed properties trampoline.** In `__unserialize()`, int/float/bool
  coercion throws `TypeError` without calling `__toString`. Do not flag non-string slots.
- **PHPStan custom-rule counts are nondeterministic** (result cache + parallel
  workers). Verify rule behaviour with `RuleTestCase`, and rely on the CI
  base-vs-PR diff (`phpstan-diff.php`), not local counts.
- **The diff-lint only flags what a PR adds.** Standing violations on the tree are
  filtered out by design, so a clean local audit does not mean the tree is clean;
  it means the PR introduced nothing new.
- **Data families are answer-keys.** SSRF subnet lists and the HtmlSanitizer URL
  attribute set drift with specs; a static check only confirms a human-curated set,
  it cannot find the next missing entry. Flag these for a completeness test, not a gate.
- **Anchors are seed examples, not the search space.** Novel issues surface in the Step 1
  first-principles pass, not the greps. A finding that matches no family is still a finding;
  do not let the dictionary bound the review.

## Error handling

- Never fabricate a sink. If the anchor finds nothing, say the family is out of scope.
- Security reports are handled privately. Do not echo report contents into public artifacts.
- When unsure whether something crosses a decision boundary, report it as
  needs-human-judgement with the boundary named, and **wait for confirmation**.
