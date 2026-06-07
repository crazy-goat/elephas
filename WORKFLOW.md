# Workflow: Issue → Feature Branch → Implementation → Code Review → PR → CI → Merge

This document describes the complete workflow for handling issues in the
[elephas](https://github.com/crazy-goat/elephas) repository using `gh` and `git`.

---

## 1. Browse Open Issues

```bash
# List open issues (title, number, labels)
gh issue list --state open --limit 30

# View a specific issue (description, labels, state)
gh issue view <NUMBER> --json title,body,labels,state
```

**Criteria for selecting the most impactful issue:**
- Issues labeled `enhancement` + `ci` (e.g. #157 – CI optimization) – high impact
- Issues about stability, data correctness, performance
- Issues blocking other tasks (e.g. #131 – CI fail on missing deps)
- Issues most relevant to users (e.g. API docs, README fixes)

---

## 2. Create a Fresh Feature Branch

```bash
# Make sure you're on main with the latest changes
git checkout main
git pull origin main

# Create a feature branch
git checkout -b feat/issue-<NUMBER>-<short-description>
```

**Branch naming convention:** `feat/issue-<NUMBER>-<kebab-case>`
(e.g. `feat/issue-157-cache-tb-client-build`)

---

## 3. Implement the Change

```bash
# Edit files, then commit and push
git add -A
git commit -m "feat(core): implement <short description> (closes #<NUMBER>)"
git push origin feat/issue-<NUMBER>-<description>
```

**Commit message convention:**
- Type: `feat`, `fix`, `docs`, `refactor`, `ci`, `test`, `chore`
- Scope: `(core)`, `(client)`, `(backend)`, `(ci)`, `(dto)` etc.
- Reference to issue: `(closes #<NUMBER>)`

---

## 4. Code Review via Subagent

After implementation, run a code review using a subagent (separate agent with
its own context). The subagent checks:

- Alignment with project architecture (ARCHITECTURE.md)
- Type correctness and signatures
- Error handling and edge cases
- Coding style (PSR-12, php-cs-fixer)
- Test coverage
- Security (FFI, input validation)

```bash
# The subagent receives a task like:
# "Code review the changes in files: <list of files>.
#  Check: type correctness, error handling, PSR-12 compliance,
#  missing tests, outdated documentation.
#  List all issues to fix."
```

---

## 5. Fix Issues Found in Code Review

```bash
# For each problem found:
# 1. Apply the fix
# 2. Commit with a descriptive message
git add -A
git commit -m "fix: <description of fix>"
git push origin feat/issue-<NUMBER>-<description>
```

**All issues must be fixed – even the least significant ones.**

---

## 6. Repeat Code Review

After fixing, invoke the subagent for another code review.

Repeat steps 5→6 until the subagent reports no issues.

> **Acceptance criteria:** The subagent responds: "Code looks good, no issues
> to fix."

---

## 7. Run Linters and Tests Locally

Before opening a PR, verify that all linters and tests pass on your machine:

```bash
# Run all linters (php-cs-fixer dry-run, phpstan, rector dry-run)
composer lint

# Auto-fix fixable issues (php-cs-fixer, rector)
composer lint-fix

# Run unit tests
composer test-unit

# Run functional tests (requires Docker + TigerBeetle)
composer test-functional
```

`composer lint-fix` automatically corrects style issues reported by
php-cs-fixer and rector. After running it, commit any fixes:

```bash
git add -A
git commit -m "style: auto-fix lint issues"
```

Only create the PR when all lints and tests pass locally.

---

## 8. Create a Pull Request

```bash
# Create a PR from the feature branch to main
gh pr create \
  --title "feat: <short description> (closes #<NUMBER>)" \
  --body "## Description

Closes #<NUMBER>

## Changes

- <list of changes>

## Code Review

- [ ] Passed subagent code review
- [ ] All review comments addressed" \
  --base main \
  --assignee @me
```

---

## 9. Wait for CI

```bash
# Check PR status
gh pr view --json statusCheckRollup

# Wait for all checks to finish
gh pr checks --watch
```

CI workflow (`.github/workflows/tests.yaml`) runs:
1. **lint** – composer validate, composer audit, php-cs-fixer, phpstan, rector
2. **test-matrix** (PHP 8.2, 8.3, 8.4) – unit tests, build native lib, functional tests
3. **tests** – aggregator checking that lint and test-matrix passed

---

## 10. Handle CI Failures

If CI fails:

```bash
# 1. See which checks failed
gh pr checks

# 2. View logs
gh run view --log --job <job-name>

# 3. Fix the issues locally
# 4. Run code review via subagent again (repeat steps 4-6)
# 5. Commit the fixes
git add -A
git commit -m "fix: <description of CI fix>"
git push origin feat/issue-<NUMBER>-<description>

# 6. Wait for CI to re-run
gh pr checks --watch
```

**Repeat until all CI checks pass.**

---

## 11. Merge PR and Close Issue

```bash
# Merge PR (squash merge recommended for clean history)
gh pr merge --squash --delete-branch

# Close the issue (automatic if commit contains "closes #<NUMBER>")
# Alternatively:
gh issue close <NUMBER>
```

---

## 12. Switch Back to main

```bash
git checkout main
git pull origin main
```

Done. Ready to start the next cycle from step 1.

---

## Quick Reference – Full Cycle

```bash
# 1. Pick an issue
gh issue list --state open --limit 30
gh issue view <NUMBER>

# 2. Feature branch
git checkout main && git pull origin main
git checkout -b feat/issue-<NUMBER>-<description>

# 3. Implementation
# ... coding ...
git add -A && git commit -m "feat: implement <desc> (closes #<NUMBER>)"
git push origin feat/issue-<NUMBER>-<description>

# 4. Code Review (subagent)
# ... fix issues ... (repeat until clean)

# 5. Run linters and tests locally
composer lint
composer test-unit

# 6. PR
gh pr create --title "feat: <desc> (closes #<NUMBER>)" --body "..." --base main

# 7. CI
gh pr checks --watch
# ... if failures → fix, code review, push → wait for CI (repeat)

# 8. Merge
gh pr merge --squash --delete-branch
gh issue close <NUMBER>

# 9. Switch back
git checkout main && git pull origin main
```

---

## Notes

- **gh** must be configured and authenticated (`gh auth status`).
- All commits must be signed-off if the repo requires DCO.
- Keep feature branches short-lived. If a rebase is needed:
  ```bash
  git fetch origin main
  git rebase origin/main
  git push --force-with-lease origin feat/issue-<NUMBER>-<description>
  ```
- Code review via subagent runs locally – the subagent has access to
  read/write/edit/bash tools. Give it clear instructions on what to check.
