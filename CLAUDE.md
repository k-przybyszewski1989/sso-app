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
- Entities classes should never be `final class`
- Controllers should be `final class`
- Test classes should be `final class` extending `TestCase`

### Entities
- Every entity MUST have a `getId()` method
- Every entity MUST have a `createdAt` and `updatedAt` properties

### Naming Conventions

- Class Interfaces: `*Interface` suffix (e.g., `CountInterface`)
- Repository implementations: `Doctrine*Repository` (e.g., `DoctrineUserRepository implements UserRepositoryInterface`)
- Request objects: `*Request` or `*Request` (e.g., `TokenRequest`)
- DTOs: `*DTO` (e.g., `CancelPaymentIntentDTO`)
- Enums: Use backed enums with string values (e.g., `enum PaymentType: string`)
- Test classes and paths: `*Test` (e.g., `UserRepositoryTest`) and place them in `tests/` directory following the same structure as the source code
- Test namespace: Use `App\Tests\` prefix (e.g., `App\Tests\Metrics`)
- Abstract classes should always be prefixed with `Abstract` (e.g., `AbstractUserRepository`)

### Strict Typing

- Always add `declare(strict_types=1);` at the top of PHP files
- Always use explicit return type declarations for methods
- Use type hints for all method parameters and properties, for arrays use annotations to explicitly declare the type
- Never add `@param` or `@return` annotations to methods with explicit return types

### Error Handling

- Prefer specific exception types (e.g., `EntityNotFoundException`, `RefundDuplicatedException`)
- Catch `\Throwable` only for unexpected errors; handle or rethrow with context
- Log errors with relevant context (e.g., userId) and avoid PII disclosure
- Emit failure domain events when applicable (e.g., `UserRegistrationFailedEvent`)

### Logging

- Use PSR-3
- Log level guidance: warning for recoverable domain issues, error for failures, critical for data loss
- Include correlation identifiers; avoid PII disclosure in log message

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

- Keep controllers thin - move business logic into services
- Never operate on HTTP Foundation requests directly – use DTOs instead (`RequestTransform::class`)
- Request DTOs should be immutable, and sanitized properly before controller execution

## Implementation Guidance

- Prefer SOLID principles and clean architecture
- Keep services stateless and dependency-injected
- Use Doctrine ORM and QueryBuilder instead of raw SQL when possible
- Validate inputs with Symfony Validation; add custom validators when needed
- Ensure explicit types throughout; avoid `mixed`/`object`-like patterns
- Match PSR-12 formatting; keep code readable with descriptive names
- Use PHPDoc for typing only - avoid unnecessary comments explaining what a method does (this should be part of the function name instead)
- Always use the `JSON_THROW_ON_ERROR` flag when encoding/decoding JSON strings
- Always use `yoda_style` comparisons
- Always use `#[AllowMockObjectsWithoutExpectations]` attribute on test classes
- Prefer enum over string for class properties i.e. instead of `string $grantType` use `App\Enum\GrantType $grantType`

## File Scaffolding Checklist (per class type)

- Service/Handler/DTO/Request: `final readonly class`, strict types, explicit return types
- Repository interface: no `declare(strict_types=1);`, clear `find*`/`get*` split
- Doctrine repository implementation: `final readonly class Doctrine*Repository implements *RepositoryInterface`
- Controller: `final class`, remain stateless; delegate to services
- Test: `final class` extending `TestCase`, methods prefixed with `test*`

## Git Workflow
- Always use `git flow` for branching and merging
- Always run `composer test` and `composer analyze` before committing

## Plan mode
- Always use checklist as a format of steps to be done to track progress
- Always save the plan into plan.md file
- Always use markdown format for plan
