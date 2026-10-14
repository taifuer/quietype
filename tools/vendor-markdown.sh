#!/usr/bin/env bash
# Refresh pinned runtime libraries; no npm/Composer installation on a live site.
set -euo pipefail
cd "$(dirname "$0")/.."
download_dir=$(mktemp -d /tmp/quietype-markdown-vendor.XXXXXX)
curl -fLsS https://codeload.github.com/michelf/php-markdown/tar.gz/refs/tags/2.0.0 -o "$download_dir/markdown.tgz"
curl -fLsS https://registry.npmjs.org/prismjs/-/prismjs-1.30.0.tgz -o "$download_dir/prism.tgz"
curl -fLsS https://registry.npmjs.org/katex/-/katex-0.19.0.tgz -o "$download_dir/katex.tgz"
printf '%s  %s\n' \
  e7cf927c583cdb5f7a2fd5f5d850fa8117fa1e4b62c2a6c62f557fdf877d6e6c "$download_dir/markdown.tgz" \
  ac16a9106a28c53b6a6313993c816a3a02525fdfefd7614fc1703df915a9fc11 "$download_dir/prism.tgz" \
  d8e49f2fea6eeed7cdae2cf4fd48e2f1d348758aa9e5740715e4533c2c96d1ee "$download_dir/katex.tgz" | sha256sum --check
mkdir -p "$download_dir/prism" "$download_dir/katex" assets/vendor/{php-markdown,prism,katex}
tar -xzf "$download_dir/markdown.tgz" -C "$download_dir"
tar -xzf "$download_dir/prism.tgz" -C "$download_dir/prism" --strip-components=1
tar -xzf "$download_dir/katex.tgz" -C "$download_dir/katex" --strip-components=1
cp "$download_dir/php-markdown-2.0.0/Michelf/"{MarkdownInterface,Markdown,MarkdownExtra}.php assets/vendor/php-markdown/
cp "$download_dir/php-markdown-2.0.0/License.md" assets/vendor/php-markdown/LICENSE.md
# Isolate the PHP namespace from plugins that bundle another Michelf version.
sed -i -e 's/^namespace Michelf;/namespace QuietypeVendor\\Michelf;/' -e 's/extends \\Michelf\\/extends \\QuietypeVendor\\Michelf\\/' assets/vendor/php-markdown/*.php
cp "$download_dir/prism/prism.js" "$download_dir/prism/LICENSE" assets/vendor/prism/
mkdir -p assets/vendor/prism/{components,plugins/autoloader,plugins/line-numbers}
cp "$download_dir/prism/components/"*.min.js assets/vendor/prism/components/
cp "$download_dir/prism/plugins/autoloader/prism-autoloader.min.js" assets/vendor/prism/plugins/autoloader/
cp "$download_dir/prism/plugins/line-numbers/prism-line-numbers.min.js" assets/vendor/prism/plugins/line-numbers/
cp "$download_dir/katex/dist/katex.min.js" "$download_dir/katex/dist/katex.min.css" "$download_dir/katex/LICENSE" assets/vendor/katex/
cp -R "$download_dir/katex/dist/fonts" assets/vendor/katex/
printf 'Vendored PHP Markdown 2.0.0, Prism 1.30.0, KaTeX 0.19.0. Sources: %s\n' "$download_dir"
