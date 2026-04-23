# Skill Registry

**Delegator use only.** Any agent that launches sub-agents reads this registry to resolve compact rules, then injects them directly into sub-agent prompts. Sub-agents do NOT read this registry or individual SKILL.md files.

## User Skills

| Trigger | Skill | Path |
|---------|-------|------|
| When creating a pull request, opening a PR, or preparing changes for review | branch-pr | c:\Users\jhony\.copilot\skills\branch-pr\SKILL.md |
| When creating a GitHub issue, reporting a bug, or requesting a feature | issue-creation | c:\Users\jhony\.copilot\skills\issue-creation\SKILL.md |
| When user says "judgment day", "review adversarial", "dual review", "doble review", "juzgar", "que lo juzguen" | judgment-day | c:\Users\jhony\.copilot\skills\judgment-day\SKILL.md |
| When user asks to create a new skill, add agent instructions, or document patterns for AI | skill-creator | c:\Users\jhony\.copilot\skills\skill-creator\SKILL.md |

## Compact Rules

Pre-digested rules per skill. Delegators copy matching blocks into sub-agent prompts as `## Project Standards (auto-resolved)`.

### branch-pr
- Every PR MUST link an approved issue — no exceptions
- Every PR MUST have exactly one `type:*` label
- Branch names: `^(feat|fix|chore|docs|style|refactor|perf|test|build|ci|revert)\/[a-z0-9._-]+$`
- Use conventional commits for all commit messages
- Automated checks must pass before merge
- Blank PRs without issue linkage will be blocked by GitHub Actions

### issue-creation
- MUST use a template (bug report or feature request) — blank issues disabled
- Every issue gets `status:needs-review` automatically on creation
- A maintainer MUST add `status:approved` before any PR can be opened
- Questions go to Discussions, not issues
- Always search existing issues for duplicates before creating

### judgment-day
- Launch TWO blind sub-agents in parallel — neither knows about the other
- Orchestrator synthesizes: Confirmed (both found) → fix immediately; Suspect (one found) → triage; Contradiction → flag manual
- Warnings classified: real (fix) vs theoretical (report as INFO, don't block)
- If confirmed CRITICALs or real WARNINGs exist → delegate Fix Agent → re-judge (max 2 iterations)
- Resolve skills from registry and inject into BOTH judge prompts before launch

### skill-creator
- Create skills only for repeated patterns, project-specific conventions, or complex workflows
- Don't create skills for trivial or one-off tasks
- Structure: `skills/{name}/SKILL.md` + optional `assets/` and `references/`
- Frontmatter required: name, description (with Trigger:), license, metadata
- Compact rules (5-15 lines) are the most important output — concise, actionable

## Project Conventions

| File | Path | Notes |
|------|------|-------|
| AGENTS.md | AGENTS.md | Index — references files below |
| CONTEXTO_IA.md | Documentación/CONTEXTO_IA.md | Referenced by AGENTS.md |
| BACKLOG_FUNCIONAL_TECNICO.md | Documentación/BACKLOG_FUNCIONAL_TECNICO.md | Referenced by AGENTS.md |
| GUIA_TECNICA_DING_CONNECT.md | Documentación/GUIA_TECNICA_DING_CONNECT.md | Referenced by AGENTS.md |

Read the convention files listed above for project-specific patterns and rules. All referenced paths have been extracted — no need to read index files to discover more.
