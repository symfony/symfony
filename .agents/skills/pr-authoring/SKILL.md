---
name: pr-authoring
description: >
  Hold your own work to the review standard of this repository, so that a change
  is merge-ready when it is opened. Use when the user asks to fix an issue, to
  add a feature, to refactor, or to make any other change to this codebase, and
  read it before writing the first line of code.
---

# Authoring a pull request

## Consult the review standard first

- Read `.agents/skills/pr-review-merge-prep/SKILL.md` in full before writing code. It states what a change must satisfy to be merged here.
- That skill speaks from the reviewer's seat, and every requirement in it applies to the code you write yourself. A reviewer will hold your pull request to it, so hold your work to it first.
- This skill says when to consult the review skill and what to do with the answer. It repeats only the rules that apply to every keystroke, listed under House rules below, so that they hold even before that skill is open. When the two seem to disagree, the review skill decides.

## Before writing code

- Choose the target branch before the first commit, with the branch rules of the review skill. The target decides which APIs the code may use, which test style it follows, and where the changelog entry goes. Changing it later means rewriting the change, not rebasing it.
- Re-derive the problem from the code, whatever the issue or the request says it is. A report describes a symptom. Work from the cause you found yourself.
- Reproduce the current behavior with a probe or a failing test before changing anything. A fix for a problem you never saw happen cannot be verified.
- Run the public-API challenge of the review skill against your own design before you build it, not after. Dropping a method that is not needed costs nothing at that point. Defending one that is not justified costs a whole discussion later.

## While working

- Keep the diff to what the request needs. Unrelated cleanups make the change harder to review, and they can pull it toward another target branch. Report what you find outside that scope, or send it as its own pull request.
- Apply the house rules of the review skill to everything you produce: tests first, comments, tone, attribution, order of methods, plain English.

## Running the tests

- Run one component at a time, by path: `./phpunit src/Symfony/Component/<Name>`, and the same form for `src/Symfony/Bridge/<Name>` and `src/Symfony/Bundle/<Name>`. Everything under `src/Symfony/Contracts` is a single suite: `./phpunit src/Symfony/Contracts`.
- Never run the whole monorepo at once. It takes hours, and it buries the failure you are looking for.
- Narrow to one test file or one filter while iterating, then run the full suite of every touched component before calling the change done.
- Read the summary line, not the exit status alone. A run can end with `Tests: N, Failures: 1`, or die on a fatal error before any banner, and colour codes sit in front of those words.
- After every rebase or merge, rerun the tests of the patched components. A replay that raised no conflict still produces broken code. Run the rebase or merge itself non-interactively, with `GIT_EDITOR=true`.

## Before opening it

- Review your own diff with the review skill, as if someone else had written it. Run the checks it asks a reviewer to run: revert-verify each new test, probe the edge cases, check every borrowed symbol against the declared version constraints, check that the changelog and upgrade entries sit in the unreleased section, and run the full suite of every touched component together with the style tool.
- Apply what that pass finds. Do not file the findings in the description as known limitations, because a reviewer reads them as work left undone.
- Shape the commits before pushing: a message that matches its diff, and a structure that carries meaning, such as a failing test followed by the fix.
- Write the description for someone who never saw the request: what the change does, the public API it adds, the options and their defaults, and the traps. Fill the header table, and give the title the component prefix.
- Say which checks you ran and what they returned. State what you did not cover just as plainly.

## Opening it

- Opening a pull request, pushing to it and commenting on it are outward actions. Ask the user before the first one, unless they already asked for the pull request.
- Push the branch to a fork. Never push a working branch to the upstream repository.

## House rules

These hold whether or not the review skill is open, and they cover inherited content too, such as a commit you amend or a patch you rebase.

- Use TDD: the failing test comes first, the implementation second, the full suite of the touched component last.
- Write code comments sparingly, only where they add value the code cannot express. Never reference an issue or a pull request from code or from tests.
- No em-dashes. No `Co-Authored-By` trailer. No credit to Claude, to Anthropic or to any other AI tool, anywhere: code, commit messages, pull request titles and bodies, review comments, issue comments.
- Keep a factual tone in everything published, and use plain English: common words, short sentences, one idea per sentence. Most readers are not native speakers.
