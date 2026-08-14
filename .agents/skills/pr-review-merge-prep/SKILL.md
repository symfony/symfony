---
name: pr-review-merge-prep
description: Principles for rigorously reviewing a pull request and making it merge-ready. Use when reviewing PRs (single or batched by milestone), verifying a submitted fix, retargeting a PR to another branch, or amending a contributor's work before merge.
---

# PR review and merge preparation

## Reviewing

- Re-derive the root cause from the code. Do not trust the PR title, the description or the author's branch analysis. Verify every claim yourself. Use small probe scripts when reasoning alone leaves doubt.
- Read the whole discussion before judging: the linked issue, the PR comments, the reviews and the inline comments. Compare each reviewer request with the current diff. Flag requests that were ignored or only partly applied.
- Revert-verify every new test: put the changed source back in its base state, keep the tests, and confirm they fail with the expected error, not another one. A test that also passes on base protects existing behavior; it does not prove the fix. Be clear about the role of each test.
- Use three-state verification to judge a rework: base (bug present), PR as submitted (what it really does), final (fixed). Run the same checks in the three states. The pattern of failures across the states is the proof.
- Validate the test harness before trusting it: run it on the known-broken state and watch it fail. A check that cannot tell "read the right thing" from "fell back to a default" proves nothing. Give each outcome its own marker.
- Check the branch target in both directions. Find the commit that introduced the flaw and probe the older maintained branches directly. Bugfixes go to the oldest affected maintained branch. Behavior changes and features go to the dev branch, and a released X.Y is not the dev branch anymore. The reported symptom and the underlying flaw can have different oldest branches: check both.
- List the edge cases and run them. Do not accept plausible reasoning when a probe can answer: nullable, union and interface types, empty input, recursion, encodings, interaction with sibling state.
- Test composition: rebase onto the current base tip and rerun. When sibling PRs touch the same area, also run the combined result.
- Account for every failing CI job: reproduce it when possible and classify it as caused by the PR or pre-existing. A red job nobody can explain is a finding.
- Before blaming the PR for a failure, reproduce the failure on the base branch. Local environments have pre-existing failures.
- Check documentation duties in both directions: a behavior change needs its changelog and upgrade entries, and a plain bugfix must not carry entries that belong to feature branches.
- Test at the layer where the defect lives. Assertions on intermediate state (service wiring, generated config) cannot catch a bug that only appears in a later phase (compiled container, runtime, a real tool run). When the bug lives there, the test must go there.

## Batch review

- To review many PRs (for example a whole milestone), run one subagent per PR, each in its own git worktree. Parallel runs must never share mutable state.
- Fetch all PR heads first, in one command, into stable refs outside `refs/heads/`, such as `refs/reviews/pr-<number>`. Agents must not depend on `FETCH_HEAD`.
- Disable checkout hooks inside worktrees. Share the main checkout's dependency directory only when every PR targets the same branch and agents never write to it; otherwise give each worktree its own install. Prime shared test-runner caches once, before starting the agents.
- Never let agents touch the main checkout; treat it as owned by the user, along with the branch namespace it works in. Worktrees check out detached: a branch checked out in a worktree is locked for the whole repository, so the user can no longer fetch into it or check it out, and the failure surfaces in their session rather than in the agent's. Review and merge-prep need no branch of their own, since commit, amend, rebase, cherry-pick and push all work on a detached HEAD.
- Give every agent the same brief: the full single-PR standard above plus the batch-wide checks (branch target, composition with sibling PRs), returning a structured report with verdict, root cause, branch conclusion, and findings with evidence.
- When an agent stalls or dies, resume it with a message restating where it was and which constraints apply; do not restart the review from scratch.

## Making it merge-ready

- Preserve the contributor's authorship: amend into their commit, or rebuild their commits with the same author. Keep a commit structure that has meaning, such as a tests-then-fix pair. Update the commit message to cover what was added; a message must always match its diff.
- Fixes added during review follow the same TDD rule as the original bug: failing test first, then the fix.
- Retargeting to another branch is a rewrite, not a copy: express the fix in the target branch's code shape and conventions (available APIs, test style, annotations or attributes, tool versions). Rebuild tests that depend on things the target branch lacks. Record the known merge-up resolution for the higher branch so the merger does not have to rediscover it.
- A conflict-free rebase can still produce broken code: an import the source branch happened to have, a test runner that ignores newer test syntax. The test run decides, not the rebase.
- Rebase onto the current base tip and confirm the parent commit equals it. Check again just before pushing: active repositories move. Run rebases and merges non-interactively (`GIT_EDITOR=true`).
- Run scoped tests while iterating and the full suite of each touched component before calling it done; never run the whole monorepo at once. Check static analysis and lowest-dependency jobs when the change can affect them. If an assertion depends on the installed dependency version, use feature detection instead of assuming one version.
- Push to the PR's real head with an explicit `HEAD:<head-branch>` refspec, since the work happens on a detached HEAD. The fork's repository name can differ from the upstream one; query it.
- Update the PR's metadata to match what it has become: title and component prefix, header table, description, labels, base and milestone. On a PR that sat, the `Branch?` row names a branch that has since shipped, the description keeps wording the review already corrected, and the component prefix can name the wrong package. Leave exactly one status label, and none the diff no longer justifies.
- Treat the description as the first draft of the documentation, not as a review log. Write it for someone who never saw the discussion: what the feature does, the public API it adds, the options and their defaults, which choice to make when, and the traps. For a feature, end with the points the documentation must carry, so whoever opens the symfony-docs pull request does not have to reconstruct them from the diff.
- Put what changed during review in a new comment instead. Those notes matter to the people following the thread and stop mattering once it merges, while the description outlives it.
- Keep changelog entries to one line each, naming the new public surface and nothing more. Reasoning, comparison tables and usage guidance belong in the documentation. A changelog entry that has to explain itself is a sign the documentation entry is missing.
- Apply the house rules to inherited content too: when amending someone else's commit, remove what the rules forbid instead of keeping it. Scan the added diff lines for violations before pushing.
- Finish on GitHub: post review feedback as a real review with evidence, close replaced issues with a factual explanation, and correct wrong claims left in threads.

## House rules

- Write code comments sparingly, only where they add value the code cannot express. Never reference issues or pull requests in code or tests.
- Use TDD for every fix: failing test first, implementation second, full suite of the touched component last.
- No em-dashes, no `Co-Authored-By` trailers, no credit to AI tools anywhere: code, commit messages, PR titles and bodies, review comments, issue comments.
- Keep a factual tone in everything published: findings and evidence, no self-promotion, no filler.
- Use plain English: common words, short sentences, one idea per sentence. Avoid idioms, cultural references and rare vocabulary. Most readers are not native speakers.

## Reporting

- Lead with the verdict. Then list findings ranked by severity, each with its evidence: what was run, on which state, and the exact failure text.
- Separate regressions from pre-existing gaps, and blockers from polish. Say which findings the merger must act on.
- State what was not covered as plainly as what was.
- In a batch, relay each verdict as soon as it lands. When all reports are in, give a summary table: one line per PR with its verdict and target branch.
- For the detailed pass, present one PR at a time and wait for a go-ahead keyword (such as "next") before the following one. This keeps each review open for discussion and lets fixes happen before moving on.
