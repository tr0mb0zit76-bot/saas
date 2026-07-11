# External sync folder

## Location

```text
C:\Sync\Yandex.Disk\Exchange\saas
```

This folder is **not** part of the git repository. It is synchronized through
Yandex Disk and is used for exchanging indexes, handoff notes, and knowledge
base material between tools such as Obsidian and Hivemind.

## Recommended structure

```text
C:\Sync\Yandex.Disk\Exchange\saas\
├─ handoff/
│  ├─ current-state.md
│  ├─ next-agent-prompt.md
│  ├─ open-questions.md
│  └─ decisions-log.md
├─ indexes/
│  ├─ source-tree-index.md
│  ├─ docs-index.md
│  ├─ database-index.md
│  ├─ api-index.md
│  ├─ files-and-uploads-index.md
│  └─ business-rules-index.md
├─ discovery/
│  ├─ current-crm-technology.md
│  ├─ current-crm-modules.md
│  └─ current-crm-risks.md
├─ architecture/
│  └─ saas-blueprint.md
├─ migration/
│  └─ entity-mapping.md
└─ prompts/
   ├─ discovery-agent.md
   └─ architecture-agent.md
```

## Relationship to git

| Location | Purpose |
| --- | --- |
| `https://github.com/tr0mb0zit76-bot/saas.git` | Code, architecture docs, infra, scripts |
| `C:\Sync\Yandex.Disk\Exchange\saas` | External knowledge exchange and handoff |

Canonical architecture and product documents live in the git repository under
`docs/`. The Yandex Disk folder is for working notes, generated indexes, and
cross-tool handoff.
