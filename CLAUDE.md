# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **specification-driven development toolkit** (SpecKit) that provides a structured workflow for feature development through Claude Code skills. The toolkit guides development from initial feature description through specification, planning, task breakdown, and implementation.

## Core Technologies

- **Language**: PHP 8.4
- **Framework**: Symfony 8.0
- **Database**: MariaDB with Doctrine ORM
- **Testing**: PHPUnit for unit tests
- **Architecture**: Single Sign On – HTTP REST API

## Coding Style

### Class Modifiers

- Service classes should be `final readonly class` when possible with the interface to be used for mocks in unit tests
- Repository implementations should be `final readonly class Doctrine*Repository implements *Repository`
- Request/DTO classes should be `final readonly class`
- Controllers should be `final class`
- Test classes should be `final class` extending `TestCase`

### Naming Conventions

- Class Interfaces: `*Interface` suffix (e.g., `CountInterface`)
- Repository implementations: `Doctrine*Repository` (e.g., `DoctrineUserRepository implements UserRepositoryInterface`)
- Request objects: `*Request` or `*Request` (e.g., `TokenRequest`)
- DTOs: `*DTO` (e.g., `CancelPaymentIntentDTO`)
- Enums: Use backed enums with string values (e.g., `enum PaymentType: string`)
- Test classes and paths: `*Test` (e.g., `UserRepositoryTest`) and place them in `tests/` directory following the same structure as the source code
- Test namespace: Use `Test\` prefix (e.g., `Test\App\Metrics`)

### Strict Typing

- Always add `declare(strict_types=1);` at the top of PHP files
- Exception: Interfaces should NOT use `declare(strict_types=1);`
- Always use explicit return type declarations for methods
- Use type hints for all method parameters and properties, for arrays use annotations to explicitly declare the type

### Error Handling

- Prefer specific exception types (e.g., `EntityNotFoundException`, `RefundDuplicatedException`)
- Catch `\Throwable` only for unexpected errors; handle or rethrow with context
- Log errors with relevant context (e.g., userId) and avoid PII disclosure
- Emit failure domain events when applicable (e.g., `UserRegistrationFailedEvent`)

### Logging

- Use PSR-3
- Log level guidance: warning for recoverable domain issues, error for failures, critical for data loss
- Include correlation identifiers; avoid PII disclosure in log messages

### Repository Pattern

- Define repository interfaces (e.g., `UserRepositoryInterface`)
- Implementations: `final readonly class Doctrine*Repository implements *RepositoryInterface`
- Use `find*` for nullable returns (`?Entity`)
- Use `get*` for guaranteed returns (`Entity`) and throw `EntityNotFoundException` if not found
- Support optional `bool $lock = false` parameter for pessimistic locking

### Testing

- Place tests in `tests/` mirroring `src/` structure
- Use the `Test\` namespace prefix (e.g., `Test\App\Metrics`)
- Test methods should use the `test*` prefix (e.g., `testIncrementCallsStatsdWithCorrectParameters`)

## Architecture

- Keep controllers thin; move business logic into services

## Implementation Guidance

- Prefer SOLID principles and clean architecture
- Keep services stateless and dependency-injected
- Use Doctrine ORM and QueryBuilder instead of raw SQL when possible
- Validate inputs with Symfony Validation; add custom validators when needed
- Ensure explicit types throughout; avoid `mixed`/`object`-like patterns
- Match PSR-12 formatting; keep code readable with descriptive names

## File Scaffolding Checklist (per class type)

- Service/Handler/DTO/Request: `final readonly class`, strict types, explicit return types
- Repository interface: no `declare(strict_types=1);`, clear `find*`/`get*` split
- Doctrine repository implementation: `final readonly class Doctrine*Repository implements *RepositoryInterface`
- Controller: `final class`, remain stateless; delegate to services
- Test: `final class` extending `TestCase`, methods prefixed with `test*`

## Core Workflow

The development process follows this sequence of skills:

1. **`/speckit.specify`** - Create feature specification from natural language description
2. **`/speckit.clarify`** (optional) - Resolve ambiguities in the specification
3. **`/speckit.plan`** - Generate technical implementation plan
4. **`/speckit.tasks`** - Break down plan into actionable tasks
5. **`/speckit.analyze`** (optional) - Cross-artifact consistency analysis
6. **`/speckit.implement`** - Execute the implementation

Additional skills:
- **`/speckit.checklist`** - Generate domain-specific validation checklists
- **`/speckit.constitution`** - Create/update project principles
- **`/speckit.taskstoissues`** - Convert tasks to GitHub issues

## Architecture

### Directory Structure

```
.specify/
├── memory/
│   └── constitution.md          # Project principles and standards
├── scripts/bash/
│   ├── create-new-feature.sh    # Initialize feature branch/directories
│   ├── setup-plan.sh            # Setup planning phase
│   ├── check-prerequisites.sh   # Validate workflow state
│   └── update-agent-context.sh  # Update agent context files
└── templates/
    ├── spec-template.md         # Feature specification structure
    ├── plan-template.md         # Implementation plan structure
    ├── tasks-template.md        # Task breakdown structure
    ├── checklist-template.md    # Checklist structure
    └── agent-file-template.md   # Agent context structure

.claude/commands/
└── speckit.*.md                 # Skill definitions (workflow instructions)

specs/
└── [###-feature-name]/          # Generated per-feature directories
    ├── spec.md                  # Feature specification
    ├── plan.md                  # Technical implementation plan
    ├── tasks.md                 # Actionable task list
    ├── research.md              # Technical research findings
    ├── data-model.md            # Entity definitions
    ├── quickstart.md            # Integration scenarios
    ├── checklists/              # Domain-specific validation
    └── contracts/               # API contracts (OpenAPI/GraphQL)
```

### Key Concepts

**Feature Branching**: Each feature gets a numbered branch (`###-short-name`) and corresponding directory under `specs/`. The workflow scripts ensure consistent numbering across branches and specs.

**User Story Organization**: Specifications use **prioritized, independently testable user stories** (P1, P2, P3...). Each story should be a vertical slice that can be developed, tested, and deployed independently. This enables MVP-first development.

**Phased Task Execution**: Tasks are organized into phases:
1. Setup (project initialization)
2. Foundational (blocking prerequisites for all stories)
3. User Story phases (one per story, in priority order)
4. Polish (cross-cutting concerns)

**Template-Driven Generation**: All artifacts (specs, plans, tasks) follow strict templates that preserve structure while filling in feature-specific details.

**JSON-Mode Scripts**: Helper scripts use `--json` flag to output structured data that can be parsed programmatically. Always capture and parse this output to get correct file paths.

## Important Conventions

### Script Invocation

**Always use JSON mode** for scripts:
```bash
.specify/scripts/bash/create-new-feature.sh --json "$ARGUMENTS" --number 5 --short-name "user-auth" "Add user authentication"
```

**Quote handling**: For single quotes in arguments like "I'm", use escape syntax: `'I'\''m'` or double quotes: `"I'm"`.

### Feature Numbering

Before creating a new feature, check:
1. Remote branches: `git fetch --all --prune && git ls-remote --heads origin | grep -E 'refs/heads/[0-9]+-<short-name>$'`
2. Local branches: `git branch | grep -E '^[* ]*[0-9]+-<short-name>$'`
3. Spec directories: `ls -d specs/[0-9]*-<short-name> 2>/dev/null`

Use the highest number found + 1 for the new feature.

### Specification Quality

**Technology-Agnostic**: Specifications must avoid implementation details (languages, frameworks, APIs). They describe **WHAT** users need and **WHY**, not **HOW** to implement.

**Measurable Success Criteria**: Use specific metrics (time, count, percentage) from user/business perspective, not technical internals.

Good: "Users can complete checkout in under 3 minutes"
Bad: "API response time is under 200ms"

**Limited Clarifications**: Maximum 3 `[NEEDS CLARIFICATION]` markers per spec. Make informed guesses based on industry standards for everything else.

**Independent Testability**: Each user story must be demonstrable on its own, delivering standalone value.

### Task Format

Every task MUST follow this format:
```
- [ ] [TaskID] [P?] [Story?] Description with file path
```

- **TaskID**: Sequential (T001, T002, T003...)
- **[P]**: Present only if parallelizable (different files, no dependencies)
- **[Story]**: Required for user story phase tasks (e.g., [US1], [US2])
- **Description**: Clear action with exact file path

### Constitution Authority

The project constitution (`.specify/memory/constitution.md`) is **non-negotiable**. Constitutional conflicts are automatically CRITICAL and require adjustment of artifacts, not dilution of principles. Constitution changes must occur separately from feature development.

## Development Patterns

### Skill Execution Flow

Each skill command file (`.claude/commands/speckit.*.md`) contains:
- **Description**: Skill purpose
- **Handoffs**: Suggested next skills to invoke
- **Outline**: Step-by-step execution instructions
- **Guidelines**: Quality standards and constraints

Follow the outline exactly as specified in each skill file.

### Tests Are Optional

Only generate test tasks if explicitly requested in the specification or by the user. The templates show test examples, but these should be removed if tests aren't required.

### Parallel Execution

Tasks marked `[P]` can run in parallel if they:
- Modify different files
- Have no dependencies on incomplete tasks

Use this to optimize implementation time.

### Incremental Updates

The `/speckit.clarify` workflow integrates answers **incrementally** after each question, saving the spec file after each update to prevent context loss.

### Agent Context Management

When adding new technologies during planning, run:
```bash
.specify/scripts/bash/update-agent-context.sh claude
```

This updates the Claude-specific context file while preserving manual additions between markers.

## File Paths

**Always use absolute paths** in scripts and tool invocations. The scripts return absolute paths in JSON output - parse and use these directly.

## Error Handling

**Gate Failures**: If constitution principles are violated or clarifications remain unresolved, ERROR and stop execution rather than proceeding with invalid state.

**Missing Prerequisites**: If a skill requires artifacts from previous steps (e.g., `/speckit.tasks` needs `plan.md`), check prerequisites and instruct user to run missing commands.

## Validation Checkpoints

**Specification Quality Checklist**: After generating a spec, validate against quality criteria before allowing progression to planning.

**Constitution Check**: Both during initial planning and after design artifacts are generated.

**Cross-Artifact Analysis**: Use `/speckit.analyze` to detect inconsistencies, duplications, ambiguities, and coverage gaps across spec, plan, and tasks.

## Working with Checklists

Generated checklists in `FEATURE_DIR/checklists/` provide domain-specific validation. Before implementation:
1. Count total/completed/incomplete items per checklist
2. If any incomplete items exist, ask user for approval before proceeding
3. Display checklist status table showing pass/fail per checklist

## Branch and Spec Management

Feature branches are created automatically by the workflow. The branch name format is `###-short-name` where:
- `###` is calculated by finding the highest existing number for that short-name + 1
- `short-name` is a 2-4 word action-noun format (e.g., "user-auth", "oauth2-api-integration")

The workflow checks all three sources (remote branches, local branches, spec directories) to ensure consistent numbering.
