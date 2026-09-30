#!/usr/bin/env bash
# Remove the worktree of a merged issue and return to a fresh default branch.
#
# Usage: bin/worktree-done.sh <issue-number>     (run from the main checkout)
set -euo pipefail

issue="${1:?usage: bin/worktree-done.sh <issue-number>}"

root="$(git rev-parse --show-toplevel)"
repo="$(basename "$root")"
default="$(gh repo view --json defaultBranchRef --jq .defaultBranchRef.name)"
dir="$(dirname "$root")/$repo-worktrees/issue-$issue"

if [[ ! -d "$dir" ]]; then
  echo "No worktree at $dir" >&2
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
