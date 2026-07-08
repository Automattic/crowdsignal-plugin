# Triage Labels

The skills speak in terms of five canonical triage roles. This file maps those roles to the actual label strings used in this repo's issue tracker.

This repo uses a `type:` / `status:` label prefix convention, so the state roles are `status:`-prefixed to match. `wontfix` reuses the pre-existing `status: wontfix` label; the other four were created to fit the convention.

| Canonical role    | Label in our tracker      | Meaning                                  |
| ----------------- | ------------------------- | ---------------------------------------- |
| `needs-triage`    | `status: needs-triage`    | Maintainer needs to evaluate this issue  |
| `needs-info`      | `status: needs-info`      | Waiting on reporter for more information |
| `ready-for-agent` | `status: ready-for-agent` | Fully specified, ready for an AFK agent  |
| `ready-for-human` | `status: ready-for-human` | Requires human implementation            |
| `wontfix`         | `status: wontfix`         | Will not be actioned                     |

Category roles map to the existing `type:` labels: `bug` → `type: bug`, `enhancement` → `type: enhancement`.

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), use the corresponding label string from this table.

Edit the right-hand column to match whatever vocabulary you actually use.
