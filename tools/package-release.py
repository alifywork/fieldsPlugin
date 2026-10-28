#!/usr/bin/env python3
"""Build a deterministic installable archive; does not publish or contact GitHub."""
import argparse
import hashlib
import io
from pathlib import Path
import re
import zipfile


def build(root):
    header = (root / 'alify-custom-field.php').read_text()
    version = re.search(r'Version:\s*(\d+\.\d+\.\d+)\s', header)
    constant = re.search(r"define\('ALIFY_CF_VERSION',\s*'(\d+\.\d+\.\d+)'\)", header)
    if not version or not constant or version[1] != constant[1]:
        raise ValueError('Plugin header and runtime versions must match.')
    files = [root / name for name in ('alify-custom-field.php', 'README.md', 'CHANGELOG.md', 'DEVELOPMENT-CONTRACTS.md', 'DEVELOPMENT-ROADMAP.md')]
    for directory in ('includes', 'assets'):
        files.extend(path for path in (root / directory).rglob('*') if path.is_file() or path.is_symlink())
    output = io.BytesIO()
    with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(files):
            if path.is_symlink() or not path.is_file() or not path.resolve().is_relative_to(root.resolve()):
                raise ValueError('Only regular in-repository release files are allowed.')
            name = path.relative_to(root).as_posix()
            if any(part.startswith('.') for part in Path(name).parts):
                raise ValueError('Hidden files are not release assets.')
            info = zipfile.ZipInfo('alify-custom-field/' + name, date_time=(1980, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, path.read_bytes(), compresslevel=9)
    return version[1], output.getvalue(), len(files)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path, help='Explicit artifact directory, preferably outside the checkout.')
    parser.add_argument('--force', action='store_true', help='Explicitly replace an existing different archive.')
    args = parser.parse_args()
    root = Path(__file__).resolve().parent.parent
    version, data, count = build(root)
    args.output.mkdir(parents=True, exist_ok=True)
    package = args.output / 'alify-custom-field.zip'
    if package.is_symlink() or (package.exists() and package.read_bytes() != data and not args.force):
        parser.error('Existing package differs or is a symlink; choose a new directory or explicitly --force a regular file.')
    checksum = args.output / 'alify-custom-field.zip.sha256'
    if checksum.is_symlink():
        parser.error('Checksum output must not be a symlink.')
    package.write_bytes(data)
    digest = hashlib.sha256(data).hexdigest()
    checksum.write_text(digest + '  alify-custom-field.zip\n')
    with zipfile.ZipFile(package) as archive:
        if archive.testzip() is not None or 'alify-custom-field/alify-custom-field.php' not in archive.namelist():
            raise ValueError('Archive integrity/root check failed.')
    print(f'PASS: {version}; {count} packaged files; {len(data)} bytes; SHA-256 {digest}; {package}')


if __name__ == '__main__':
    main()
