---
name: writing-docs
description: Write, change and check api-core's documentation in docs/ and the AI resources in resources/boost/. Use when adding, editing, moving or renaming a document, when a code change alters behavior, config, routes, classes or methods that the documents describe, and when changing the guideline or the skill that applications get through Laravel Boost.
---

# Writing docs

## Layout

- `docs/` has a folder per area (`auth`, `files`, `http/controllers`, …). `docs/00-index.md` lists every document with
  a one-line summary, and `docs/getting-started/00-overview.md` maps features to their first documents.
- Documents are named `NN-kebab-case-topic.md`, numbered from `00` in each folder without gaps. Renumbering or renaming
  one means fixing every link to it, in `docs/`, `README.md` and `resources/boost/`.
- Add a new document to `docs/00-index.md` under its area, and to the feature map, when it starts a feature.

## A document

- One topic per document, about 40–150 lines; split a longer one.
- `# Title` on the first line, then a short paragraph of what the document covers and for whom.
- `##` sections, and a `## Related` section last, with a bulleted list of links to related documents.
- Links are relative (`../files/00-overview.md`), with an anchor for a section (`03-middleware.md#order`).

## Style

- Match the voice of the sibling documents: plain statements of how api-core behaves, in American spelling.
- Wrap prose at 120 characters. Tables and code blocks may run longer.
- Name things exactly as the code does: `Core\…` classes, `Class::method()`, `api-core.*` config keys, env variables,
  routes (`POST auth/login`), status codes and messages (`403`, `Not found.`).
- Examples use made-up application models (`Invoice`, `Project`, `Client`, `App\…`) and never name a real
  application.
- Take responses, errors and outputs from real runs (a test or `php8.5 artisan tinker`), not from memory.

## Code changes

- Update every document that describes the changed behavior: search `docs/` for the class, method, config key and
  route.
- Describe what an application meets on upgrading (a migration to publish, a new error, changed data or responses) in
  `docs/getting-started/08-upgrading.md`, under its section.
- A new or renamed config key also goes into `docs/getting-started/03-configuration.md`.

## Boost resources

`resources/boost/guidelines/core.md` (always loaded in applications) and
`resources/boost/skills/api-core-development/SKILL.md` (loaded when relevant) reach applications through
`php artisan boost:install` or `boost:update`.

- Cite documents by their installed path, `vendor/andriichello/api-core/docs/…`, since an application has no `docs/`
  of its own, and without anchors.
- Keep the guideline short: what api-core is, where its documents are, and the rules that prevent silent mistakes.
  Details belong in the skill's checklist, task table and traps.
- Add a row to the skill's table for a new feature, and fix its paths when documents move.

## Checks

Run both from the repository's root after changing documents or AI resources:

```bash
php8.5 .ai/skills/writing-docs/scripts/check-docs.php
php8.5 artisan test --compact tests/Unit/Docs/GuidelinesAndSkillsTest.php
```

`check-docs.php` reports `file:line: problem` and exits with 1, when it finds any:

- file names, which don't match `NN-kebab-case.md`, and numbering with gaps or duplicates;
- documents missing from `docs/00-index.md`, a first line without `# Title`, and a missing `## Related`;
- prose lines over 120 characters;
- links to missing files or missing anchors;
- mentions of a real application;
- `api-core.*` config keys, `Core\` classes and namespaces, `Class::method()` calls and `Class::CONSTANT`s of api-core,
  which don't exist.

The test checks that the guidelines and skills cite only existing documents, and that the package's ones cite them by
their installed path.
