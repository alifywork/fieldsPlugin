# v0.34.0 release operations — 2026-10-29

## Published assets

- Stable public release: https://github.com/alifywork/fieldsPlugin/releases/tag/v0.34.0
- Release source: `5eca2e8096e1d637726860bf0db34d8668d9b329`.
- Installable archive: `alify-custom-field.zip`, 247,088 bytes, 66 runtime/documentation files.
- SHA-256: `620d69b165127c7b0534da73ad87c09802c6254f52fabe04a62d2b32c87ddec1`.
- The `.zip.sha256` sidecar is attached alongside the archive. GitHub asset metadata reports the same archive digest.
- Release is neither draft nor prerelease. No production website has been changed.

## Verification performed

1. Exact release commit passed all five Plugin tests jobs: https://github.com/alifywork/fieldsPlugin/actions/runs/40778571520 .
2. Direct sandbox uploads failed with EOF. The new draft-asset workflow built the exact already-tested commit, attached both assets, re-downloaded them and verified matching checksums: https://github.com/alifywork/fieldsPlugin/actions/runs/40779283280 . It did not publish the release.
3. Release-operations branch passed all five Plugin tests jobs: https://github.com/alifywork/fieldsPlugin/actions/runs/40779282622 .
4. User selected a **temporary isolated test site**, not an existing staging/production site. Fresh WordPress 6.9 core and a new SQLite database were prepared. The checked ZIP was extracted as a normal plugin directory (not a source-checkout symlink) and activated under PHP 8.3.33.
5. The actual packaged plugin passed **1,111 real WordPress backend assertions**, with an empty WordPress debug log. This exercised saved metadata, validation, permissions, REST/schema and other existing integration contracts. It was not a browser inspection of a production theme or production dataset.
6. After those checks, the maintainer operation published v0.34.0. The installed packaged updater parser accepted the real published GitHub release metadata and canonical stable ZIP URL. This was metadata validation, not an unattended upgrade of a live website.

## Operational boundaries

- Production deployment still requires a destination, authorized access, backups and a production-specific rollout plan. No hosting or production-site access has been supplied.
- Temporary staging used SQLite; authoritative WooCommerce HPOS/MySQL/provider contracts are covered separately by the linked native CI. No broader hosting, multisite, security-audit or performance certification is claimed.
- Published artifacts remain pinned to the tested source commit; later release-operation documentation/workflow changes are not silently substituted into those artifacts.
- The asset workflow only accepts an existing draft with a full ancestor commit SHA and a successful exact-commit Plugin tests run. It refuses different existing assets and never publishes. Publication remains a separate explicit maintainer action.
