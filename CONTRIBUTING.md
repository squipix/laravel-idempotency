# Contributing to Laravel Idempotency

Thank you for considering contributing to Laravel Idempotency! This document outlines the contribution process.

## Code of Conduct

Please be respectful and constructive in all interactions. We are committed to providing a welcoming and inclusive environment.

## How to Contribute

### Reporting Bugs

If you discover a bug, please create an issue on GitHub with:
- A clear descriptive title
- Detailed steps to reproduce
- Expected vs actual behavior
- Laravel and PHP versions
- Any relevant logs or error messages

### Suggesting Features

Feature suggestions are welcome! Please create an issue with:
- Clear description of the feature
- Use cases and benefits
- Potential implementation approach (if applicable)

### Pull Requests

1. **Fork the repository** and create a new branch from `main`
2. **Follow coding standards** (PSR-12)
3. **Write tests** for new features or bug fixes
4. **Update documentation** if you change functionality
5. **Keep PRs focused** - one feature or fix per PR
6. **Write clear commit messages** following [Conventional Commits](https://www.conventionalcommits.org/)

#### Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

Examples:
```
feat(middleware): add support for custom idempotency headers

fix(service): resolve race condition in lock acquisition

docs(readme): update installation instructions
```

### Development Setup

1. **Clone your fork:**
   ```bash
   git clone https://github.com/squipix/laravel-idempotency.git
   cd laravel-idempotency
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up testing environment:**
   ```bash
   cp .env.example .env
   # Configure your test database and Redis
   ```

4. **Run tests:**
   ```bash
   composer test
   ```

### Code Style

This project follows PSR-12 coding standards. Before submitting a PR:

```bash
# Check code style
composer check-style

# Fix code style automatically
composer fix-style
```

### Testing Guidelines

- Write tests for all new features
- Ensure all tests pass before submitting PR
- Aim for high code coverage (>80%)
- Include both unit and integration tests where applicable

#### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/IdempotencyServiceTest.php
```

### Documentation

When adding new features:
- Update README.md if user-facing
- Add examples in `/examples` directory
- Update CHANGELOG.md (under "Unreleased")
- Add PHPDoc blocks for all public methods
- Include code examples in documentation

### Pull Request Process

1. **Create PR** with clear title and description
2. **Reference issues** (e.g., "Fixes #123")
3. **Wait for CI** checks to pass
4. **Address review feedback** promptly
5. **Squash commits** before merge if requested

#### PR Checklist

- [ ] Tests added/updated and passing
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Code follows PSR-12 standards
- [ ] No breaking changes (or clearly documented)
- [ ] Commit messages follow conventions
- [ ] Branch is up to date with main

### Security Vulnerabilities

**Do not** create public issues for security vulnerabilities. Instead, email security@squipix.com with:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We will respond within 48 hours and work with you to resolve the issue.

## Development Workflow

### Branch Naming

- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring

### Release Process

1. Update CHANGELOG.md with version and date
2. Update version badge in README.md
3. Tag release: `git tag v1.2.3`
4. Push tags: `git push --tags`
5. Create GitHub release with changelog notes

## Project Structure

```
laravel-idempotency/
├── config/              # Configuration files
├── database/
│   └── migrations/      # Database migrations
├── examples/            # Usage examples
├── src/
│   ├── Console/         # Artisan commands
│   ├── Contracts/       # Interfaces
│   ├── Jobs/            # Job middleware
│   ├── Metrics/         # Metrics collectors
│   ├── Middleware/      # HTTP middleware
│   └── Services/        # Core services
└── tests/
    ├── Unit/            # Unit tests
    └── Integration/     # Integration tests
```

## Getting Help

- **Documentation:** Check README.md, INSTALLATION.md, and other docs
- **Issues:** Search existing issues before creating new ones
- **Discussions:** Use GitHub Discussions for questions
- **Email:** support@squipix.com

## Recognition

Contributors will be recognized in:
- CHANGELOG.md for their contributions
- GitHub contributors page
- Release notes

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for making Laravel Idempotency better! 🚀
