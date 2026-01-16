# 1winpro AI Coding Assistant Instructions

## Project Overview
**1winpro** is a gambling/gaming platform built with Laravel 8 backend, Node.js WebSocket servers for real-time games, and React/Material-UI frontend. It manages users, games, payments, tournaments, and real-time game sessions.

### Architecture Layers
- **Backend**: Laravel 8 (VanguardLTE namespace) with Eloquent ORM, JWT authentication
- **Real-Time**: Node.js WebSocket servers (Server.js, Arcade.js, Slots.js) for live gameplay
- **Frontend**: React with Material-UI (in progress; website/ folder)
- **Infrastructure**: Docker (MySQL, Redis, Apache) with SSL/TLS

## Key Models & Relationships

### Core Entities
- **User**: Base entity with JWT auth trait, manages balance, profile, 2FA (Google Authenticator), roles/permissions
- **Shop**: Multi-tenant support; each shop has API keys, IP restrictions, games, and user pools
- **Game**: Linked to categories; tracks stats, payouts, house edges
- **Transaction**: Financial ledger for all balance changes (bets, payouts, bonuses, withdrawals)
- **Api**: Shop API authentication (keygen, IP-based access control)

### Feature-Specific Models
- **Tournament**, **HappyHour**, **Progress**, **Task**: Time-based gameplay mechanics
- **Payment**, **Withdraw**: Financial operations with state machines (pending→completed)
- **Bonus**: WelcomeBonus, SMSBonus, WheelFortune, RefundBonus (feature flags in User.fillable)
- **SportBet**: Sports betting with leagues, teams, countries

### Database Patterns
- Timestamps are **explicit** (last_login, created_at, not always auto-managed)
- Counts are **denormalized** (count_tournaments in User for performance)
- Boolean features use **string counts** (tournaments: "1" means enabled, "0" disabled)

## Critical Workflows

### API Authentication
1. **Path**: `routes/api.php` → `Http/Controllers/Auth/AuthController@postLogin`
2. **Method**: JWT tokens (tymon/jwt-auth package)
3. **Middleware**: `UseApiGuard` for JWT validation; `ipcheck` for IP whitelisting
4. **API Keys**: Shop API authenticated via `Api` model (keygen + IP check)

### Payment Processing
1. **Initiation**: `Payment::create()` with shop_id, user_id, amount
2. **Processors**: CoinPayment, Coinbase Commerce, SMS.to (custom adapters in `Services/`)
3. **Webhooks**: External processors POST to `/api/webhook/*` endpoints
4. **State**: pending → completed/failed; refunds create reverse transactions

### Real-Time Game Sessions
1. **WebSocket Servers**: Node.js (PTWebSocket/)
2. **Connection**: Socket.io or raw WebSocket (check socket_config2.json in public/)
3. **Flow**: Player authenticates → joins game room → receives bet/spin updates → settlement
4. **Bet Tracking**: Transaction created immediately, settlement updates balance

### User Registration & Onboarding
1. **Gate**: `reg_enabled` setting in `config/settings.php`
2. **Verification**: Email (if use_email=true) or SMS-based
3. **Bonuses**: WelcomeBonus auto-applied if user meets criteria
4. **Referral**: `Invite` model tracks affiliate relationships

## Code Patterns & Conventions

### Models & Repositories
- **Models**: Store in `app/` root (e.g., User.php, Transaction.php)
- **Repositories**: Business logic in `app/Repositories/` (Activity/, Country/, User/, etc.)
- **Transformers**: API response formatting in `app/Transformers/` using Fractal library
- **Example**: [User model](app/User.php) + [UserRepository](app/Repositories/User/) + [UserTransformer](app/Transformers/UserTransformer.php)

### Controllers
- **Web Frontend**: `app/Http/Controllers/Web/Frontend/`
- **API v2**: `app/Http/Controllers/Api/V2/`
- **Pattern**: Inject repositories, return transformed data via Fractal manager

### Services
- **Auth**: JWT tokens in `app/Services/Auth/Api/JWT.php`
- **Logging**: User activity tracking in `app/Services/Logging/UserActivity/Logger.php`
- **Upload**: File handling in `app/Services/Upload/`

### Eloquent Relationships
- Use **named relationships** for clarity (e.g., `$user->shop()`, `$game->category()`)
- Scope queries in models or repositories (not in controllers)
- Eager load relationships to avoid N+1 queries: `User::with('shop', 'roles')`

## Database & State

### Tables & Migrations
- Database: MySQL 5.7 (Docker)
- Migrations in `database/migrations/` (standard Laravel)
- Seeding: `database/seeds/` (use factories for consistency)
- SQL dump: `totalbet365.sql` (import on setup)

### Caching & Sessions
- **Redis**: For session caching, real-time game state
- **Session Storage**: File-based or Redis (config in `config/session.php`)
- **Key Prefix**: Use namespaced keys (e.g., `game:{gameId}:{playerId}`)

## Configuration & Environment

### Key Settings
- `APP_KEY`: Encryption key (generate if missing)
- `DB_HOST`, `DB_PASSWORD`: Docker MySQL credentials (root/rootpassword)
- `REDIS_HOST`: Redis server for caching
- `JWT_SECRET`: Signing key for API tokens
- **Shop Config**: Public JSON files for UI (host, host_ws, shop_id)

### Feature Flags
- `reg_enabled`: Allow new registrations
- `use_email`: Require email verification
- `forgot_password`: Enable password reset
- Use `settings('flag_name')` to check (global helper)

## Deployment & Running

### Development Setup
```bash
docker-compose up --build        # Start all services (MySQL, Redis, PHP/Apache)
docker exec -it app bash         # Enter PHP container
composer install                 # Install PHP deps
php artisan migrate              # Run migrations
php artisan db:seed              # Seed initial data
```

### Game Servers (Node.js)
```bash
npm install pm2 -g               # Process manager
cd PTWebSocket
pm2 start Server.js --watch      # Main game server
pm2 start Arcade.js --watch      # Arcade games
pm2 start Slots.js --watch       # Slot machines
```

### Frontend Build
```bash
npm install
npm run production               # Build React assets (via Laravel Mix)
```

### Ports & Services
- **80/443**: HTTP/HTTPS (Apache)
- **12087, 12053, 12096**: Game servers (WebSocket)
- **3306**: MySQL
- **6379**: Redis

## Testing & Debugging

### Unit Tests
- Framework: PHPUnit
- Config: `phpunit.xml`
- Run: `php artisan test` (or `vendor/bin/phpunit`)
- Test directory: `tests/`

### API Debugging
- **DebugBar**: `barryvdh/laravel-debugbar` (enabled in development)
- **Query Logging**: Check Redis for live game state
- **Logs**: `storage/logs/laravel.log`

### Common Issues
- **SSL Errors**: WebSocket connections need valid certs (in PTWebSocket/ssl/)
- **CORS**: Check middleware in `Http/Kernel.php` for API routes
- **Balance Issues**: Check transactions table; verify decimal precision (not floats)

## When Modifying This Codebase

### Adding a New Game
1. Create `Game` model entry with category_id
2. Add game stats tracking in `GameLog` or `StatGame`
3. Create WebSocket handler in `PTWebSocket/` (if real-time)
4. Add API endpoint in `routes/api.php`
5. Transform response with `GameTransformer`

### Adding a New Payment Method
1. Create processor class in `app/Services/`
2. Implement webhook handler in API controller
3. Update `Payment` state machine in model
4. Add configuration in `config/payments.php`
5. Test with `routes/api.php` webhook endpoint

### User Feature Flags
- Add boolean count property to User model (e.g., `count_newfeature`)
- Add to `$fillable` array
- Check via `user->newfeature == "1"` (strings, not booleans)
- Seed initial value in `database/seeds/`

### Middleware Chain
- Web routes: CSRF + Session + Auth
- API routes: JWT validation + IP checking + Rate limiting
- Custom: Game-specific (`shopzero`, `shop_not_zero`, `checker`)
- Reference: [Kernel.php](app/Http/Kernel.php#L1)

## Files Not to Modify Without Consideration
- `app/User.php` (1276+ lines; core entity with complex state)
- `routes/api.php` (centralized routing; coordinate with team)
- `PTWebSocket/Server.js` (manages live sessions; test thoroughly)
- Migrations (once deployed; use new migrations for schema changes)

## External Integrations
- **Payment Gateways**: CoinPayment, Coinbase Commerce (webhook config required)
- **SMS**: SMS.to provider for OTP and bonus notifications
- **2FA**: Google Authenticator (QR code generation in User)
- **GeoIP**: geoip2/geoip2 for location-based game restrictions

---

**Last Updated**: January 16, 2026 | **Laravel 8** | **Node.js WebSockets** | **React Frontend (In Progress)**
