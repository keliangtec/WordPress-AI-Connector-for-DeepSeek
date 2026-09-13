#!/usr/bin/env bash
set -euo pipefail

readonly plugin_slug='ai-connector-for-deepseek-guducat-ver'
readonly main_file="${plugin_slug}.php"
readonly output_dir="${1:-dist}"
tag="${GITHUB_REF_NAME:-${2:-}}"

if [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Release tag must match vX.Y.Z: ${tag:-<empty>}" >&2
  exit 1
fi

version="${tag#v}"
header_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$main_file" | head -n 1)"
constant_version="$(sed -n "s/.*DEEPSEEK_AI_PROVIDER_VERSION', '\([^']*\)'.*/\1/p" "$main_file" | head -n 1)"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -n 1)"

[[ "$header_version" == "$version" ]] || { echo 'Plugin header version mismatch' >&2; exit 1; }
[[ "$constant_version" == "$version" ]] || { echo 'Version constant mismatch' >&2; exit 1; }
[[ "$stable_tag" == "$version" ]] || { echo 'Stable tag mismatch' >&2; exit 1; }

rm -rf "$output_dir"
mkdir -p "$output_dir/$plugin_slug"

copy_file() { mkdir -p "$output_dir/$plugin_slug/$(dirname "$1")"; cp "$1" "$output_dir/$plugin_slug/$1"; }
copy_file "$main_file"
copy_file README.md
copy_file README.zh-CN.md
copy_file readme.txt
copy_file NOTICE.md
copy_file license.txt
copy_file uninstall.php
copy_file index.php
copy_file assets/images/deepseek.svg
find src -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do copy_file "$file"; done

(cd "$output_dir" && zip -qr "${plugin_slug}-v${version}.zip" "$plugin_slug")
sha256sum "$output_dir/${plugin_slug}-v${version}.zip" > "$output_dir/${plugin_slug}-v${version}.zip.sha256"
echo "$output_dir/${plugin_slug}-v${version}.zip"
