# Release workflow

One milestone = one release. Versions follow
[Semantic Versioning](https://semver.org/) and the changelog follows
[Keep a Changelog](https://keepachangelog.com/). Tags are `vX.Y.Z`.

Everything is written in **English**, including release notes.

## 1. Release gate

A release is ready when the milestone has **no open issues**:

```bash
gh api repos/{owner}/{repo}/milestones --jq '.[] | select(.title=="vX.Y.Z") | {title, open_issues, closed_issues}'
```

- Open issues that will not make it: move them to the next milestone
  (`gh issue edit <N> --milestone vX.Y.(Z+1)`).
- The default branch must have a green `ci-ok`.

## 2. Choose the version

| Change | Bump |
|---|---|
| Bug fixes only | patch (`1.2.3` → `1.2.4`) |
| New, backward compatible features | minor (`1.2.3` → `1.3.0`) |
| Breaking changes | major (`1.2.3` → `2.0.0`) |

Before `1.0.0`, breaking changes bump the minor version. The milestone title
already holds the planned version. Change the milestone title if the plan changed.

## 3. Prepare the CHANGELOG (pull request)

```bash
git switch -c chore/release-vX.Y.Z
```

In `CHANGELOG.md`:

- Rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`.
- Add a fresh empty `## [Unreleased]` above it.
- Group entries under Added, Changed, Deprecated, Removed, Fixed, Security.
- Update the compare links at the bottom, if the file has them.
- Bump the version in files that carry it (`composer.json` does not need it, but
  `package.json`, `version.go`, extension headers do). See `AGENTS.md`.

Open a PR titled `chore: release vX.Y.Z`, wait for `ci-ok`, squash merge
(`gh pr merge --squash --delete-branch`). Push the branch with an explicit ref:

```bash
git push -u origin refs/heads/chore/release-vX.Y.Z
```

elephas carries no version number in source files. The `CHANGELOG.md` section is
the only thing to prepare.

## 4. Tag

Tag the merge commit on the default branch with an **annotated** tag:

```bash
git switch main && git pull --ff-only
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin refs/tags/vX.Y.Z
```

## 5. GitHub Release

Pushing the tag starts `.github/workflows/release.yaml`. GitHub runs the workflow
file from the **tagged commit**, so the release PR with the `## [X.Y.Z]` section
must be **merged before** you tag.

1. `build-libs` builds `libtb_client` with Zig on four platforms: `ubuntu-latest`
   (linux x86_64), `ubuntu-24.04-arm` (linux arm64), `macos-15-intel` (macOS x86_64)
   and `macos-15` (macOS arm64). Each asset gets a `.sha256` file.
2. `release` downloads the artifacts, writes `SHA256SUMS.txt` and creates the GitHub
   Release with the notes from the matching `CHANGELOG.md` section. It fails when the
   section is missing or empty. Tags with a `-` (for example `v1.0.0-rc.1`) become
   pre-releases.

GitHub rejects release notes longer than 125000 characters. The workflow cuts the notes
below 120000 characters at a line boundary and adds a link to `CHANGELOG.md` at the tag.

```bash
gh run watch
gh release view vX.Y.Z
```

## 6. Close the milestone

```bash
gh api -X PATCH repos/{owner}/{repo}/milestones/<number> -f state=closed
```

Make sure the next milestone `vX.Y.(Z+1)` (or the next minor) exists.

## 7. After the release

- Check that install instructions work with the new version (Packagist).
- If something is wrong, do not move the tag. Fix forward with a patch release.

## Checklist

- [ ] Milestone has no open issues, CI is green
- [ ] CHANGELOG section `[X.Y.Z] - date` written, `[Unreleased]` is empty
- [ ] Release PR merged
- [ ] Annotated tag `vX.Y.Z` pushed
- [ ] GitHub Release exists with the CHANGELOG notes
- [ ] Milestone closed, next milestone exists
