---
name: security-triage
description: >
  Triage a reported security finding into a disposition: a private CVE
  (coordinated disclosure + advisory), a public hardening PR (fix in the open,
  no CVE), or not-a-security-issue (reply to reporter). Assigns severity and
  affected maintained branches, and routes to the next step. Use when the user
  says "does this need a CVE", "CVE or hardening", "triage this report", "is
  this a security issue", "classify this finding", or "how should we disclose this".
---

# Symfony Security Triage

Decides how a finding is handled, not whether the code is wrong. It complements
`symfony-security-review` (which finds missing hardening) by making the disclosure
call on a report.

The three dispositions and the conventions that record them:

| Disposition | Label | Branch prefix | Process |
|---|---|---|---|
| **CVE** | `Has CVE` + severity | `cve-*` | Private fix, GHSA/CVE, credit, blog post, coordinated release |
| **Public hardening** | none / `Not a security issue` | `harden-*`, `hardening-*`, `pin-*`, `fix-*` | Normal open PR, changelog, no embargo |
| **Not a security issue** | `Not a security issue` / `Won't fix` | n/a or `fix-*` | Reply to reporter; optionally a doc/robustness PR |

This skill produces a recommendation. The final call belongs to the Symfony security
team; treat its output as a structured argument, and defer to `symfony.com/security`
for the authoritative "what is not a vulnerability" list.

## Progress checklist

- [ ] Step 0: Establish the facts (reproduce, scope, trust model)
- [ ] Step 1: Apply the disposition decision tree
- [ ] Step 2: Assign severity and affected maintained branches
- [ ] Step 3: Route (branch prefix, labels, next workflow, reporter reply)

## Confirmation rule

Whenever this skill says **"Wait for confirmation"**, treat anything other than an
explicit affirmative as **no**: stop and ask the user how they want to proceed.

---

## Step 0 — Establish the facts

Before classifying, pin down four things. Guessing any of them produces a wrong call.

1. **Reachability**: is the vulnerable code on a path reachable from untrusted input in
   a default configuration, or does it need opt-in/insecure config?
2. **Actor and precondition**: what must the attacker already have? Unauthenticated and
   remote is the worst case; "already controls the serialized bytes" or "already has the
   app secret" usually means the precondition is itself game-over.
3. **Impact**: RCE, auth/authz bypass, SSRF to internal, signature/secret bypass that
   accepts forged input, XSS on a default-rendered surface, secret disclosure, open
   redirect, session fixation, or "only" DoS / info of low value.
4. **Contract**: is the component meant to defend this boundary (router,
   HttpFoundation/HttpClient, Security, webhook verification, HtmlSanitizer, Mime
   parsing), or is the unsafe behaviour documented as the caller's responsibility
   (deserializing untrusted bytes, a trusted-channel feature such as ESI,
   `template_from_string`)?

Reproduce if at all possible; an unreproducible report is not yet triable.

## Step 1 — Disposition decision tree

Apply in order. The first matching bucket wins.

### It is **not a security issue** if any of these hold
- **Pure DoS / resource exhaustion.** Generally excluded from the CVE pipeline. Fix as
  hardening with a limit if cheap, but no CVE.
- **Requires misuse contrary to documentation**, with no default-config attack. The
  component is not contracted to defend this (e.g. deserializing untrusted bytes through
  an API documented as trusted-only; using a trusted-channel feature to reach internal hosts).
- **Dev-only tooling** (profiler, debug, web-profiler) manifesting only in a dev environment.
- **Precondition is already game-over** (attacker already holds the app secret, controls
  the deserialized input contract, or has local/physical access).
- **Not reproducible**, or rooted in a third-party dependency outside Symfony's control.
- **Not a realistic bypass** (a comparison nuance with no working exploit, etc.).

### It is a **CVE** only if **all** of these hold
1. **Default-reachable**: exploitable against a default or documented-safe config.
2. **Expected actor**: the attacker is at or below the trust level the boundary is
   meant to enforce (typically unauthenticated/remote, or a lower-privileged user
   escalating), with no game-over precondition.
3. **Contracted boundary**: the component is meant to defend this (see Step 0.4).
4. **Real impact**: RCE, auth/authz bypass, SSRF to internal, sanitizer/signature
   bypass accepting forged input on a sensitive sink, stored/reflected XSS on a default
   surface, secret disclosure, or open redirect with meaningful reach.
5. **Maintained**: the vulnerable code ships in a maintained version.

### Otherwise it is **public hardening**
A genuine improvement where a CVE condition fails. Typical shapes:
- **Defense-in-depth** on top of an existing control, or that only matters once another
  bug already holds (an `__unserialize` `__toString` trampoline guard needs a pre-existing
  untrusted `unserialize()` entry to matter).
- **Safer-default change** where the old default was not a vulnerability under the
  threat model (adding a webhook IP allowlist, pinning the HMAC algorithm).
- **Limited impact or exposure** even though a boundary is technically crossed (a forged
  webhook against an opt-in endpoint with a user-configured secret injects only a
  delivery-status event).
- **Robustness** improvements (input-length caps, broader sanitizer coverage).

## Step 2 — Severity and affected branches

**Severity** (match the `low`/`medium`/`high` labels; CVSS is a sanity check, not the goal):
- **high**: unauthenticated RCE, auth bypass, full sanitizer bypass enabling stored XSS, SSRF reaching internal services.
- **medium**: reflected XSS, open redirect, signature bypass with bounded impact, info disclosure of non-trivial data.
- **low**: defense-in-depth, narrow-config or low-impact issues, most hardening.

**Affected branches**: find the oldest version where the vulnerable code exists, intersect
with `maintained_versions` from `https://symfony.com/releases.json`. Fix on the lowest
maintained affected branch, then merge up (see the `merge-up` skill). Record the
oldest exposure even if it predates maintained versions.

## Step 3 — Route

State the recommendation as: **disposition + severity + affected maintained branches +
the one-line rationale (which decision-tree conditions decided it)**, then route:

- **CVE**: name the branch `cve-<slug>-<branch>`; apply `Has CVE` + severity; the fix is
  prepared privately and goes through the coordinated-disclosure process (request a
  GHSA/CVE, credit the reporter, prepare the security release and blog post). Do **not**
  open a public PR or push to a public remote before release. **Wait for confirmation**
  before any outward step.
- **Public hardening**: name the branch `harden-`/`hardening-`/`pin-`/`fix-<slug>`; open a
  normal PR with a CHANGELOG entry; use `symfony-security-review` to confirm the fix and
  `hardening-rule` to add a durable gate where the class recurs.
- **Not a security issue**: draft a short, factual reply to the reporter explaining why
  (cite the contract/threat-model reason), and optionally a doc clarification or
  low-priority robustness PR. Apply `Not a security issue` / `Won't fix`.

In every case, the fix follows TDD, component-scoped tests, no em-dashes,
no Claude/Anthropic credit, comments sparingly, no issue references in code.

---

## Worked examples (abstracted patterns)

| Finding shape | Disposition | Deciding factor |
|---|---|---|
| SSRF filter (private-network client) bypassed in a default configuration | **CVE** | default control bypassed, unauthenticated reach |
| HTML sanitizer lets a `javascript:` URL through on a default profile | **CVE** | sanitizer's core contract bypassed, stored XSS |
| URL generator emits a path that crosses a routing boundary by default | **CVE** | boundary crossed in default use |
| A signed transport decodes the payload before verifying its MAC | **CVE candidate, high** | pre-auth RCE if signing is meant to defend a malicious broker; confirm the trust model |
| Webhook signature compared with `!==`, or the secret is ignored | **Hardening** | opt-in endpoint, user-configured secret, bounded impact |
| `__unserialize` assigns a string property without a `\Stringable` guard | **Hardening** | needs a pre-existing untrusted `unserialize()` entry (game-over precondition) |
| Input-length cap / broader sanitizer coverage added | **Hardening** | robustness, not a default-exploitable bypass |
| Unbounded recursion / regex backtracking / cache growth on input | **Hardening, low** | pure DoS, excluded from the CVE pipeline |
| Deserializing bytes through an API documented as trusted-only | **Not a security issue** | documented contract, caller's responsibility |
| A trusted-channel feature (e.g. ESI/SSI) used to reach internal hosts | **Not a security issue** | trusted by design |
| Case-insensitive host allowlist with no working bypass | **Not a security issue** | not a realistic bypass |

## Gotchas

- **"Boundary crossed" does not imply CVE.** Impact and exposure decide it. A forged
  webhook against an opt-in, user-secret endpoint is hardening; an SSRF filter bypass in
  default use is a CVE.
- **DoS is the most common miscategorisation.** Resource exhaustion is hardening/low,
  not a CVE, even when trivially triggerable.
- **Defense-in-depth tells.** If exploiting the finding requires another, already-present
  vulnerability or a leaked secret, it is hardening.
- **Trust-model questions are for the maintainer.** A signed-transport verify-order case
  turns on whether the broker is trusted; surface the question, do not assume.
- **Embargo discipline.** Never name a CVE-bound finding, push a `cve-*` branch, or open a
  public PR for a CVE-class finding before the coordinated release. **Wait for confirmation.**
- **Defer to authority.** `symfony.com/security` is the source of truth for what is not a
  vulnerability; this skill encodes observed practice, not policy.

## Error handling

- If reachability or trust model is unknown, say so and triage as needs-human-judgement;
  do not force a disposition.
- Security reports are handled privately. Do not echo report contents into public
  artifacts, commit messages, or branch names that leak the vulnerability before release.
- Never push to a public remote during CVE triage. Stop and hand back to the user.
