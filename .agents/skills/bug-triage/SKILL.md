---
name: bug-triage
description: >
  Decide whether open Bug PRs target the correct branch. A bug must be fixed on
  the lowest maintained branch where it exists, then merged up. Use when the
  user says "triage bug PRs", "which PRs need retargeting", or "retarget triage".
---

# Symfony Bug-PR Retarget Triage

Symfony fixes bugs on the **lowest maintained branch where the bug exists**, then
merges up. This skill produces a retarget recommendation per PR. The final call
belongs to a maintainer; never act without explicit confirmation.

---

## Steps

### Step 0 — Scope

**Maintained branches:**
```bash
curl -s https://symfony.com/releases.json
# maintained_versions, e.g. ["6.4","7.4","8.0","8.1","8.2"]; LOWEST = first entry.
# DEV (new-feature target) = first entry AFTER latest_stable_version, not the last
# entry: when X.4 and Y.0 are developed in parallel both are dev, and features go to
# the lower one (they merge up).
```

**Resolve the upstream remote** (not always `origin`):
```bash
REMOTE=$(git remote -v | awk '/[:\/]symfony\/symfony(\.git)?[[:space:]]+\(fetch\)/{print $1; exit}')
# Stop and ask the user if empty
```

**Fetch all maintained branches**, then collect open Bug PRs:
```bash
git fetch "$REMOTE" <each maintained branch>

gh pr list --repo symfony/symfony --label Bug --state open --limit 300 \
  --json number,title,baseRefName,milestone,isDraft
# Skip drafts unless asked. A milestone/base mismatch is itself a signal.
```

---

### Step 1 — Investigate each PR (one subagent per PR, run concurrently, batches of 8-10)

Each subagent is read-only and returns a structured verdict.

**1a. Fetch PR data:**
```bash
gh pr view <N> --repo symfony/symfony \
  --json number,title,baseRefName,milestone,body,files,labels,closingIssuesReferences,url
gh pr diff <N> --repo symfony/symfony
gh pr view <N> --repo symfony/symfony --comments
```
From the body Q&A table, read `Branch?`, `Bug fix?`, `New feature?`, `Deprecations?`.

**1b. Bug fix or feature? (objective check)**

The `Bug fix?` field is self-declared and can be wrong. The real check: does the
production diff **add public/protected API surface**?

```bash
# Scan only non-Tests/ files for added API
gh pr diff <N> --repo symfony/symfony \
  | awk '/^diff --git /{skip=($0 ~ /\/Tests\//)} !skip' \
  | grep -nE '^\+\s*(public|protected)\s+(function|const|readonly|static|\??[A-Za-z\\]+\s+\$)|^\+\s*(final\s+)?(class|interface|trait|enum)\s'
```

If the diff grows the surface → treat as **feature** (target `DEV`, never retarget down).
Two false positives to discount: code merely *moved* also shows as `+` lines, and
adding to an `@internal`/`@experimental` class is not a BC extension.
Exception: implementing a marker interface (e.g. `ResetInterface`) to fix a state
leak is still a bug fix; weigh intent in those cases.

**1c. Read linked issues:**
```bash
# Also parse the body for "Fix #", "Closes #", "Resolves #"
gh issue view <ISSUE> --repo symfony/symfony --json title,body,labels,comments
# The reported Symfony version is the strongest signal for how far back the bug reaches.
```

**1d. Probe lower branches** (for each branch below the PR's base, oldest first):
```bash
# 1. Does the file exist?
git cat-file -e "$REMOTE/<branch>:<path>" && echo present || echo absent

# 2. Does the buggy code exist there?
git show "$REMOTE/<branch>:<path>"

# 3. When did the code arrive? (determines the regression floor)
git log -S'<symbol>' --oneline "$REMOTE/<branch>" -- <path>
```

**1e. (Optional) Reproduce on the candidate branch:**
```bash
git worktree add --detach ../symfony-sf-triage-<N> "$REMOTE/<branch>"
cd ../symfony-sf-triage-<N> && ./phpunit src/Symfony/Component/<Name>
cd - && git worktree remove --force ../symfony-sf-triage-<N>
```

**Subagent verdict:** PR number, component, current base, **recommended base**,
confidence (high/medium/low), deciding evidence, whether the fix needs adaptation.

---

### Step 2 — Decision tree (first match wins)

**Keep on current base if any hold:**
- Not a bug fix (feature, deprecation, or grows the public/protected API surface).
- The touched code does not exist on any lower branch.
- The regression was introduced on the current base or later (lower branches never had it).
- Fixing lower would change frozen/contractual behaviour (flag for maintainer).

**Retarget DOWN to branch `T` if all hold:**
1. It is a **bug fix** (fixes wrong behaviour, doesn't add capability).
2. `T` is the **oldest maintained branch where the bug exists**, bounded by the
   regression floor and never below `LOWEST`.
3. The fix **applies on `T`** (directly or with a documented adaptation).

**Raise the base (rare):** the PR targets a branch where the code doesn't exist yet,
or an unmaintained branch → recommend the lowest maintained branch that has the code.

---

### Step 3 — Aggregate report

Collect verdicts into a table ordered by action then component:

```
PR      Component     Base  →  Recommend  Conf   Why
#64613  Validator     7.4      6.4        high   issue reports 6.4; method present & broken on 6.4
#64589  ObjectMapper  8.1      8.1 (keep) high   component added in 8.1; absent below
#64576  Serializer    8.1      7.4        med    regression introduced in 7.4 (commit abc123)
```

Routing (each is outward, so **wait for confirmation** before doing anything):
- **Retarget:** draft a short factual comment citing the evidence.
- **Keep:** record the reason so the PR is not re-triaged.
- **Needs human judgement:** low-confidence or behaviour-change cases → state the open question.

---

### Step 4 — Execute (only on explicit user authorization)

**When to comment-only vs. rebase+push:**
- Comment-only when: `maintainerCanModify` is false; the fix needs real code
  adaptation; or the PR is already approved (a force-push dismisses reviews).
- Rebase+push when commits cherry-pick cleanly onto the target.

**Rebase mechanics:**
```bash
# Cherry-pick PR commits onto a fresh branch off the target (do NOT rebase the whole branch)
git fetch <fork-url> <headRef>; OLD=$(git rev-parse FETCH_HEAD)
git checkout -B retarget-<N> $REMOTE/<target>
git cherry-pick <oid>...

# Run tests before pushing
./phpunit src/Symfony/Component/<Name>

# Push with lease, then change base
git push <fork-url> HEAD:<headRef> --force-with-lease=<headRef>:$OLD
gh pr edit <N> --repo <repo> --base <target> --milestone <target>

# Clean up
git checkout <dev-branch> && git branch -D retarget-<N>
```

After retargeting, also update the body's `| Branch? |` value and remove any
labels that contradict the new disposition (e.g. `Feature` on a confirmed bug fix).

---

## Quick reference table

| PR shape | Recommendation | Deciding factor |
|---|---|---|
| Bug on 8.1; issue reproduced on 6.4; method present & broken on 6.4 | **Retarget to 6.4** | bug reaches the floor |
| Bug on 8.2; offending line introduced in a commit first shipped in 8.0 | **Retarget to 8.0** | regression floor is 8.0 |
| Bug on 8.1 in a component that didn't exist before 8.1 | **Keep on 8.1** | code absent on lower branches |
| `New feature? yes` opened on 8.1 | **Raise to DEV** | features target the dev branch |
| Maintainer commented "please rebase on 7.4" | **Retarget to 7.4 (high)** | explicit instruction overrides inference |
| Early "rebase on 6.4", later "this is a feature" | **Follow the latest: keep on DEV** | most recent instruction wins |
| PR already approved; bug reaches lower branches | **Comment-only, ask author** | force-push dismisses reviews |
| Fix uses 8.x-only API but bug exists on 6.4 | **Retarget to 6.4, adapt the fix** | don't leave 6.4 broken |
