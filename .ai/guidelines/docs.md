# Documentation

- `docs/` documents api-core for the developers of applications, listed in `docs/00-index.md`. Read the documents of a feature before changing it.
- A change of behavior, config, routes or of a public class or method updates the documents that describe it, in the same commit. A change that applications, their data or their clients meet when upgrading also goes into `docs/getting-started/08-upgrading.md`.
- Write and check documents with the `writing-docs` skill.
- `resources/boost/` holds the guideline and the `api-core-development` skill, which Laravel Boost installs into applications. Keep them in line with `docs/`, citing documents by their installed path `vendor/andriichello/api-core/docs/…`.
