# Changelog

All notable changes to `laravel-idempotency` will be documented in this file.

## [1.0.0] - 2026-01-09

### Added
- Initial release
- Stripe-style idempotency for Laravel APIs
- Idempotent job middleware for queues
- Redis-based distributed locking
- Database persistence for responses
- Configurable payload validation
- Automatic middleware registration
- Migration publishing
- Comprehensive documentation
- Example usage files
- Support for Laravel 10.x and 11.x
- PHP 8.1, 8.2, and 8.3 support

### Features
- HTTP idempotency middleware
- Queue job idempotency middleware
- Payload hash validation
- Response caching (Redis + Database)
- Distributed lock management
- Concurrent request handling
- Automatic cleanup helpers
- Configurable TTLs
- GET/HEAD/OPTIONS request skipping
- Custom header support
