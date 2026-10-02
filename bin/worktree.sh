#!/usr/bin/env bash
# Create an isolated worktree for one issue and prepare its environment.
#
# Usage: bin/worktree.sh <issue-number> [type]
#   type: feat|fix|docs|refactor|test|chore (default: derived from the type:* label)
#
# Creates ../<repo>-worktrees/issue-<N> on branch <type>/issue-<N>-<slug>, empty
# findings.md and review.md (both gitignored), a unique COMPOSE_PROJECT_NAME and
# free host ports in .env.worktree, then runs the optional bin/worktree-setup.sh.
set -euo pipefail

issue="${1:?usage: bin/worktree.sh <issue-number> [type]}"
type="${2:-}"

root="$(git rev-parse --show-toplevel)"
repo="$(basename "$root")"
default="$(gh repo view --json defaultBranchRef --jq .defaultBranchRef.name)"

info="$(gh issue view "$issue" --json title,labels)"
title="$(jq -r .title <<<"$info")"

if [[ -z "$type" ]]; then
  label="$(jq -r '[.labels[].name | select(startswith("type:"))][0] // ""' <<<"$info")"
  case "${label#type:}" in
    bug|security) type=fix ;;
    feature|performance) type=feat ;;
    docs) type=docs ;;
    tests) type="test" ;;
    refactor) type=refactor ;;
    *) type=chore ;;
  esac
fi

slug="$(tr '[:upper:]' '[:lower:]' <<<"$title" | tr -cs 'a-z0-9' '-' | sed 's/^-//; s/-$//' | cut -c1-40 | sed 's/-$//')"
branch="$type/issue-$issue-$slug"
dir="$(dirname "$root")/$repo-worktrees/issue-$issue"

git -C "$root" fetch origin "$default"
git -C "$root" worktree add -b "$branch" "$dir" "origin/$default"

cd "$dir"
for f in findings.md review.md; do
  printf '# %s for #%s\n\n' "${f%.md}" "$issue" >"$f"
done

project="$(tr -c 'a-z0-9\n' '-' <<<"$repo-issue-$issue")"
{
  echo "COMPOSE_PROJECT_NAME=$project"
  # Every ${NAME_PORT:-1234} in a compose file gets a free host port here.
  grep -rhoE '\$\{[A-Z0-9_]*PORT[A-Z0-9_]*:-[0-9]+\}' --include='*compose*.y*ml' . 2>/dev/null \
    | sed -E 's/^\$\{([A-Z0-9_]+):-.*/\1/' | sort -u | while read -r name; do
      port="$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1])')"
      echo "$name=$port"
    done
} >.env.worktree

export COMPOSE_PROJECT_NAME="$project" COMPOSE_ENV_FILES=.env.worktree
if [[ -x bin/worktree-setup.sh ]]; then
  bin/worktree-setup.sh
fi

echo
echo "Worktree: $dir"
echo "Branch:   $branch"
echo "Use:      cd $dir && set -a && . ./.env.worktree && set +a"
