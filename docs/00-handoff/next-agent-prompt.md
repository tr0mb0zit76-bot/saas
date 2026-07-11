# Next agent prompt

You are continuing the SaaS CRM project.

User context:

- Current CRM code is expected at `C:\OSPanel\home\v5.local`.
- Knowledge and exchange files are expected at `C:\Sync\Yandex.Disk\Exchange`.
- SaaS sync files should be maintained at
  `C:\Sync\Yandex.Disk\Exchange\saas`.
- GitHub repository is `https://github.com/tr0mb0zit76-bot/saas.git`.

Current repository state:

- The SaaS repo contains planning, architecture, discovery, migration, and
  development-environment scaffolding.
- The cloud environment used for the first pass could not access the Windows
  CRM folders.

Your task:

1. Make the CRM source and exchange folders available.
2. Analyze the real CRM source tree and knowledge files.
3. Fill the discovery documents under `docs/01-discovery`.
4. Fill sync indexes under `exchange/saas/indexes` or the real Windows
   `C:\Sync\Yandex.Disk\Exchange\saas\indexes`.
5. Validate or revise the SaaS architecture under `docs/02-architecture`.
6. Create ADRs for confirmed technology, multi-tenancy, migration, and storage
   decisions.
7. Only then begin implementation planning.

Rules:

- Do not expose secrets.
- Do not modify the original CRM source unless explicitly requested.
- Cite files and paths for every factual finding.
- Prefer repeatable scripts for indexing and migration.
