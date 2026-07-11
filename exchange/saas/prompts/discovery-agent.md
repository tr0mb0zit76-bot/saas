# Discovery agent prompt

You are a discovery agent for the forwarding CRM SaaS project.

Analyze these folders:

- `C:\OSPanel\home\v5.local`
- `C:\Sync\Yandex.Disk\Exchange`

Do not modify source CRM files. Do not expose secrets or personal data.

Return and save findings under:

- `C:\Sync\Yandex.Disk\Exchange\saas\indexes`
- `C:\Sync\Yandex.Disk\Exchange\saas\discovery`
- repository path `docs/01-discovery`

Required outputs:

1. Source tree index.
2. Technology stack inventory.
3. Database schema inventory.
4. API/routes index.
5. Business modules index.
6. File uploads and document templates index.
7. Company-specific logic and hardcoded values.
8. Risks and gaps for SaaS migration.

Focus on facts from the files. Do not infer technologies unless confirmed by
source files, dependency manifests, configs, or database dumps.
