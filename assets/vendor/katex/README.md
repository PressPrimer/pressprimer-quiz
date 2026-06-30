# KaTeX (vendored)

Locally bundled copy of [KaTeX](https://katex.org/) used to render LaTeX math
notation in quizzes. Loaded only when math notation is enabled
(Settings → General) **and** the rendered content contains math delimiters.

- **Version:** 0.17.0
- **Upstream:** https://github.com/KaTeX/KaTeX (npm package `katex@0.17.0`)
- **License:** MIT (KaTeX is MIT licensed)
- **Served locally** — no CDN / external request (so nothing is sent off-site and
  no External Services disclosure is required).

## Files included

```
katex.min.js                 # core renderer
katex.min.css                # styles (references fonts/ relatively)
contrib/auto-render.min.js   # delimiter auto-render extension
fonts/                       # woff2 + woff only (legacy .ttf omitted to save size)
```

Only `.woff2` and `.woff` font formats are shipped. The unmodified `katex.min.css`
also lists `.ttf` in each `@font-face src`, but browsers select the first
supported format, so modern browsers use woff2 and the long tail uses woff; the
`.ttf` entries are never requested in practice.

## Updating

KaTeX is a `devDependency` in the plugin's `package.json` (so the version is
reproducible). To update:

```
npm install --save-dev katex@<version>
# then copy from node_modules/katex/dist into this folder:
#   katex.min.js, katex.min.css, contrib/auto-render.min.js, fonts/*.woff2, fonts/*.woff
```

Update the version above and the `readme.txt` minified-assets disclosure.
