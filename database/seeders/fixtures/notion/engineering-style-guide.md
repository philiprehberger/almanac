# Engineering Style Guide

## Code style

- TypeScript strict mode on. No `any` without a `// reason:` comment.
- 100-character line max, enforced by Prettier + ESLint flat config.
- Server components by default; `"use client"` only when interactivity demands it.

## Commits

- Conventional commits encouraged but not enforced.
- Squash on merge. The PR title becomes the commit subject.

## PR review

- Two approvals for changes to production code paths. One approval for tests, docs, and infra.
- PR description must explain *why*. "What" is usually visible in the diff.

## Branching

- Trunk-based: feature branches are short-lived; main is always deployable.
- Long-running branches require an active discussion thread and a rebase plan.
