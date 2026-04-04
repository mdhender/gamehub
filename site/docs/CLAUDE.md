# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Hugo documentation site for "EC" (Epimethean Challenge), a play-by-mail game. Uses the [Hextra](https://github.com/imfing/hextra) theme via Hugo modules. Published to https://docs.damned.dev/.

## Commands

```sh
hugo server          # Local dev server at http://localhost:1313
hugo                 # Build site to public/
tools/server.sh      # Opens browser + runs hugo server
tools/deploy.sh      # Build and rsync to production
```

No npm/Node — this is a pure Hugo (Go) project.

## Architecture

**Theme:** Hextra, loaded via Hugo modules (`go.mod`). The `themes/` directory is intentionally empty.

**Content** follows the [Diátaxis framework](https://diataxis.fr/) and is organized by audience under `content/docs/`:
- `players/` — player-facing docs
- `referees/` — GM/operator docs
- `developers/` — coding agent docs

Each audience section has the same four Diátaxis sub-directories: `tutorials/`, `how-to/`, `reference/`, `explanation/`.

Top-level sections outside `docs/`: `history/` (preserved original game manuals), `reference/` (cross-cutting lookup tables), `blog/`.

**Custom shortcodes** live in `layouts/shortcodes/` (e.g., `discord-card.html`). Available Hextra shortcodes: `{{< callout >}}`, `{{< cards >}}`, `{{< card >}}`, `{{< tabs >}}`, `{{< tab >}}`.

**Agent skills** in `.agents/skills/`:
- `diataxis/` — use when writing or reviewing documentation
- `using-ff-v4/` — CLI flag/subcommand patterns for the EC Go CLI

## Conventions

- Front matter: YAML delimited by `---`
- Section landing pages: `_index.md`
- Leaf pages: kebab-case filenames (e.g., `first-turn.md`)

## Do Not Modify

- `go.mod` / `go.sum` — managed by Hugo modules
- `public/` — generated output, gitignored
- `themes/` — intentionally empty
- `history/` — preserve original source text faithfully; do not rewrite or modernize without explicit instruction
