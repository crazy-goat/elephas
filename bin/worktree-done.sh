#!/usr/bin/env bash
# Remove the worktree of a merged issue and return to a fresh default branch.
#
# Usage: bin/worktree-done.sh <issue-number>
set -euo pipefail

usage="usage: bin/worktree-done.sh <issue-number>"
[[ $# -eq 1 && $1 =~ ^[0-9]+$ ]] || { echo "$usage" >&2; exit 1; }
issue="$1"

# The main checkout, also when this runs inside one of its worktrees.
root="$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")"
default="$(gh repo view --json defaultBranchRef --jq .defaultBranchRef.name)"
# Find the worktree by its branch (<type>/issue-<N>-<slug>), wherever it is.
# The first record is the main checkout; it is never removed.
matches="$(git -C "$root" worktree list --porcelain | awk -v n="$issue" '
  /^worktree / { wt = substr($0, 10); count++ }
  /^branch / && count > 1 { b = $2; sub("^refs/heads/", "", b); if (b ~ ("/issue-" n "(-|$)")) print wt }
')"

if [[ -z "$matches" ]]; then
  echo "No worktree with a branch for issue #$issue (git worktree list)" >&2
  exit 1
fi
if [[ "$(wc -l <<<"$matches")" -gt 1 ]]; then
  echo "More than one worktree for issue #$issue; remove the right one by hand:" >&2
  echo "$matches" >&2
  exit 1
fi
dir="$matches"
if [[ ! -d "$dir" ]]; then
  echo "Worktree $dir is missing; run: git worktree prune" >&2
  exit 1
fi

branch="$(git -C "$dir" rev-parse --abbrev-ref HEAD)"

if [[ -f "$dir/.env.worktree" ]]; then
  (
    cd "$dir"
    set -a
    # shellcheck disable=SC1091
    . ./.env.worktree
    set +a
    export COMPOSE_ENV_FILES=.env.worktree
    if [[ -x bin/worktree-teardown.sh ]]; then bin/worktree-teardown.sh; fi
    for f in docker-compose*.y*ml compose*.y*ml */docker-compose*.y*ml; do
      [[ -f "$f" ]] && docker compose -f "$f" down -v --remove-orphans 2>/dev/null || true
    done
  )
fi

git -C "$root" worktree remove --force "$dir"
git -C "$root" switch "$default"
git -C "$root" pull --ff-only
git -C "$root" branch -D "$branch" 2>/dev/null || true
git -C "$root" worktree prune
echo "Done. $root is on a fresh $default."
