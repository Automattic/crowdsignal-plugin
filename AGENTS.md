# AGENTS.md

Guidance for AI agents working in this repository.

## Agent skills

### Issue tracker

Issues live in this repo's GitHub Issues (via the `gh` CLI). External pull requests are a triage surface — `/triage` pulls them into the same queue as issues. See `docs/agents/issue-tracker.md`.

### Triage labels

Five canonical triage roles map to `status:`-prefixed labels (`status: needs-triage`, `status: needs-info`, `status: ready-for-agent`, `status: ready-for-human`, `status: wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `CONTEXT.md` + `docs/adr/` at the repo root (neither created yet). See `docs/agents/domain.md`.
