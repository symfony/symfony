---
name: hardening-rule
description: >
  Decide whether a recurring hardening invariant is worth a CI gate, and add it
  without hitting the traps. Covers PHPStan custom rules (implementation-shape
  checks) and the check-hardening-tests.php convention (test-presence checks).
  Use when the user says "write a PHPStan rule", "add a hardening rule", "gate
  this pattern", "catch this in CI", or wants to turn a finding into a durable check.
---

# Symfony Hardening Rule

Turns a recurring hardening invariant into a CI gate. The **mechanics are obvious from the
three shipped rules** (`HardenedComparisonRule`, `UnserializeToStringTrampolineRule`,
`UnserializeMissingAllowedClassesRule`): copy the class shape and the registration. This
skill is the part the example files cannot teach: when a rule is worth building, and the
traps that cost an afternoon.

## When to build (and when not)

A PHPStan rule earns its place only if it catches a **broad, recurring shape across many
sites**. The shipped rules each span dozens (every webhook compare; 60+ `__unserialize`;
every `unserialize()`). If your rule effectively targets one class or one call site, it is
**too narrow**: it carries CI weight for almost no coverage. Use a regression test instead.
(A verify-MAC-before-deserialize rule was built and dropped for exactly this reason: it
matched a single serializer.)

Pick the mechanism by what the invariant is about:

| Invariant is about... | Use |
|---|---|
| A code **shape** recurring across many sites | a PHPStan rule (this skill) |
| A **test** existing for a boundary class | extend `check-hardening-tests.php` |
| A value being **attacker-controlled** (data-flow) | Psalm taint, or a reviewer note; not a syntactic rule |
| A **curated set** being complete (subnets, attributes) | a unit test next to the data |
| One class / one site | a regression test, not a rule |

Do not force a taint problem into a syntactic rule: if safe and unsafe look identical at the
sink and differ only by where a value came from, the rule is all false positives.

## The shape (copy, do not reinvent)

Model the new rule on the existing `.github/sa-tools/rules/*Rule.php`: global namespace,
Symfony license header, `implements Rule` with `getNodeType()`/`processNode()`, a stable
`identifier('symfony.<camelCase>')`, and the narrowest node type that sees the shape.

## The traps

1. **Registration is one line plus the file.** Drop `<Name>Rule.php` into
   `.github/sa-tools/rules/` and add its class to the `rules:` list in
   `.github/sa-tools/rules.neon` (which `phpstan.dist.neon` includes). The folder's autoloader
   (`rules/bootstrap.php`, wired once via `--autoload-file` in `static-analysis.yml`) and the
   `.gitignore` folder whitelist (`!rules`, `!rules/*`) pick the file up with no further edits.
   A rule that needs constructor arguments takes a `services:` entry tagged
   `phpstan.rules.rule` in that same `rules.neon` instead of the `rules:` shorthand. (PHPStan
   only autoloads a project-local rule class early enough via `--autoload-file` or composer
   PSR-4 — a neon `scanDirectories`/`bootstrapFiles` runs too late — hence the stable
   bootstrap, which you never touch per rule.)
2. **`phpstan analyse` counts are nondeterministic** (result cache + parallel workers report
   0 then N for the same input). Verify the logic with `PHPStan\Testing\RuleTestCase` in a
   throwaway `composer require phpstan/phpstan phpunit/phpunit` project, with fixtures for
   the unsafe shape (must fire), the fixed shape (silent), and the nearest safe look-alike
   from the decision boundary (silent). Never gate on a local `analyse` count.
3. **The diff-lint only flags new errors.** `phpstan-diff.php` compares the PR against the
   base branch and fails only on errors new to the PR, so standing violations are absorbed.
   Design target: zero findings on the current tree at introduction (no baseline entry).

## Convention path

If the invariant is "this boundary class must ship its regression test", extend
`check-hardening-tests.php` (pure tokenizer, no autoload) instead of writing a rule. Add the
boundary predicate and the required-test predicate in its existing style; accepted gaps go in
the `ALLOWLIST` const as a TODO with a reason, never a permanent waiver.

## Error handling

- If the invariant needs data-flow, do not ship a syntactic rule that fakes it. Say it is a
  taint or checklist item and **wait for confirmation** on the fallback.
- Do not edit `phpstan-diff.php` or the workflow to make a rule pass. Fix the rule.
- House conventions: Symfony license header, no em-dashes, no Claude/Anthropic credit,
  comments sparingly, no issue numbers in code.
