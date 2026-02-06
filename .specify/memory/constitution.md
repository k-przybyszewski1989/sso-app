# SSO App Constitution

<!--
Sync Impact Report - Version 1.0.0 Initial Ratification

Version Change: N/A → 1.0.0 (Initial Constitution)
Ratification Date: 2026-02-06

Principles Established:
1. Strict Typing & Type Safety
2. SOLID Architecture & Clean Code
3. Repository Pattern with Clear Semantics
4. Comprehensive Testing Discipline
5. Security-First Development
6. Specification-Driven Development

Additional Sections:
- Technical Standards (PHP/Symfony constraints)
- Development Workflow (SpecKit integration)
- Governance (amendment process, versioning)

Templates Requiring Updates:
✅ .specify/templates/spec-template.md - Reviewed, aligns with principles
✅ .specify/templates/plan-template.md - Constitution Check section aligns
✅ .specify/templates/tasks-template.md - Task categorization aligns
✅ .claude/commands/speckit.*.md - Reviewed for consistency

Follow-up TODOs: None

Commit Message:
docs: ratify constitution v1.0.0 (initial SSO App governance)
-->

## Core Principles

### I. Strict Typing & Type Safety

**MUST** enforce strict types in all PHP code:
- Every PHP file (except interfaces) MUST begin with `declare(strict_types=1);`
- All method parameters MUST have explicit type hints
- All method return types MUST be declared
- Array contents MUST be documented with PHPDoc annotations (e.g., `@param array<string, int>`)
- NEVER use `mixed`, `object`, or untyped variables without documented justification

**Rationale**: Type safety prevents entire classes of runtime errors, enables static analysis with PHPStan, and serves as living documentation. In a security-critical SSO application, type ambiguity can lead to authentication/authorization vulnerabilities.

### II. SOLID Architecture & Clean Code

**MUST** adhere to SOLID principles and clean architecture patterns:
- Services MUST be `final readonly class` with interfaces for mocking
- Controllers MUST remain thin, delegating all business logic to services
- Dependency injection MUST be used exclusively (no service locators, no static calls)
- Single Responsibility: Each class has ONE reason to change
- Avoid over-engineering: No abstractions for hypothetical future requirements

**Rationale**: SSO systems require maintainability and testability at scale. SOLID principles ensure the codebase remains extensible without becoming brittle. `readonly` classes prevent accidental state mutation, critical for security contexts.

### III. Repository Pattern with Clear Semantics

**MUST** implement repositories following these rules:
- Repository interfaces define contracts (no `declare(strict_types=1);` in interfaces)
- Implementations MUST be `final readonly class Doctrine*Repository implements *RepositoryInterface`
- `find*` methods return nullable types (`?Entity`) for "may not exist" scenarios
- `get*` methods return guaranteed types (`Entity`) and throw `EntityNotFoundException` when not found
- Support optional `bool $lock = false` parameter for pessimistic locking in critical sections

**Rationale**: Clear semantics prevent null-pointer exceptions and make transaction locking explicit. In SSO authentication flows, race conditions can create security vulnerabilities; pessimistic locking must be opt-in and obvious.

### IV. Comprehensive Testing Discipline

**MUST** follow testing standards:
- Test files mirror source structure under `tests/` with `Test\` namespace prefix
- Test classes are `final class *Test extends TestCase`
- Test methods use `test*` prefix (e.g., `testUserLoginSucceedsWithValidCredentials`)
- Unit tests MUST mock external dependencies using interfaces
- Integration tests required for: authentication flows, authorization checks, token generation, inter-service communication

**Note**: Tests are NOT automatically required for every feature. Generate test tasks only when:
1. Explicitly requested in the specification
2. The feature involves security-critical logic (authentication, authorization, cryptography)
3. The user explicitly requests tests

**Rationale**: SSO systems are trust boundaries; authentication bugs have catastrophic consequences. However, over-testing low-risk code wastes resources. Testing discipline must be strategic, not dogmatic.

### V. Security-First Development

**MUST** prioritize security at every layer:
- Input validation MUST use Symfony Validation components with explicit constraints
- Error handling MUST catch specific exception types; `\Throwable` only for unexpected errors
- Logging MUST include correlation IDs but NEVER log PII (passwords, tokens, email addresses unless hashed)
- NEVER use destructive git actions (force push, reset --hard) without explicit user approval
- Static analysis (PHPStan, PHP-CS-Fixer) MUST pass before commits

**Rationale**: SSO applications are high-value targets. A single XSS, SQL injection, or token leakage can compromise entire ecosystems. Security cannot be retrofitted; it must be architectural.

### VI. Specification-Driven Development

**MUST** follow SpecKit workflow for non-trivial features:
1. Technology-agnostic specifications describing WHAT and WHY (not HOW)
2. User stories prioritized by business value (P1, P2, P3...) and independently testable
3. Implementation plans vetted through constitution checks
4. Task breakdown with explicit dependencies and parallelization markers
5. Incremental implementation with validation gates

**Rationale**: Ad-hoc feature development leads to scope creep, technical debt, and misalignment with user needs. Specification-first ensures clarity, enables stakeholder review, and prevents wasted implementation effort.

## Technical Standards

### Language & Framework Constraints

- **PHP Version**: 8.4+ (leveraging readonly properties, promoted constructors, enums)
- **Symfony Version**: 8.0.x (long-term support, security patches mandatory)
- **Database**: MariaDB with Doctrine ORM (no raw SQL unless performance-critical and documented)
- **Code Style**: PSR-12 with PHP-CS-Fixer enforcement
- **Static Analysis**: PHPStan level 9 (maximum strictness)

### Naming Conventions

- Interfaces: `*Interface` suffix (e.g., `UserRepositoryInterface`)
- Doctrine Repositories: `Doctrine*Repository` (e.g., `DoctrineUserRepository`)
- DTOs: `*DTO` suffix (e.g., `TokenResponseDTO`)
- Requests: `*Request` suffix (e.g., `AuthenticationRequest`)
- Enums: Backed enums with string values (e.g., `enum TokenType: string`)

### Error Handling Standards

- Domain exceptions for business rule violations (e.g., `InvalidCredentialsException`)
- Infrastructure exceptions for external failures (e.g., `DatabaseConnectionException`)
- Never catch `\Throwable` without rethrowing or logging with full context
- Emit domain events for failures when applicable (e.g., `LoginFailedEvent` for security monitoring)

## Development Workflow

### SpecKit Integration

All non-trivial features MUST follow the SpecKit workflow:

1. **`/speckit.specify`**: Create technology-agnostic specification
2. **`/speckit.clarify`** (optional): Resolve ambiguities before planning
3. **`/speckit.plan`**: Generate implementation plan with constitution check
4. **`/speckit.tasks`**: Break down into dependency-ordered tasks
5. **`/speckit.analyze`** (optional): Cross-artifact consistency validation
6. **`/speckit.implement`**: Execute phased implementation

### Validation Gates

**Before Implementation Proceeds:**
1. Constitution check MUST pass (no principle violations)
2. Specification MUST have ≤3 `[NEEDS CLARIFICATION]` markers
3. Plans MUST include measurable success criteria from user perspective
4. Tasks MUST follow `[TaskID] [P?] [Story?] Description + file path` format

**Constitutional Conflicts**: Automatically CRITICAL. Adjust artifacts, not principles. Constitution changes require separate amendment process.

### Git Workflow

- Feature branches: `###-short-name` format (e.g., `001-oauth2-integration`)
- Commits: Atomic, descriptive, following Conventional Commits
- Co-authored by Claude: `Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>`
- NEVER skip hooks (`--no-verify`), NEVER force push to main/master
- Stage specific files by name, not `git add .` (prevents accidental secret commits)

## Governance

### Amendment Process

1. Constitutional amendments MUST be proposed separately from feature work
2. Amendments require:
   - Documented rationale for change
   - Impact analysis on existing artifacts and templates
   - Version bump following semantic versioning:
     - **MAJOR**: Backward-incompatible governance changes (principle removal/redefinition)
     - **MINOR**: New principles or material expansions
     - **PATCH**: Clarifications, wording fixes, non-semantic refinements
3. After amendment, run `.specify/scripts/bash/update-agent-context.sh` to propagate changes

### Compliance & Review

- All PRs MUST verify constitutional compliance (use `/speckit.analyze` for automated checks)
- Constitution supersedes all other practices and guidelines
- Complexity deviations MUST be justified and documented inline
- Runtime development guidance lives in `CLAUDE.md` (agent-specific) and this constitution (project-wide)

### Version History

**Version**: 1.0.0 | **Ratified**: 2026-02-06 | **Last Amended**: 2026-02-06

---

*This constitution is the supreme governance document for the SSO App project. All code, documentation, and processes must align with these principles. Violations found during review are blocking and must be resolved before merge.*
