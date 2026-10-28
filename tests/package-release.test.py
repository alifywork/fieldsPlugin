"""Offline release packaging contracts; only writes disposable artifacts."""
import hashlib
import importlib.util
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location('release_builder', ROOT / 'tools/package-release.py')
BUILDER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(BUILDER)


class ReleaseContracts(unittest.TestCase):
    def test_determinism_and_contents(self):
        version, first, count = BUILDER.build(ROOT)
        self.assertEqual((version, first, count), BUILDER.build(ROOT))
        with tempfile.TemporaryDirectory() as output:
            self.run_builder(output)
            archive_path = Path(output) / 'alify-custom-field.zip'
            self.assertEqual(first, archive_path.read_bytes())
            expected = hashlib.sha256(first).hexdigest() + '  alify-custom-field.zip\n'
            self.assertEqual(expected, (Path(output) / 'alify-custom-field.zip.sha256').read_text())
            with zipfile.ZipFile(archive_path) as archive:
                self.assertIsNone(archive.testzip())
                self.assertEqual(count, len(archive.namelist()))
                self.assertIn('alify-custom-field/includes/class-alify-cf-woocommerce.php', archive.namelist())
                self.assertTrue(all(name.startswith('alify-custom-field/') for name in archive.namelist()))
                self.assertFalse(any('/tests/' in name or '/tools/' in name or '/node_modules/' in name or '/.git/' in name for name in archive.namelist()))
            archive_path.write_bytes(b'Existing release must not be silently replaced')
            self.run_builder(output, success=False)
            self.assertEqual(b'Existing release must not be silently replaced', archive_path.read_bytes())

    def test_rejects_source_symlinks_and_version_mismatch(self):
        with tempfile.TemporaryDirectory() as output:
            root = Path(output)
            for name in ('alify-custom-field.php', 'README.md', 'CHANGELOG.md', 'DEVELOPMENT-CONTRACTS.md', 'DEVELOPMENT-ROADMAP.md'):
                (root / name).write_bytes((ROOT / name).read_bytes())
            (root / 'includes').mkdir()
            (root / 'assets').mkdir()
            (root / 'includes/foreign.php').symlink_to(ROOT / 'alify-custom-field.php')
            with self.assertRaises(ValueError):
                BUILDER.build(root)
            (root / 'includes/foreign.php').unlink()
            (root / 'alify-custom-field.php').write_text('Version: 0.34.0\n')
            with self.assertRaises(ValueError):
                BUILDER.build(root)

    def run_builder(self, output, success=True):
        result = subprocess.run([sys.executable, str(ROOT / 'tools/package-release.py'), '--output', output], capture_output=True, text=True)
        self.assertEqual(0 if success else 2, result.returncode, result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()
