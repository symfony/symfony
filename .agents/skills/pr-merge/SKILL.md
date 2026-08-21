---
name: pr-merge
description: >
  Merge a reviewed pull request the way the Symfony core team does: one
  --no-ff merge commit per PR, whose message archives the whole discussion,
  with the review gates checked first. Use when asked to merge a PR, to
  finish a merge that stopped on conflicts, or to verify that a merge was
  done right.
---

# Merging a pull request

Every pull request lands as exactly one `--no-ff` merge commit on the target
branch, even a single-commit PR. The first-parent history therefore reads as
one line per PR, the inner commits keep the contributor's authorship, and the
merge commit archives the PR: its number, title, contributors, description
and commit list in the message, and the PR comments in a git note. The
repository stays self-contained: the history explains itself without GitHub.

This skill states the full process: run it end to end, resume it after a
conflict, or use it to verify a merge. Do all the local work first, then
stop exactly once for confirmation before anything outward happens.

This skill starts where `pr-review-merge-prep` ends: the PR has been
reviewed, targets the right branch and is merge-ready. After a merge into an
older maintained branch, `merge-up` carries it to the newer branches.

## Confirmation rule

Merging publishes: it pushes upstream, edits the PR and comments on it.
Everything up to and including the local merge commit is reversible, so do
it without asking. Then stop once, before the first outward action, with a
summary: target branch, suggested category and why, squash decision, CI
verdict with the failures triaged, the linked issues that will be closed,
and any gate that needs an override. One
confirmation covers all the outward steps that follow. Treat anything but
an explicit affirmative as no, and never bury a blocker in the summary.

## The merge commit

```
{category} #{number} {title} ({contributors})

This PR was merged into the {branch} branch.

Discussion
----------

{PR title}

{PR body}

Commits
-------

{output of: git log {target}..pull/{N} --oneline}
```

- `{category}` is one of `feature`, `bug`, `minor`, `security`, `tidy` (see
  below). The head line is load-bearing: release and changelog tooling parse
  it, and maintainers grep history for `bug #`, `feature #` and PR numbers.
- `{contributors}` is the comma-separated list of unique commit authors, in
  commit order, GitHub login preferred, raw author name as fallback.
- The second line varies:
  - squashed: `This PR was squashed before being merged into the {branch} branch.`
  - retargeted: `This PR was submitted for the {asked} branch but it was merged into the {branch} branch instead.`
  - retargeted and squashed: `This PR was submitted for the {asked} branch but it was squashed and merged into the {branch} branch instead.`
- Security releases: the head becomes `security #cve-XXXX-NNNNN {title}
  ({contributors})` and the message stops after the branch line. No
  Discussion, no Commits, no notes: the details stay out of the repository
  until the advisory is public.
- Every `@login` in the message is wrapped in backticks, so pushing the
  commit does not ping the people mentioned in the discussion.
- The PR comments are attached to the merge commit as a git note under
  `refs/notes/github-comments`, one block per comment:
  `by {login} at {created_at}` followed by the body. Skip bot comments:
  carsonbot and the CI bots add nothing worth archiving.

## Categories

The category decides whether the change appears in the CHANGELOG and
triggers a release. Suggest one yourself from the diff and the discussion,
with a one-line reason, as part of the pre-push summary; the user corrects
it there when needed.

| Category | Meaning |
|---|---|
| `feature` | New feature, merged into the dev branch only |
| `bug` | Bug fix, merged into the oldest affected maintained branch |
| `minor` | Noteworthy change that is neither (new translations, generic types); still listed and released |
| `security` | Security fix with a CVE, merged during a coordinated release |
| `tidy` | Not worth a release: coding standards, CI, typos, test-only fixes |

Some satellite repositories (ux, ai, twig, reprise) add `documentation`.

## Gates

Check these while preparing the merge and put the results in the pre-push
summary. Overriding a failed gate is always the user's decision, never the
skill's.

- **Core team approval**: at least two `+1` and no standing `-1` among core
  team members, counting GitHub reviews (approved is `+1`, changes requested
  is `-1`) and comment votes (`+1`, `-1`, thumbs emoji). Votes by the PR
  author do not count. The merger's own approval counts as one of the two
  when the merger is on the team and did not author the PR.
- **Milestone matches the target branch**: a version milestone names the
  branch the fix must land in (resolved through symfony.com/releases). On a
  mismatch, either fix the milestone or retarget the merge (step 5).
- **The branch is still maintained**: no merging into a branch past end of
  maintenance without an explicit decision.
- **CI**: check it in the background. It needs nothing from the working
  tree, so start it first (`gh pr checks <N>`, or the statuses and check
  runs on the head SHA) and let it run while the other steps proceed. Triage
  every failing job before the summary: a failure caused by the PR is a
  blocker to report with evidence, a pre-existing or unrelated failure is
  noted and moved past. fabbot and the static-analysis jobs are known
  false-positive producers; ignoring them is fine when what they flag is
  unrelated to the PR. Required checks still pending mean the verdict is not
  in yet. One confirmation covers all jobs; never ask per job.

Check the rest with `gh pr view <N> --json reviews,milestone,baseRefName`.

## The procedure

`REMOTE` is `origin` here. `TARGET` is the branch being merged into,
`ASKED` the PR's base branch; they differ only when retargeting.

1. **Preconditions.** The working tree is clean for tracked files
   (`git status --porcelain --untracked-files=no` prints nothing). Start
   the CI gate check in the background now.

2. **Update the branches.** The local branch must not have diverged:

   ```bash
   git fetch $REMOTE
   git checkout $TARGET && git merge --ff-only $REMOTE/$TARGET
   # when retargeting, the same for $ASKED
   ```

3. **Fetch the PR head** into a local `pull/<N>` branch:

   ```bash
   git fetch -f $REMOTE refs/pull/$N/head:pull/$N
   ```

4. **Squash when warranted.** A multi-commit PR from a single contributor is
   squashed by default (branch-update merge commits made through the GitHub
   UI do not count as a second contributor). A multi-author PR is merged
   unsquashed to keep each author's commits, unless the commits carry no
   meaning and the user agrees to squash across authors. A PR that contains
   merge commits cannot be merged unsquashed: squash it, or rebase the merge
   commits away first. The squashed commit keeps the first commit's author
   and author date, and takes the PR title as its message:

   ```bash
   git checkout pull/$N
   base=$(git merge-base $ASKED pull/$N)   # the PR is still based on $ASKED at this point
   first=$(git rev-list $base..pull/$N | tail -1)
   last=$(git rev-list $base..pull/$N | head -1)
   author=$(git log -1 --format='%an <%ae>' $first)
   date=$(git log -1 --format=%ad $first)
   git reset --hard $first~
   git merge --squash $last
   git commit -m "$PR_TITLE" --author="$author" --date="$date"
   ```

5. **Retarget when merging into another branch than the PR asked:**

   ```bash
   git rebase --onto $TARGET $ASKED pull/$N
   ```

   Remember that retargeting is a rewrite, not a copy: the review skill's
   rules on expressing the fix in the target branch's shape apply.

6. **Build the message** from the template above and write it to a file.
   Generate the Commits section now, from the final shas:
   `git -c color.ui=false log $TARGET..pull/$N --oneline`.

7. **Merge without committing:**

   ```bash
   git checkout $TARGET
   git merge --no-ff --no-commit pull/$N
   ```

8. **Commit** with the prepared message: `git commit --file=<message file>`.

9. **Attach the notes** (skip for security merges and comment-less PRs):

   ```bash
   git fetch -f $REMOTE refs/notes/github-comments:refs/notes/github-comments
   git notes --ref=github-comments add --file=<notes file>
   ```

10. **Validate.** Run the test suites of the touched components
    (`./phpunit src/Symfony/Component/<Name>`) whenever the merge involved a
    rebase, a squash with conflicts, or anything beyond what CI already ran.

11. **Stop for the single confirmation** described at the top: present the
    summary, collect the CI verdict from the background check, and get one
    go-ahead for everything that follows.

12. **Sync the PR on GitHub when the head was rewritten** (squashed or
    retargeted), before the upstream push, so GitHub can mark the PR merged
    instead of closed. Only possible when the PR allows maintainer edits;
    otherwise skip, and the PR will show as closed. When retargeted, change
    the PR base first (`gh pr edit $N --base $TARGET`), then force-push the
    rewritten head to the contributor's fork, guarded by the head SHA the PR
    had:

    ```bash
    git push --no-follow-tags --force-with-lease=$HEAD_BRANCH:$OLD_HEAD_SHA $FORK_SSH_URL pull/$N:$HEAD_BRANCH
    ```

    This is the only force-push in the whole process, and it never targets
    the upstream repository. If it fails, restore the PR base.

13. **Clean up and push:**

    ```bash
    git branch -D pull/$N
    git push --no-follow-tags $REMOTE $TARGET refs/notes/github-comments
    ```

    Leave `refs/notes/github-comments` out when no note was added.

14. **Close the loop on GitHub.** Thank the author with a one-line comment
    (skip when the merger authored the PR, or for bots). Close the linked
    issues: the `Issues` row of the PR header table (`Fix #NNNNN`) and
    closing keywords in the body name them. GitHub auto-closes them only
    when the merge lands in the default branch, which is the dev branch
    here; a merge into any other branch leaves them open, so close each one
    with a short comment naming the PR and branch that fixed it. For a
    `feature` merged into symfony/symfony, open an issue on
    symfony/symfony-docs, milestoned to the target version, linking the PR
    and its authors, unless the PR body already links a real docs PR.

## Conflicts and amending

- To amend the PR before merging (a review fix, a changelog line), pause
  after step 5 on the `pull/<N>` branch, amend there, then continue with
  step 6.
- When step 7 conflicts and the PR is editable, abort the merge and rebase
  `pull/<N>` onto the target instead: conflicts get resolved in the PR's
  own commits, and the recorded merge stays conflict-free. Then redo step 7.
- When the PR is not editable, resolve inside the merge by hand and
  continue.
- Amendments follow `pr-review-merge-prep`: keep the contributor's
  authorship, amend into their commits, never a placeholder identity.

## Verify before pushing

```bash
git show -s --format=%B HEAD                        # message matches the template, category exact
git log -1 --format='%an <%ae> / %cn <%ce>' HEAD    # merger as author and committer of the merge commit
git log --first-parent --oneline $REMOTE/$TARGET..$TARGET   # exactly one new first-parent commit
git notes --ref=github-comments show HEAD           # notes attached, when expected
```

The inner commits must keep the contributor as author. The committer of
rewritten inner commits is the merger; that is expected.

## Hard rules

- Never force-push to the upstream remote, never rewrite the target branch.
  A merge adds exactly one merge commit on top of it.
- Never merge with a plain `git merge`: without the prepared message, the
  archive value of the commit is lost and tooling cannot classify it.
- Never push with `--follow-tags`; local tags stay local.
- Never decide an override (a PR-caused CI failure, missing votes, an
  unmaintained branch, a milestone mismatch, an unsquashable PR) without
  asking; surface it in the pre-push summary.
