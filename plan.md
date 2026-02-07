# OAuth2 Protocol Implementation Plan

## Context

This plan implements a fully operational OAuth2 2.0 authorization server for the SSO API. The system currently has minimal authentication infrastructure (in-memory user provider, basic security configuration) and empty Entity/Repository directories. This implementation will establish the foundation for OAuth2-based authentication and authorization across the entire API.

**Why this change is needed:**
- Enable third-party applications to securely access the API on behalf of users
- Provide industry-standard authentication using OAuth2 protocol (RFC 6749)
- Support multiple grant types for different use cases (authorization code, client credentials, refresh token)
- Establish secure token-based authentication infrastructure

**Intended outcome:**
- Complete OAuth2 authorization server with all standard endpoints
- Support for 3 grant types: authorization_code, client_credentials, refresh_token
- User registration and login API
- Scope-based authorization for API endpoints
- Comprehensive test coverage for all OAuth2 flows

**Implementation decisions based on requirements:**
- Authorization endpoint: API-only (returns codes via JSON, assumes authenticated user)
- User management: REST API endpoints for registration and login
- PKCE: Optional support (not mandatory)
- Scope enforcement: Full implementation with attribute-based protection for API endpoints

---

## Architecture Overview

### Entity Model

Six core entities establish the OAuth2 data model:

1. **User** (`src/Entity/User.php`) - Resource owners, implements Symfony's `UserInterface`
2. **OAuth2Client** (`src/Entity/OAuth2Client.php`) - Registered applications with credentials
3. **AccessToken** (`src/Entity/AccessToken.php`) - Short-lived tokens (1 hour) for API access
4. **RefreshToken** (`src/Entity/RefreshToken.php`) - Long-lived tokens (30 days) for renewal
5. **AuthorizationCode** (`src/Entity/AuthorizationCode.php`) - Short-lived codes (10 min) for auth code flow
6. **Scope** (`src/Entity/Scope.php`) - Available permission scopes (openid, profile, email, offline_access)

**Key relationships:**
- User (1) → (N) AccessToken, RefreshToken, AuthorizationCode
- OAuth2Client (1) → (N) AccessToken, RefreshToken, AuthorizationCode
- Scopes stored as JSON arrays in tokens/codes

**Table naming:** Doctrine's `underscore_number_aware` strategy converts entity names (e.g., `AccessToken` → `access_token` table)

### Service Architecture

**Core Services:**
- `TokenGeneratorService` - Cryptographically secure token generation
- `ClientAuthenticationService` - Client credential validation (Basic Auth + POST body)
- `ScopeValidationService` - Scope validation against client permissions
- `PkceService` - PKCE challenge validation (plain and S256 methods)
- `AccessTokenService` - Access token lifecycle management
- `RefreshTokenService` - Refresh token lifecycle with rotation
- `AuthorizationCodeService` - Authorization code creation and validation

**Grant Handlers (Strategy Pattern):**
- `AuthorizationCodeGrantHandler` - Exchanges auth codes for tokens
- `ClientCredentialsGrantHandler` - Machine-to-machine authentication
- `RefreshTokenGrantHandler` - Token renewal with rotation
- `OAuth2Service` - Orchestrator receiving all grant handlers via tagged services

**User Services:**
- `UserRegistrationService` - Handles user creation with validation
- `UserAuthenticationService` - Password verification and login

### Repository Pattern

Following project standards: `final readonly class Doctrine*Repository implements *RepositoryInterface`

All repositories provide:
- `find*` methods returning `?Entity` (nullable)
- `get*` methods returning `Entity` or throwing `EntityNotFoundException`
- `bool $lock = false` parameter for pessimistic locking on `get*` methods

Six repository pairs (interface + Doctrine implementation):
1. UserRepository
2. OAuth2ClientRepository
3. AccessTokenRepository (includes `deleteExpired()` cleanup)
4. RefreshTokenRepository (includes `deleteExpired()` cleanup)
5. AuthorizationCodeRepository (includes `deleteExpired()` cleanup)
6. ScopeRepository

### REST API Endpoints

**OAuth2 Endpoints:**
- `POST /oauth2/authorize` - Authorization endpoint (returns code as JSON)
- `POST /oauth2/token` - Token endpoint (exchanges credentials/codes for tokens)
- `POST /oauth2/revoke` - Token revocation
- `POST /oauth2/introspect` - Token introspection (RFC 7662)

**User Management Endpoints:**
- `POST /api/users/register` - User registration
- `POST /api/users/login` - User login (returns access token)
- `GET /api/users/me` - Get current user profile (requires authentication)

**Client Management Endpoints (Admin only):**
- `GET /api/clients` - List OAuth2 clients
- `POST /api/clients` - Create new client (returns client_id and client_secret)
- `DELETE /api/clients/{clientId}` - Delete client

### Security Configuration

**Firewalls:**
- `oauth2_token` - Stateless, no security (handles auth internally)
- `api` - Stateless, uses `OAuth2Authenticator` (validates Bearer tokens)

**OAuth2Authenticator:**
- Extracts Bearer token from Authorization header
- Validates token via `AccessTokenService`
- Returns user from token for authenticated context

**Scope Enforcement:**
- Custom `#[RequireScope('scope_name')]` attribute for controllers/methods
- `ScopeAuthorizationListener` validates token scopes against required scopes
- Returns 403 if scopes insufficient

### Request/Response Flow

**Leverages existing patterns:**
- `#[RequestTransform]` attribute for automatic DTO deserialization
- `RequestArgumentResolver` handles JSON/form data → DTO conversion
- `RequestValidationException` for validation errors
- `RequestExceptionSubscriber` for global JSON error responses

**New DTOs:**
- Request: `TokenRequest`, `AuthorizationRequest`, `RevokeRequest`, `IntrospectRequest`, `RegisterUserRequest`, `LoginUserRequest`, `CreateClientRequest`
- Response: `TokenResponse`, `UserResponse`

### Exception Hierarchy

Base class `OAuth2Exception` with `error` code and HTTP status:
- `InvalidClientException` - `invalid_client` (401)
- `InvalidGrantException` - `invalid_grant` (400)
- `InvalidRequestException` - `invalid_request` (400)
- `InvalidScopeException` - `invalid_scope` (400)
- `InvalidTokenException` - `invalid_token` (401)
- `UnsupportedGrantTypeException` - `unsupported_grant_type` (400)
- `UnauthorizedClientException` - `unauthorized_client` (400)

All follow RFC 6749 error response format:
```json
{
  "error": "invalid_grant",
  "error_description": "Authorization code has expired"
}
```

---

## Implementation Plan

### Phase 1: Exceptions and DTOs (Foundation) ✅

**Create exception hierarchy:**
- [x] `src/Exception/OAuth2/OAuth2Exception.php` - Base exception with error code and status
- [x] `src/Exception/OAuth2/InvalidClientException.php`
- [x] `src/Exception/OAuth2/InvalidGrantException.php`
- [x] `src/Exception/OAuth2/InvalidRequestException.php`
- [x] `src/Exception/OAuth2/InvalidScopeException.php`
- [x] `src/Exception/OAuth2/InvalidTokenException.php`
- [x] `src/Exception/OAuth2/UnsupportedGrantTypeException.php`
- [x] `src/Exception/OAuth2/UnauthorizedClientException.php`
- [x] `src/Exception/EntityNotFoundException.php` - General entity not found exception

**Create response DTOs:**
- [x] `src/Response/OAuth2/TokenResponse.php` - Token endpoint response
- [x] `src/Response/User/UserResponse.php` - User profile response

**Create request DTOs:**
- [x] `src/Request/OAuth2/TokenRequest.php` - Token endpoint (grant_type, code, client credentials, etc.)
- [x] `src/Request/OAuth2/AuthorizationRequest.php` - Authorization endpoint (response_type, client_id, redirect_uri, scope, state, PKCE)
- [x] `src/Request/OAuth2/RevokeRequest.php` - Revoke endpoint (token, token_type_hint)
- [x] `src/Request/OAuth2/IntrospectRequest.php` - Introspect endpoint (token)
- [x] `src/Request/OAuth2/CreateClientRequest.php` - Client creation (name, redirect_uris, grant_types)
- [x] `src/Request/User/RegisterUserRequest.php` - User registration (email, username, password)
- [x] `src/Request/User/LoginUserRequest.php` - User login (email, password)

All DTOs: `final readonly class` with Symfony validation constraints

### Phase 2: Entity Model

**Create entities (all `final class` with Doctrine attributes):**

- [ ] `src/Entity/User.php` - Implements `UserInterface` and `PasswordAuthenticatedUserInterface`
    - Properties: id, email, username, password, roles[], enabled, createdAt, lastLoginAt
    - Relations: OneToMany to AccessToken, RefreshToken, AuthorizationCode

- [ ] `src/Entity/OAuth2Client.php`
    - Properties: id, clientId, clientSecretHash, name, description, redirectUris[], grantTypes[], allowedScopes[], confidential, active, createdAt, updatedAt
    - Relations: OneToMany to AccessToken, RefreshToken, AuthorizationCode

- [ ] `src/Entity/AccessToken.php`
    - Properties: id, token, client (ManyToOne), user (ManyToOne, nullable), scopes[], expiresAt, createdAt, revoked, revokedAt
    - Methods: `isExpired()`, `isValid()`
    - Indexes: token (unique), expires_at, (user_id, client_id) composite

- [ ] `src/Entity/RefreshToken.php`
    - Properties: id, token, client (ManyToOne), user (ManyToOne), scopes[], expiresAt, createdAt, revoked, revokedAt
    - Methods: `isExpired()`, `isValid()`
    - Indexes: token (unique), expires_at

- [ ] `src/Entity/AuthorizationCode.php`
    - Properties: id, code, client (ManyToOne), user (ManyToOne), redirectUri, scopes[], expiresAt, createdAt, used, usedAt, codeChallenge, codeChallengeMethod
    - Methods: `isExpired()`, `isValid()`
    - Indexes: code (unique), expires_at

- [ ] `src/Entity/Scope.php`
    - Properties: id, identifier, description, isDefault, createdAt
    - Index: identifier (unique)

### Phase 3: Repository Layer

**Create repository interfaces (NO `declare(strict_types=1)` per project standards):**

- [ ] `src/Repository/UserRepositoryInterface.php` - findById, getById, findByEmail, findByUsername, save, delete
- [ ] `src/Repository/OAuth2ClientRepositoryInterface.php` - findById, getById, findByClientId, getByClientId, findAll, findActive, save, delete
- [ ] `src/Repository/AccessTokenRepositoryInterface.php` - findByToken, getByToken, findByUser, findByClient, save, delete, deleteExpired, revokeAllForUser, revokeAllForClient
- [ ] `src/Repository/RefreshTokenRepositoryInterface.php` - findByToken, getByToken, findByUser, save, delete, deleteExpired, revokeAllForUser
- [ ] `src/Repository/AuthorizationCodeRepositoryInterface.php` - findByCode, getByCode, save, delete, deleteExpired
- [ ] `src/Repository/ScopeRepositoryInterface.php` - findById, getById, findByIdentifier, getByIdentifier, findAll, findDefaults, findByIdentifiers, save, delete

**Create Doctrine implementations (`final readonly class`):**

- [ ] `src/Repository/DoctrineUserRepository.php` - Implements UserRepositoryInterface
- [ ] `src/Repository/DoctrineOAuth2ClientRepository.php` - Implements OAuth2ClientRepositoryInterface
- [ ] `src/Repository/DoctrineAccessTokenRepository.php` - Implements AccessTokenRepositoryInterface
    - Use `LockMode::PESSIMISTIC_WRITE` for locking
    - `deleteExpired()` uses QueryBuilder DELETE with timestamp filter
- [ ] `src/Repository/DoctrineRefreshTokenRepository.php` - Implements RefreshTokenRepositoryInterface
- [ ] `src/Repository/DoctrineAuthorizationCodeRepository.php` - Implements AuthorizationCodeRepositoryInterface
- [ ] `src/Repository/DoctrineScopeRepository.php` - Implements ScopeRepositoryInterface

### Phase 4: Database Migrations

- [ ] Generate migration: `bin/console make:migration`
    - Creates all 6 tables with proper columns, types, indexes, foreign keys
    - Run: `bin/console doctrine:migrations:migrate`

- [ ] Create seed migration: `migrations/Version*_SeedScopes.php`
    - Insert default scopes: openid, profile, email, offline_access
    - Run: `bin/console doctrine:migrations:migrate`

### Phase 5: Core OAuth2 Services

**Create service interfaces and implementations (`final readonly class`):**

- [ ] `src/Service/OAuth2/TokenGeneratorServiceInterface.php` + `src/Service/OAuth2/TokenGeneratorService.php`
    - `generateAccessToken()` - 64 chars (32 bytes hex)
    - `generateRefreshToken()` - 64 chars
    - `generateAuthorizationCode()` - 32 chars (16 bytes hex)
    - `generateClientId()` - 32 chars
    - `generateClientSecret()` - 64 chars

- [ ] `src/Service/OAuth2/PkceServiceInterface.php` + `src/Service/OAuth2/PkceService.php`
    - `validate(string $codeVerifier, string $codeChallenge, string $method)` - Supports 'plain' and 'S256'

- [ ] `src/Service/OAuth2/ScopeValidationServiceInterface.php` + `src/Service/OAuth2/ScopeValidationService.php`
    - `validate(array $requestedScopes, array $allowedScopes): array` - Returns valid scopes or throws InvalidScopeException
    - Uses `ScopeRepository->findByIdentifiers()` to verify scopes exist

- [ ] `src/Service/OAuth2/ClientAuthenticationServiceInterface.php` + `src/Service/OAuth2/ClientAuthenticationService.php`
    - `authenticate(?string $authHeader, ?string $clientId, ?string $clientSecret): OAuth2Client`
    - Supports Basic Auth (Authorization: Basic base64(clientId:clientSecret))
    - Falls back to POST body credentials
    - Verifies client secret with PasswordHasher
    - Throws InvalidClientException if invalid

- [ ] `src/Service/OAuth2/AccessTokenServiceInterface.php` + `src/Service/OAuth2/AccessTokenService.php`
    - `createAccessToken(OAuth2Client $client, array $scopes, ?User $user): AccessToken` - Creates token with 1 hour expiration
    - `validateToken(string $token): AccessToken` - Validates and returns token or throws InvalidTokenException
    - `revokeToken(string $token): void` - Marks token as revoked

- [ ] `src/Service/OAuth2/RefreshTokenServiceInterface.php` + `src/Service/OAuth2/RefreshTokenService.php`
    - `createRefreshToken(OAuth2Client $client, User $user, array $scopes): RefreshToken` - Creates token with 30 day expiration
    - `validateAndConsumeToken(string $token, OAuth2Client $client): RefreshToken` - Validates and revokes old token (rotation)
    - `revokeToken(string $token): void`

- [ ] `src/Service/OAuth2/AuthorizationCodeServiceInterface.php` + `src/Service/OAuth2/AuthorizationCodeService.php`
    - `createAuthorizationCode(OAuth2Client $client, User $user, string $redirectUri, array $scopes, ?string $codeChallenge, ?string $codeChallengeMethod): AuthorizationCode` - Creates code with 10 min expiration
    - `validateAndConsumeCode(string $code, OAuth2Client $client, string $redirectUri, ?string $codeVerifier): AuthorizationCode` - Validates code, checks PKCE if present, marks as used

### Phase 6: Grant Handlers

- [ ] `src/Service/OAuth2/Grant/GrantHandlerInterface.php`
    - `supports(string $grantType): bool`
    - `handle(TokenRequest $request): TokenResponse`

- [ ] `src/Service/OAuth2/Grant/ClientCredentialsGrantHandler.php` (implements GrantHandlerInterface)
    - Authenticates client
    - Validates grant type allowed for client
    - Validates scopes
    - Creates access token (no user, no refresh token)
    - Returns TokenResponse

- [ ] `src/Service/OAuth2/Grant/AuthorizationCodeGrantHandler.php` (implements GrantHandlerInterface)
    - Authenticates client
    - Validates grant type allowed
    - Validates and consumes authorization code (includes PKCE check)
    - Creates access token
    - Creates refresh token if 'offline_access' scope present
    - Returns TokenResponse

- [ ] `src/Service/OAuth2/Grant/RefreshTokenGrantHandler.php` (implements GrantHandlerInterface)
    - Authenticates client
    - Validates grant type allowed
    - Validates and consumes refresh token (rotation)
    - Validates scopes (can narrow, not expand)
    - Creates new access token
    - Creates new refresh token
    - Returns TokenResponse

- [ ] `src/Service/OAuth2/OAuth2ServiceInterface.php` + `src/Service/OAuth2/OAuth2Service.php`
    - Receives iterable of GrantHandlerInterface (tagged services)
    - `issueToken(TokenRequest $request): TokenResponse` - Delegates to appropriate grant handler
    - Throws UnsupportedGrantTypeException if no handler supports grant type

### Phase 7: User Services

- [ ] `src/Service/User/UserRegistrationServiceInterface.php` + `src/Service/User/UserRegistrationService.php`
    - `registerUser(string $email, string $username, string $password): User`
    - Validates email/username uniqueness
    - Hashes password
    - Creates User entity
    - Logs registration

- [ ] `src/Service/User/UserAuthenticationServiceInterface.php` + `src/Service/User/UserAuthenticationService.php`
    - `authenticate(string $email, string $password): User`
    - Finds user by email
    - Verifies password with PasswordHasher
    - Updates lastLoginAt
    - Throws InvalidCredentialsException if invalid

### Phase 8: Client Management Service

- [ ] `src/Service/OAuth2/ClientManagementServiceInterface.php` + `src/Service/OAuth2/ClientManagementService.php`
    - `createClient(string $name, array $redirectUris, array $grantTypes, bool $confidential): array` - Returns ['client_id', 'client_secret', 'name']
    - `listClients(): array<OAuth2Client>`
    - `deleteClient(string $clientId): void`
    - Hashes client secret with bcrypt before storing

### Phase 9: Scope Enforcement

- [ ] `src/Security/Attribute/RequireScope.php` - PHP attribute for scope requirements
    - `#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]`
    - Constructor accepts string|array of required scopes

- [ ] `src/EventListener/ScopeAuthorizationListener.php` - Event subscriber
    - Listens to `kernel.controller` event
    - Checks for `#[RequireScope]` attribute on controller/method
    - Validates current token has required scopes
    - Returns 403 JsonResponse if insufficient scopes

### Phase 10: Controllers

- [ ] `src/Controller/OAuth2Controller.php` (`final class`)
    - `POST /oauth2/authorize` - `authorize(AuthorizationRequest)` - Creates auth code, returns as JSON
    - `POST /oauth2/token` - `token(TokenRequest)` - Delegates to OAuth2Service, returns TokenResponse
    - `POST /oauth2/revoke` - `revoke(RevokeRequest)` - Revokes access/refresh token, returns 200
    - `POST /oauth2/introspect` - `introspect(IntrospectRequest)` - Returns token metadata or {active: false}

- [ ] `src/Controller/UserController.php` (`final class`)
    - `POST /api/users/register` - `register(RegisterUserRequest)` - Creates user, returns UserResponse
    - `POST /api/users/login` - `login(LoginUserRequest)` - Authenticates user, creates access token, returns TokenResponse
    - `GET /api/users/me` - `me()` - Returns current authenticated user (uses OAuth2Authenticator)

- [ ] `src/Controller/ClientController.php` (`final class`)
    - `GET /api/clients` - `list()` - Returns all clients (admin only)
    - `POST /api/clients` - `create(CreateClientRequest)` - Creates client, returns client_id and client_secret (admin only)
    - `DELETE /api/clients/{clientId}` - `delete(string $clientId)` - Deletes client (admin only)

### Phase 11: Security Integration

- [ ] `src/Security/OAuth2Authenticator.php` - Implements `AbstractAuthenticator`
    - `supports(Request)` - Checks for "Bearer " token in Authorization header
    - `authenticate(Request)` - Extracts token, validates via AccessTokenService, returns Passport with user
    - `onAuthenticationSuccess()` - Returns null (continues request)
    - `onAuthenticationFailure()` - Returns 401 JSON with error

- [ ] Update `config/packages/security.yaml`:
    - Add password hasher for User entity (auto)
    - Add password hasher for OAuth2Client (bcrypt, cost 12)
    - Add entity user provider for User
    - Add firewalls: oauth2_token (stateless, no security), api (stateless, OAuth2Authenticator)
    - Add access control: /oauth2 (PUBLIC), /api/clients (ROLE_ADMIN), /api (IS_AUTHENTICATED_FULLY)

- [ ] Update `config/services.yaml`:
    - Tag all grant handlers with 'app.oauth2.grant_handler'
    - Configure OAuth2Service to receive tagged grant handlers via `!tagged_iterator`
    - Bind all repository interfaces to Doctrine implementations

### Phase 12: Console Commands

- [ ] `src/Command/CleanupExpiredTokensCommand.php`
    - `oauth2:cleanup-expired-tokens` - Calls deleteExpired() on all token repositories
    - Returns count of deleted tokens
    - Schedule via cron for daily cleanup

- [ ] `src/Command/CreateAdminUserCommand.php`
    - `user:create-admin` - Interactive command to create admin user with ROLE_ADMIN
    - Useful for initial setup

### Phase 13: Unit Tests

**Test all services in isolation (`final class *Test extends TestCase`):**

- [ ] `tests/Service/OAuth2/TokenGeneratorServiceTest.php` - Test token generation uniqueness and length
- [ ] `tests/Service/OAuth2/PkceServiceTest.php` - Test plain and S256 validation
- [ ] `tests/Service/OAuth2/ScopeValidationServiceTest.php` - Test scope validation logic
- [ ] `tests/Service/OAuth2/ClientAuthenticationServiceTest.php` - Test Basic Auth and POST body auth, invalid credentials
- [ ] `tests/Service/OAuth2/AccessTokenServiceTest.php` - Test token creation, validation, revocation
- [ ] `tests/Service/OAuth2/RefreshTokenServiceTest.php` - Test token rotation
- [ ] `tests/Service/OAuth2/AuthorizationCodeServiceTest.php` - Test code creation, validation, PKCE
- [ ] `tests/Service/OAuth2/Grant/ClientCredentialsGrantHandlerTest.php` - Test grant handler
- [ ] `tests/Service/OAuth2/Grant/AuthorizationCodeGrantHandlerTest.php` - Test grant handler
- [ ] `tests/Service/OAuth2/Grant/RefreshTokenGrantHandlerTest.php` - Test grant handler
- [ ] `tests/Service/OAuth2/OAuth2ServiceTest.php` - Test orchestrator delegation
- [ ] `tests/Service/User/UserRegistrationServiceTest.php` - Test user registration
- [ ] `tests/Service/User/UserAuthenticationServiceTest.php` - Test authentication

### Phase 14: Repository Tests

**Test repository implementations with database (`final class *Test extends KernelTestCase`):**

- [ ] `tests/Repository/DoctrineUserRepositoryTest.php` - Test CRUD operations, findByEmail, findByUsername
- [ ] `tests/Repository/DoctrineOAuth2ClientRepositoryTest.php` - Test CRUD, findByClientId
- [ ] `tests/Repository/DoctrineAccessTokenRepositoryTest.php` - Test CRUD, deleteExpired, revokeAllForUser
- [ ] `tests/Repository/DoctrineRefreshTokenRepositoryTest.php` - Test CRUD, deleteExpired
- [ ] `tests/Repository/DoctrineAuthorizationCodeRepositoryTest.php` - Test CRUD, deleteExpired
- [ ] `tests/Repository/DoctrineScopeRepositoryTest.php` - Test CRUD, findByIdentifiers, findDefaults

### Phase 15: Integration Tests

**Test complete OAuth2 flows end-to-end (`final class *Test extends WebTestCase`):**

- [ ] `tests/Integration/OAuth2FlowTest.php` - Test all three grant types:
    - `testClientCredentialsFlow()` - Create client, request token, verify response
    - `testAuthorizationCodeFlow()` - Create client, get auth code, exchange for token, verify refresh token
    - `testRefreshTokenFlow()` - Get initial tokens, use refresh token to get new tokens
    - `testPkceAuthorizationCodeFlow()` - Test PKCE with S256 challenge
    - `testTokenRevocation()` - Create token, revoke it, verify it's invalid
    - `testTokenIntrospection()` - Create token, introspect it, verify metadata

- [ ] `tests/Integration/UserFlowTest.php` - Test user management:
    - `testUserRegistrationAndLogin()` - Register user, login, get token
    - `testProtectedEndpointAccess()` - Access /api/users/me with valid token
    - `testUnauthorizedAccess()` - Access protected endpoint without token

- [ ] `tests/Integration/ScopeEnforcementTest.php` - Test scope-based authorization:
    - `testAccessWithSufficientScopes()` - Access endpoint with required scopes
    - `testAccessWithInsufficientScopes()` - Verify 403 when scopes missing

---

## Critical Files to Modify/Create

### New Files (in order of creation)

**Exceptions:** 9 files in `src/Exception/` and `src/Exception/OAuth2/`

**DTOs:** 11 files in `src/Request/OAuth2/`, `src/Request/User/`, `src/Response/OAuth2/`, `src/Response/User/`

**Entities:** 6 files in `src/Entity/`

**Repositories:** 12 files in `src/Repository/` (6 interfaces + 6 implementations)

**Services:** 20 files in `src/Service/OAuth2/`, `src/Service/OAuth2/Grant/`, `src/Service/User/`

**Security:** 2 files in `src/Security/`, 1 file in `src/EventListener/`

**Controllers:** 3 files in `src/Controller/`

**Commands:** 2 files in `src/Command/`

**Tests:** 22 files in `tests/Service/`, `tests/Repository/`, `tests/Integration/`

**Migrations:** 2 files in `migrations/`

### Files to Modify

1. **`config/packages/security.yaml`** - Add OAuth2 authentication, password hashers, firewalls
2. **`config/services.yaml`** - Configure grant handler tags, repository bindings

### Existing Patterns to Reuse

**From `/Users/krzysztof.przybyszewski/Projects/ai/sso-app/src/Request/ParamConverter/`:**
- `RequestTransform` attribute - Use on all controller method parameters
- `RequestArgumentResolver` - Already configured, handles DTO transformation
- `RequestValidationException` - Use in custom validation logic
- `RequestExceptionSubscriber` - Already handles global error responses

**Pattern usage:**
```php
#[Route('/oauth2/token', methods: ['POST'])]
public function token(
    #[RequestTransform(validate: true)]
    TokenRequest $request,
): Response {
    // Request is automatically deserialized and validated
}
```

---

## Verification & Testing

### Manual Testing Steps

1. **Create admin user:**
   ```bash
   bin/console user:create-admin
   # Enter email, username, password
   ```

2. **Create OAuth2 client:**
   ```bash
   curl -X POST http://localhost/api/clients \
     -H "Authorization: Bearer <admin_token>" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "Test Client",
       "redirect_uris": ["https://example.com/callback"],
       "grant_types": ["authorization_code", "client_credentials", "refresh_token"]
     }'
   # Save client_id and client_secret from response
   ```

3. **Test client credentials flow:**
   ```bash
   curl -X POST http://localhost/oauth2/token \
     -u "CLIENT_ID:CLIENT_SECRET" \
     -d "grant_type=client_credentials&scope=openid profile"
   # Verify access_token in response
   ```

4. **Register user:**
   ```bash
   curl -X POST http://localhost/api/users/register \
     -H "Content-Type: application/json" \
     -d '{"email": "user@example.com", "username": "testuser", "password": "SecurePass123!"}'
   ```

5. **Login and get token:**
   ```bash
   curl -X POST http://localhost/api/users/login \
     -H "Content-Type: application/json" \
     -d '{"email": "user@example.com", "password": "SecurePass123!"}'
   # Save access_token
   ```

6. **Test authorization code flow:**
   ```bash
   # Get authorization code
   curl -X POST http://localhost/oauth2/authorize \
     -H "Authorization: Bearer <user_access_token>" \
     -H "Content-Type: application/json" \
     -d '{
       "response_type": "code",
       "client_id": "CLIENT_ID",
       "redirect_uri": "https://example.com/callback",
       "scope": "openid profile email offline_access",
       "state": "random_state"
     }'
   # Save code from response

   # Exchange code for tokens
   curl -X POST http://localhost/oauth2/token \
     -u "CLIENT_ID:CLIENT_SECRET" \
     -d "grant_type=authorization_code&code=CODE&redirect_uri=https://example.com/callback"
   # Verify access_token and refresh_token in response
   ```

7. **Test refresh token flow:**
   ```bash
   curl -X POST http://localhost/oauth2/token \
     -u "CLIENT_ID:CLIENT_SECRET" \
     -d "grant_type=refresh_token&refresh_token=REFRESH_TOKEN"
   # Verify new access_token and refresh_token (rotation)
   ```

8. **Test protected endpoint:**
   ```bash
   curl -X GET http://localhost/api/users/me \
     -H "Authorization: Bearer ACCESS_TOKEN"
   # Verify user profile returned
   ```

9. **Test token introspection:**
   ```bash
   curl -X POST http://localhost/oauth2/introspect \
     -u "CLIENT_ID:CLIENT_SECRET" \
     -d "token=ACCESS_TOKEN"
   # Verify {"active": true, "scope": "...", "client_id": "...", "username": "..."}
   ```

10. **Test token revocation:**
    ```bash
    curl -X POST http://localhost/oauth2/revoke \
      -u "CLIENT_ID:CLIENT_SECRET" \
      -d "token=ACCESS_TOKEN&token_type_hint=access_token"
    # Verify 200 response

    # Try using revoked token
    curl -X GET http://localhost/api/users/me \
      -H "Authorization: Bearer ACCESS_TOKEN"
    # Verify 401 error
    ```

### Automated Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suites
vendor/bin/phpunit tests/Service/OAuth2/
vendor/bin/phpunit tests/Integration/
```

### Database Verification

```bash
# Check migrations applied
bin/console doctrine:migrations:status

# Verify tables created
bin/console doctrine:schema:validate

# Check scopes seeded
bin/console dbal:run-sql "SELECT * FROM oauth2_scopes"
```

### Cleanup Testing

```bash
# Create expired tokens (manually in database or via test)
# Run cleanup command
bin/console oauth2:cleanup-expired-tokens
# Verify counts of deleted tokens
```

---

## Success Criteria

✅ All 6 entities created with proper relationships and indexes
✅ All 6 repository pairs implemented (interface + Doctrine)
✅ Database migrations create all tables successfully
✅ Default scopes seeded (openid, profile, email, offline_access)
✅ All 3 grant types working (authorization_code, client_credentials, refresh_token)
✅ PKCE support functional (optional, not enforced)
✅ User registration and login endpoints functional
✅ Scope-based authorization enforced on API endpoints
✅ Token revocation working
✅ Token introspection working
✅ Client management endpoints working (admin only)
✅ All unit tests passing (76 tests minimum)
✅ All integration tests passing (3 test files minimum)
✅ Manual testing of all flows successful
✅ Cleanup command removes expired tokens
✅ Security configuration properly integrated

---

## Post-Implementation Notes

**Security considerations:**
- Client secrets are bcrypt-hashed (never stored in plain text)
- Tokens use cryptographically secure random generation
- Refresh tokens are rotated on use (old token revoked)
- PKCE is optional but supported for enhanced security
- Scope enforcement prevents unauthorized access

**Performance considerations:**
- Indexes on all token tables for efficient lookups
- Pessimistic locking prevents race conditions
- Cleanup command should run daily via cron
- Consider adding rate limiting to token endpoint (future enhancement)

**Future enhancements (out of scope):**
- JWT token format (currently opaque tokens)
- OpenID Connect full implementation (currently basic OAuth2)
- Token encryption at rest
- Rate limiting per client
- Consent screen UI for authorization endpoint
- OAuth2 scope discovery endpoint
