#!/bin/bash

# ===================================================
# FraudAlert — Release Package Generator
# Run this BEFORE pushing updates to GitHub
# ===================================================

# Folder names (must match your GitHub repo structure)
THEME_DIR="fraudalert-theme-child"
MU_DIR="mu-plugins"

# Output zip names
THEME_ZIP="child-theme.zip"
MU_ZIP="mu-plugins.zip"

echo ""
echo "=== FraudAlert Release Generator ==="
echo ""

# Remove old zips
rm -f "$THEME_ZIP" "$MU_ZIP"

# --- Theme Zip ---
# Zip WITH folder name so it extracts as themes/fraudalert-theme-child/
if [ -d "$THEME_DIR" ]; then
    echo "[1/2] Zipping $THEME_DIR..."
    zip -r "$THEME_ZIP" "$THEME_DIR/"
    echo "      ✅ $THEME_ZIP created"
else
    echo "      ❌ ERROR: $THEME_DIR folder not found!"
    exit 1
fi

# --- MU-Plugins Zip ---
# Zip WITHOUT folder name so files extract directly into mu-plugins/
if [ -d "$MU_DIR" ]; then
    echo "[2/2] Zipping $MU_DIR..."
    cd "$MU_DIR" && zip -r "../$MU_ZIP" . && cd ..
    echo "      ✅ $MU_ZIP created"
else
    echo "      ❌ ERROR: $MU_DIR folder not found!"
    exit 1
fi

echo ""
echo "=== SHA256 Checksums ==="
echo "Copy these into version.json:"
echo ""

if [ -f "$THEME_ZIP" ]; then
    THEME_HASH=$(sha256sum "$THEME_ZIP" | awk '{print $1}')
    echo "Theme:      $THEME_HASH"
fi

if [ -f "$MU_ZIP" ]; then
    MU_HASH=$(sha256sum "$MU_ZIP" | awk '{print $1}')
    echo "MU-Plugins: $MU_HASH"
fi

echo ""
echo "=== Next Steps ==="
echo "1. Open version.json"
echo "2. Update version number"
echo "3. Paste checksums above"
echo "4. git add . && git commit -m 'Release vX.X.X' && git push"
echo ""
echo "Done! ✅"
