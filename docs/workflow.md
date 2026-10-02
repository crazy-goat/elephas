# Workflow: issue → worktree → code → review → PR → CI → merge → findings → cleanup

This is the development process of every `crazy-goat` repository. Project
commands (build, lint, tests) live in [`AGENTS.md`](../AGENTS.md) and are not
repeated here. The process is the same for humans and for coding agents.

Everything is written in **English**: code, comments, commits, docs, issues, PRs.

## Rules in short

- One issue = one worktree = one branch = one pull request.
- Work is driven by the **lowest open milestone** (`vX.Y.Z`).
- Every open issue in a milestone has one `type:*` and one `priority:*` label.
- Merge with **squash** only, and only when CI (`ci-ok`) is green on a branch that is up to date with the default branch.
- Update `CHANGELOG.md` in every PR that changes user-visible behaviour.
- The coder and the reviewer are **subagents** with a fresh context. They talk through
  two scratch files, `findings.md` and `review.md`, in the worktree root. Both are
  gitignored and never committed.
- Nothing is pushed before the review accepts the code.
- **Nothing found on the way is lost.** Findings become issues after the merge (step 7).

```text
1 pick ─▶ 2 worktree ─▶ 3 code ─▶ 4 review ─▶ 5 PR + CI ─▶ 6 merge ─▶ 7 findings ─▶ 8 cleanup
                         ▲          │ issues     │ CI red     │ conflict
                         └──────────┴────────────┘            └─▶ rebase, back to 5
```

## 1. Pick an issue

```bash
bin/pick-issue.sh                 # top 5 of the lowest open milestone
bin/pick-issue.sh --top=10        # more candidates
bin/pick-issue.sh --milestone=v1.2.0
bin/pick-issue.sh --json          # machine-readable, for agents
```

The script needs only `gh`. It finds the **lowest open milestone**, scores its
open issues from labels, title, age and comment count (it never reads issue
bodies, so it is cheap for an agent), and prints the top candidates with the
score breakdown. You still make the final pick. Blocked issues
(`status:blocked`, `status:needs-info`) are ranked last.

- **Release gate:** when the lowest milestone has no open issues left, the script
  exits with code **3** and prints `RELEASE NEEDED`. Stop. Cut the release first
  (see [release-workflow.md](release-workflow.md)), then run the script again.
  Do not take issues from a higher milestone.
- Read the issue, including **Where to start** and **Definition of done**.

## 2. Create a worktree

```bash
bin/worktree.sh <issue-number>          # optional 2nd argument: feat|fix|docs|refactor|test|chore
cd <worktree path printed by the script>
```

The script fetches the default branch and creates a worktree on branch
`<type>/issue-<N>-<slug>`. You choose where it goes; the first match wins:

1. `--dir <path>`: exactly `<path>` (a relative path is relative to where you are),
2. `WORKTREES_DIR=<dir>` in the environment: `<dir>/<repo>/issue-<N>`,
3. a `.worktrees` directory next to the clone (a workspace with several clones side by
   side): `../.worktrees/<repo>/issue-<N>`,
4. otherwise: `../<repo>-worktrees/issue-<N>`.

`bin/worktree-done.sh` finds the worktree by its branch name, so any location works. The
scripts are a convenience: a worktree you create yourself is fine, as long as its branch
is named `<type>/issue-<N>-<slug>` (the cleanup looks for `/issue-<N>-`) and the rest of
this process is followed.

The main checkout stays on the default branch and is not edited; it is for reading only.
The script also:

- creates empty `findings.md` and `review.md` (both gitignored),
- writes `.env.worktree` with a unique `COMPOSE_PROJECT_NAME` and a free host port for
  every `${..._PORT:-N}` variable found in a compose file,
- runs the repo's optional `bin/worktree-setup.sh` (install dependencies, start test
  containers, and so on).

Load the environment with `set -a && . ./.env.worktree && set +a` before running tests.

**Every repo must be worktree-safe**, so that several worktrees can run tests at the same
time. See [Worktree-safe repositories](#worktree-safe-repositories).

## 3. Code (coder subagent)

- Make the smallest correct change that satisfies the Definition of done.
- Add or update tests. A bug fix starts with a test that fails.
- Run the checks from `AGENTS.md` until they pass.
- Update `CHANGELOG.md` under `[Unreleased]` and the docs.
- **Commit** with [Conventional Commits](https://www.conventionalcommits.org/)
  (`fix: handle empty response (#42)`). **Do not push.**
- On a second or later round, first read `review.md` and fix every open point.

**Coder output contract.** The coder always reports: (1) the changed files, (2) the
biggest problem met on the way, and (3) every bug or weak spot noticed, **including ones
outside this issue's scope**, each with `file:line` and a suggested fix. Items (2) and (3)
are appended to `findings.md` (entries with role `coder`), not only written in the chat.
Do not fix out-of-scope findings in this PR.

## 4. Review (review subagent)

A separate agent with a fresh context reviews the branch diff against the default branch.
It checks correctness, error handling, missing tests, outdated docs, unrelated changes,
leftovers (debug code, commented-out code), and that everything is in English.

- It reads `review.md` first. For every earlier point it writes: **fixed**, **still
  present**, or **not a real problem** (with evidence). Then it looks for new problems.
- New in-scope problems go to `review.md`. New out-of-scope problems go to
  `findings.md` (role `review`), as in step 3.
- **Every point gets an answer**, including nits. Silence is not an answer.
- A point first seen in round 2 or later escaped round 1, which usually means a check
  is missing, so prefer adding a test over only fixing the line.
- Open points left → go back to **step 3**. No open points → the review accepts.

A finding entry has: role, `file:line`, what is wrong, severity, and a suggested fix.

## 5. Push, open the pull request, wait for CI

Only after the review accepts:

```bash
git push -u origin HEAD
gh pr create --fill --body "Closes #<N>"
gh pr checks --watch
```

- The PR title is a Conventional Commit. With squash merge it becomes the commit message.
- Use the PR template. Put `Closes #<N>` in the description.
- The required check is `ci-ok`. If CI fails, read the log (`gh run view --log-failed`),
  and go back to **step 3**. Do not disable or skip a check to make it green.
- PRs from first-time contributors need a maintainer to approve the workflow run.

## 6. Merge

When `ci-ok` is green:

```bash
gh pr merge --squash --delete-branch
```

The ruleset requires the branch to be **up to date** with the default branch, so `ci-ok`
has run on exactly the code that lands. If the default branch moved on, update the
branch and wait for `ci-ok` again (**step 5**):

```bash
gh pr update-branch            # merges the default branch into the PR branch
```

On a conflict, merge or rebase the default branch into the worktree branch, resolve the
conflicts, run the checks, push, and go back to **step 5**.

Then check that the issue was closed (`gh issue view <N> --json state`). When the merge
empties the milestone, go to [release-workflow.md](release-workflow.md) after step 8.

## 7. Follow-up findings

Run this step **after every merge**, also for small PRs. It turns `findings.md` into
tracked issues, without duplicates.

1. **Collect** the candidates from `findings.md`, plus the biggest problem the coder
   reported. Skip findings that were already fixed in this PR.
2. **Check them with a read-only subagent.** It must not edit files and must not create,
   edit or close issues. For every candidate it decides:
   1. **Is it real?** Read the cited lines on the current default branch. Skip it when
      the behaviour is by design and documented.
   2. **Is a similar issue already tracked?** Search open **and** closed issues.
      `gh` lists only 30 items by default, so always pass a limit:

      ```bash
      gh issue list --state open   --limit 200 --json number,title,labels
      gh issue list --state closed --limit 200 --json number,title,labels
      gh search issues --repo {owner}/{repo} --limit 50 "<keywords>"
      ```

      Overlapping scope counts as similar. Check issues named in `CHANGELOG.md` too.
   3. **Verdict:** *comment* on the existing issue, *create* a new issue, or *skip*
      (not real, or by design).
3. **Act on the verdicts:**
   - *comment*: add a comment to the existing issue with the new details, `file:line`
     and the PR link.
   - *create*: open a new issue **without a milestone**. It stays in the inbox until a
     maintainer triages it, so `bin/pick-issue.sh` does not pick it up by accident.

     ```bash
     gh issue create --title "<what is wrong>" \
       --label "type:bug" --label "priority:medium" --label "good first issue" \
       --body-file finding.md
     ```

     The body follows the issue form: **Description** (what, where as `file:line`,
     impact, link to the merged PR), **Where to start**, **Definition of done**.

     | The finding is... | Labels |
     |---|---|
     | small and clear, a newcomer can fix it | `type:*`, `priority:*`, `good first issue` |
     | bigger, but not urgent | `type:*`, `priority:*`, `help wanted` |
     | urgent (data loss, security, crash) | `priority:critical`; tell the maintainer, who assigns the milestone |
     | needs a decision or more information | `status:needs-info` |

     A `good first issue` without **Where to start** helps nobody, so fill it in.
4. **Report** the numbers of the created and commented issues in the final message.
5. If an automated check could have caught the defect, prefer adding the check (test,
   linter rule) over only writing an issue.

## 8. Clean up

```bash
bin/worktree-done.sh <issue-number>     # from the main checkout
```

The script stops the worktree's containers (`docker compose down -v`), runs the optional
`bin/worktree-teardown.sh`, removes the worktree, switches the main checkout to the default
branch, pulls it, and deletes the local branch. `findings.md` and `review.md` disappear
with the worktree, so run this only after step 7.

## Worktree-safe repositories

Every repo must allow several worktrees to build and test at the same time.

- **No fixed host ports** in compose files. Write `"${RABBITMQ_PORT:-5672}:5672"`, never
  `"5672:5672"`. The default keeps the main checkout unchanged; `bin/worktree.sh` puts a
  free port for each variable into `.env.worktree`. Variable names must contain `PORT`.
- **No `container_name`.** Names are derived from `COMPOSE_PROJECT_NAME`, which is unique
  per worktree.
- Tests read host, port and credentials from environment variables, never from
  hard-coded values.
- `.gitignore` contains `/findings.md`, `/review.md` and `/.env.worktree`.
- Dependencies (`vendor/`, `node_modules/`) live inside the worktree. Put the install step in
  `bin/worktree-setup.sh`.
- Optional `bin/worktree-setup.sh` and `bin/worktree-teardown.sh` hold everything specific
  to the repo (starting test containers, seeding data, and so on).

## Checklist

- [ ] Issue picked with `bin/pick-issue.sh`; it has `type:*`, `priority:*` and a milestone
- [ ] Work done in a worktree (not the main checkout), branch `<type>/issue-<N>-<slug>`
- [ ] Tests added, all checks pass in the worktree
- [ ] `CHANGELOG.md` and docs updated
- [ ] Committed but not pushed before the review accepted
- [ ] `review.md`: every point answered, no open points
- [ ] `findings.md`: coder and review findings recorded
- [ ] PR title is a Conventional Commit and the description has `Closes #<N>`
- [ ] `ci-ok` is green, PR merged with squash
- [ ] Findings checked by a subagent; existing issues commented, new issues created without a milestone
- [ ] `bin/worktree-done.sh` run; main checkout is on a fresh default branch
