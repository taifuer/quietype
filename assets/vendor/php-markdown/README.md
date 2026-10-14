# Markdown runtime dependencies

Pinned distributions used by Quietype's optional Markdown module:

| Library | Upstream distribution | SHA-256 | License |
| --- | --- | --- | --- |
| PHP Markdown 2.0.0 | https://codeload.github.com/michelf/php-markdown/tar.gz/refs/tags/2.0.0 | `e7cf927c583cdb5f7a2fd5f5d850fa8117fa1e4b62c2a6c62f557fdf877d6e6c` | BSD-3-Clause |
| Prism 1.30.0 | https://registry.npmjs.org/prismjs/-/prismjs-1.30.0.tgz | `ac16a9106a28c53b6a6313993c816a3a02525fdfefd7614fc1703df915a9fc11` | MIT |
| KaTeX 0.19.0 | https://registry.npmjs.org/katex/-/katex-0.19.0.tgz | `d8e49f2fea6eeed7cdae2cf4fd48e2f1d348758aa9e5740715e4533c2c96d1ee` | MIT |

`tools/vendor-markdown.sh` downloads and verifies these archives, copies runtime files/licenses, and mechanically changes the PHP namespace and fully qualified parent reference from `Michelf` to `QuietypeVendor\Michelf` to avoid plugin collisions. Theme dialect extensions are outside the vendor code in `inc/markdown-parser.php`. Prism languages are loaded from the local components directory; KaTeX fonts are local. The editor reuses WordPress's bundled CodeMirror instead of shipping another editor or fetching remote resources.
