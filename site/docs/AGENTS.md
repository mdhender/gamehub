# AGENTS.md

## Project Overview

This is a Hugo documentation site for "EC" (Epimethean Challenge), a play-by-mail game.
The repository is [`mdhender/gamehub`](https://github.com/mdhender/gamehub).
It uses the [Hextra](https://github.com/imfing/hextra) theme via Hugo modules and is published to https://docs.damned.dev/.

## Tech Stack

- **Hugo** static site generator (Go-based, uses Hugo modules)
- **Hextra theme** — imported as a Hugo module (`github.com/imfing/hextra`)
- **Content format**: Markdown with YAML front matter
- **Configuration**: `hugo.yaml`

## Content Structure

Content follows the [Diátaxis framework](https://diataxis.fr/) and is organized by audience:

```
content/
├── docs/
│   ├── players/        # Player-facing documentation
│   │   ├── tutorials/  # Learning-oriented (Diátaxis: tutorial)
│   │   ├── how-to/     # Task-oriented (Diátaxis: how-to)
│   │   ├── reference/  # Information-oriented (Diátaxis: reference)
│   │   └── explanation/# Understanding-oriented (Diátaxis: explanation)
│   ├── referees/       # GM/operator documentation (same sub-structure)
│   └── developers/     # Developer/coding-agent documentation (same sub-structure)
├── history/            # Preserved historical game manuals (1978, 1980, 1994)
├── reference/          # Cross-cutting quick-lookup pages
└── blog/               # Notes / blog posts
```

## Conventions

- All content pages use YAML front matter delimited by `---`.
- Section landing pages are named `_index.md`.
- Leaf pages are named with kebab-case (e.g., `first-turn.md`).
- Use Hextra shortcodes: `{{< callout >}}`, `{{< cards >}}`, `{{< card >}}`, `{{< tabs >}}`, `{{< tab >}}`.
- Historical content under `history/` should stay faithful to the original source text.
- Use the Diátaxis skill when writing or reviewing documentation.

## Build & Preview

```sh
hugo server       # Local dev server (http://localhost:1313)
hugo              # Build to public/
tools/deploy.sh   # deploy to production
```

## Do Not Modify

- `go.mod` / `go.sum` — managed by Hugo modules; do not edit manually.
- `public/` — generated output; gitignored.
- `themes/` — empty; theme is loaded via Hugo modules.
- Files under `history/` should not be rewritten or modernized without explicit instruction.
