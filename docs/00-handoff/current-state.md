# Current state

## Goal

Create a SaaS version of the existing forwarding CRM so it can serve any
forwarding company, not only the current internal company.

## User-provided locations

- Existing CRM code: `C:\OSPanel\home\v5.local`
- Knowledge and exchange folder: `C:\Sync\Yandex.Disk\Exchange`
- SaaS sync handoff folder: `C:\Sync\Yandex.Disk\Exchange\saas`
- GitHub repository: `https://github.com/tr0mb0zit76-bot/saas.git`

## What was verified in the cloud environment

- `/workspace` is the `saas` git repository cloned from GitHub.
- The remote points to `https://github.com/tr0mb0zit76-bot/saas`.
- This Cloud Agent runs in a remote Linux container, not on the owner's local
  Windows machine.
- The agent has access only to `/workspace` (the git clone), not to local
  Windows paths such as `C:\OSPanel\home\v5.local` or
  `C:\Sync\Yandex.Disk\Exchange`.
- Checked likely mount points such as `/mnt/c` and `/c`; they do not exist in
  this environment.

## Important limitation

The current CRM source code and knowledge base were not available for direct
analysis. The SaaS plan is therefore a blueprint and must be validated against
the real CRM code, database schema, business rules, and documents.

## Work completed in this branch

- Created the SaaS planning and development scaffold.
- Added architecture documents for multi-tenancy, modules, security, billing,
  operations, and migration discovery.
- Documented the external Yandex Disk sync folder at
  `C:\Sync\Yandex.Disk\Exchange\saas` (outside git).
- Added a stack-neutral local development environment outline.

## Next required input

Before writing application code, provide one of the following:

1. A mounted copy of `C:\OSPanel\home\v5.local` in the cloud environment.
2. A sanitized archive of the CRM source tree.
3. A schema-only or anonymized database dump.
4. Existing documentation from `C:\Sync\Yandex.Disk\Exchange`.

Secrets, production credentials, and personal data should be removed before
sharing source archives or database dumps.
