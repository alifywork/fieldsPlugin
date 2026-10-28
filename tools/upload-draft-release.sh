#!/usr/bin/env bash
# Maintainer operation: attach assets only to an existing, tested draft. Never publish.
set -euo pipefail
: "${RELEASE_TAG:?Existing draft release tag required}"
: "${RUNNER_TEMP:?Run this operation in GitHub Actions}"
[[ "$RELEASE_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Invalid release tag.' >&2; exit 1; }
release=$(gh release view "$RELEASE_TAG" --json isDraft,targetCommitish,body,assets)
[[ $(jq -r '.isDraft' <<< "$release") == true ]] || { echo 'Refusing to alter a published release.' >&2; exit 1; }
target=$(jq -r '.targetCommitish' <<< "$release")
[[ "$target" =~ ^[0-9a-f]{40}$ ]] || { echo 'Draft must pin a full commit SHA.' >&2; exit 1; }
git merge-base --is-ancestor "$target" HEAD
checks=$(gh run list --commit "$target" --workflow tests.yml --limit 100 --json status,conclusion)
jq -e 'any(.[]; .status == "completed" and .conclusion == "success")' <<< "$checks" >/dev/null || { echo 'No successful Plugin tests run for the exact draft commit.' >&2; exit 1; }
work=$(mktemp -d "$RUNNER_TEMP/alify-release.XXXXXX")
trap 'rm -rf "$work"' EXIT
mkdir "$work/source" "$work/assets" "$work/verified"
git archive "$target" | tar -x -C "$work/source"
version=$(sed -n 's/^ \* Version: //p' "$work/source/alify-custom-field.php")
[[ "$RELEASE_TAG" == "v$version" ]] || { echo 'Tag/header mismatch.' >&2; exit 1; }
python3 "$work/source/tests/package-release.test.py"
python3 "$work/source/tools/package-release.py" --output "$work/assets"
(cd "$work/assets" && sha256sum --check alify-custom-field.zip.sha256)
for name in alify-custom-field.zip alify-custom-field.zip.sha256; do
    if jq -e --arg name "$name" 'any(.assets[]; .name == $name)' <<< "$release" >/dev/null; then
        gh release download "$RELEASE_TAG" --pattern "$name" --dir "$work/verified"
        cmp "$work/assets/$name" "$work/verified/$name" || { echo 'Existing asset differs; refusing replacement.' >&2; exit 1; }
    else
        gh release upload "$RELEASE_TAG" "$work/assets/$name"
    fi
done
# Re-download both assets: successful HTTP upload alone is not verification.
rm -f "$work/verified/alify-custom-field.zip" "$work/verified/alify-custom-field.zip.sha256"
gh release download "$RELEASE_TAG" --pattern alify-custom-field.zip --pattern alify-custom-field.zip.sha256 --dir "$work/verified"
(cd "$work/verified" && sha256sum --check alify-custom-field.zip.sha256)
cmp "$work/assets/alify-custom-field.zip" "$work/verified/alify-custom-field.zip"
[[ $(gh release view "$RELEASE_TAG" --json isDraft --jq .isDraft) == true ]] || { echo 'Release is no longer a draft.' >&2; exit 1; }
echo 'PASS: exact tested commit packaged, uploaded and re-downloaded with matching SHA-256. Release remains draft; staging/production deployment not performed.'
