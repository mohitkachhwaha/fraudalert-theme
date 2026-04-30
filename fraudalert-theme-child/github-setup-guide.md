# GitHub Release Setup Guide

Follow this guide to manage updates for your FraudAlert theme and mu-plugins using the custom updater system.

## 1. Repository Structure
Ensure your GitHub repository follows this structure:

```text
fraudalert-theme/
├── child-theme/            # Your child theme source files
│   ├── style.css
│   ├── functions.php
│   ├── ... (all other theme files)
├── mu-plugins/             # Your MU plugins source files
│   ├── ps-cpt.php
│   ├── ps-ad-manager.php
│   └── ps-updater.php
├── child-theme.zip         # Generated via script
├── mu-plugins.zip          # Generated via script
└── version.json            # Update manifest
```

---

## 2. Release Workflow

Whenever you make changes and want to push an update to your users:

1.  **Commit Changes**: Update your PHP, CSS, or JS files in the `child-theme/` or `mu-plugins/` folders.
2.  **Generate Release Packages**: Run the `generate-release.sh` script (provided below) to create fresh ZIP files and get their SHA256 checksums.
3.  **Update `version.json`**:
    *   Increment the `"version"` number (e.g., `1.1.0` -> `1.1.1`).
    *   Update the `"release_date"`.
    *   Update the `"changelog"` array.
    *   Paste the new `"checksums"` from the script output.
4.  **Push to GitHub**: Commit all files, including the new ZIPs and `version.json`, and push to your `main` branch.

---

## 3. Helper Script (`generate-release.sh`)

Save this code as `generate-release.sh` in your repository root. Use Git Bash or a similar terminal to run it.

```bash
#!/bin/bash

# Configuration
THEME_DIR="child-theme"
MU_DIR="mu-plugins"
THEME_ZIP="child-theme.zip"
MU_ZIP="mu-plugins.zip"

echo "--- Generating Release Packages ---"

# Remove old zips
rm -f "$THEME_ZIP" "$MU_ZIP"

# Create Theme Zip
if [ -d "$THEME_DIR" ]; then
    echo "Zipping $THEME_DIR..."
    # Zip the contents of the directory, not the directory itself
    cd "$THEME_DIR" && zip -r "../$THEME_ZIP" . && cd ..
else
    echo "Error: $THEME_DIR not found!"
fi

# Create MU-Plugins Zip
if [ -d "$MU_DIR" ]; then
    echo "Zipping $MU_DIR..."
    cd "$MU_DIR" && zip -r "../$MU_ZIP" . && cd ..
else
    echo "Error: $MU_DIR not found!"
fi

echo ""
echo "--- SHA256 Checksums ---"
echo "Copy these to your version.json file:"
echo ""

if [ -f "$THEME_ZIP" ]; then
    THEME_HASH=$(sha256sum "$THEME_ZIP" | awk '{print $1}')
    echo "Theme Checksum: $THEME_HASH"
fi

if [ -f "$MU_ZIP" ]; then
    MU_HASH=$(sha256sum "$MU_ZIP" | awk '{print $1}')
    echo "MU-Plugins Checksum: $MU_HASH"
fi

echo ""
echo "Done! Remember to update version.json and push to GitHub."
```

---

## 4. `version.json` Example

```json
{
  "version": "1.1.0",
  "release_date": "2025-04-30",
  "changelog": [
    "Ad Manager conditions added",
    "Photo Story cache improved",
    "Security hardening"
  ],
  "min_wp": "6.0",
  "min_php": "7.4",
  "files": {
    "theme": "https://raw.githubusercontent.com/YOUR_USERNAME/YOUR_REPO/main/child-theme.zip",
    "mu_plugins": "https://raw.githubusercontent.com/YOUR_USERNAME/YOUR_REPO/main/mu-plugins.zip"
  },
  "checksums": {
    "theme": "PASTE_THEME_CHECKSUM_HERE",
    "mu_plugins": "PASTE_MU_PLUGINS_CHECKSUM_HERE"
  }
}
```
