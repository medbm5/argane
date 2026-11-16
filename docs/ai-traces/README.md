# AI traces

Session notes from AI-assisted development (Claude Code, Claude Opus 5.5), one file per
session, named `YYYY-MM-DD-<slices>.md`.

| File | Covers |
|---|---|
| [2026-11-14-slice-00.md](2026-11-14-slice-00.md) | Repository bootstrap |
| [2026-11-14-slices-01-02.md](2026-11-14-slices-01-02.md) | Shared kernel, modules, first design system |
| [2026-11-15-slices-02-22.md](2026-11-15-slices-02-22.md) | Identity to delivery, including the mural redesign |
| [2026-11-16-after-launch.md](2026-11-16-after-launch.md) | Live testing fixes and owner feedback |

Every commit made with the assistant carries a `Co-Authored-By: Claude` trailer, so
`git log --grep "Co-Authored-By: Claude"` lists the AI-assisted work exactly.

## Submission archive

```sh
make ai-traces    # → argane-ai-traces.zip (this folder, as committed)
```

The archive is built with `git archive`, so it only contains committed files. The raw
Claude Code transcripts (`~/.claude/projects/…/*.jsonl`) are not included: they can contain
local paths and values read from git-ignored `.env.local` files. Review them before
sharing any.
