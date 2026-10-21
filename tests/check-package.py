"""Check install-tree and documentation invariants with the Python standard library."""
from pathlib import Path
import json
root = Path(__file__).resolve().parents[1]
package = root / "files/zc_plugins/BackInStockNotifications/v4.0.0"
assert list((root / "files").glob("*")) == [root / "files/zc_plugins"]
assert list((package.parent).iterdir()) == [package]
assert list((root / "files").rglob("manifest.php")) == [package / "manifest.php"]
for required in [
    "Installer/ScriptedInstaller.php", "filenames.php", "shared/Bisn.php",
    "admin/includes/functions/extra_functions/bisn_menu.php",
    "admin/includes/languages/english/extra_definitions/lang.bisn.php",
    "catalog/includes/classes/observers/auto.bisn.php",
]:
    assert (package / required).is_file(), required
manifest = (package / "manifest.php").read_text()
assert "'pluginVersion' => 'v4.0.0'" in manifest
assert "'zcVersions' => ['v210', 'v222']" in manifest
assert "'pluginId' => 0" in manifest
for page in [
    "account_back_in_stock_notifications",
    "back_in_stock_notification_subscribe",
    "back_in_stock_notification_unsubscribe",
]:
    assert (package / f"catalog/includes/modules/pages/{page}/header_php.php").is_file()
    assert (package / f"catalog/includes/templates/default/templates/tpl_{page}_default.php").is_file()
for path in (root / "files").rglob("*"):
    if path.is_file():
        assert path.suffix == ".php", path
        data = path.read_bytes()
        assert data.startswith(b"<?php"), path
        assert b"set_time_limit" not in data, path
        assert b"CeonXHTMLHiTemplate" not in data, path
for name in ["README.md", "CHANGELOG.md", "SECURITY.md", "docs/INSTALLATION.md", "docs/USER_GUIDE.md", "docs/TECHNICAL_NOTES.md", "docs/AUDIT.md"]:
    data = (root / name).read_text()
    assert data.isascii(), name
assert json.loads((root / "docs/legacy-files.json").read_text())
print("Package paths, single manifest/version, compatibility, documentation, and legacy inventory passed")
