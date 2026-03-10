# Packagist Publishing Checklist

## Pre-Publishing

- [x] composer.json configured with proper metadata
- [x] README.md with badges and clear documentation
- [x] LICENSE file (MIT)
- [x] CHANGELOG.md with version history
- [x] .gitignore configured
- [x] .gitattributes for export-ignore
- [x] CONTRIBUTING.md for contributors
- [x] SECURITY.md for vulnerability reporting
- [x] Examples directory with usage examples
- [x] Comprehensive documentation files

## Code Quality

- [x] PSR-4 autoloading configured
- [x] PSR-12 coding standards
- [x] .editorconfig for consistent formatting
- [x] .php-cs-fixer.php configuration
- [x] phpstan.neon for static analysis
- [x] phpunit.xml.dist for testing

## GitHub Actions

- [x] tests.yml - Automated testing
- [x] phpstan.yml - Static analysis
- [x] code-style.yml - Code style checking

## Documentation

- [x] README.md - Main documentation (470 lines)
- [x] INSTALLATION.md - Step-by-step installation
- [x] QUICKSTART.md - Quick start guide
- [x] OVERVIEW.md - Architecture overview
- [x] METRICS.md - Metrics documentation (500+ lines)
- [x] OPENAPI.md - API documentation guide
- [x] openapi.yaml - OpenAPI 3.0 specification

## Publishing Steps

### 1. Initialize Git Repository

```bash
git init
git add .
git commit -m "Initial commit: Laravel Idempotency v1.0.0"
```

### 2. Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `laravel-idempotency`
3. Description: `Stripe-style idempotency for Laravel APIs and queues`
4. Make it **Public**
5. **Don't** initialize with README
6. Click "Create repository"

### 3. Push to GitHub

```bash
git remote add origin https://github.com/YOUR-USERNAME/laravel-idempotency.git
git branch -M main
git push -u origin main
```

### 4. Create Release Tag

```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

### 5. Create GitHub Release

1. Go to repository > Releases
2. Click "Create a new release"
3. Choose tag: v1.0.0
4. Release title: `v1.0.0 - Initial Release`
5. Description: Copy from CHANGELOG.md
6. Click "Publish release"

### 6. Submit to Packagist

1. Go to https://packagist.org/
2. Login or create account
3. Click "Submit" in top navigation
4. Enter repository URL: `https://github.com/YOUR-USERNAME/laravel-idempotency`
5. Click "Check"
6. If validation passes, click "Submit"

### 7. Configure Auto-Updates

**GitHub Webhook (Recommended):**

1. Go to your Packagist package page
2. Click "Settings" tab
3. Copy the webhook URL and token
4. Go to GitHub repository: Settings > Webhooks
5. Click "Add webhook"
6. Configure:
   - Payload URL: `[Packagist webhook URL]`
   - Content type: `application/json`
   - Secret: `[Packagist token]`
   - Which events: "Just the push event"
7. Click "Add webhook"
8. Test the webhook

**Alternative - GitHub Service Integration:**

1. On Packagist, go to Settings tab
2. Enable GitHub integration
3. Authorize Packagist on GitHub
4. Auto-updates will be configured

### 8. Verify Installation

Test in a fresh Laravel project:

```bash
composer create-project laravel/laravel test-project
cd test-project
composer require squipix/laravel-idempotency
```

## Post-Publishing

- [ ] Test package installation
- [ ] Verify badges on README show correctly
- [ ] Check Packagist page displays properly
- [ ] Test auto-update webhook
- [ ] Announce on social media/forums
- [ ] Add to Laravel News (https://laravel-news.com/submit)
- [ ] Add to Awesome Laravel lists
- [ ] Create demo application (optional)

## Package URLs

After publishing, your package will be available at:

- **Packagist**: https://packagist.org/packages/squipix/laravel-idempotency
- **GitHub**: https://github.com/YOUR-USERNAME/laravel-idempotency

## Installation Command

Users will install with:

```bash
composer require squipix/laravel-idempotency
```

## Maintenance

### Releasing Updates

1. Update CHANGELOG.md
2. Update version in documentation if needed
3. Commit changes
4. Create new tag:
   ```bash
   git tag -a v1.1.0 -m "Version 1.1.0"
   git push origin v1.1.0
   ```
5. Create GitHub release
6. Packagist will auto-update via webhook

### Semantic Versioning

Follow [semver.org](https://semver.org):

- **MAJOR** (v2.0.0): Breaking changes
- **MINOR** (v1.1.0): New features, backward compatible
- **PATCH** (v1.0.1): Bug fixes, backward compatible

### Support

- Respond to issues promptly
- Review and merge pull requests
- Update documentation as needed
- Keep dependencies up to date

## Troubleshooting

### Packagist Not Updating

1. Check webhook delivery in GitHub
2. Manually update on Packagist (Update button)
3. Verify tag was pushed: `git push --tags`

### Installation Fails

1. Check composer.json syntax
2. Verify Laravel version constraints
3. Check PSR-4 namespace matches directory structure

### Badge Not Showing

1. Wait a few minutes for cache
2. Verify package name in badge URL
3. Check package is published and public

## Success Indicators

✅ Package appears on Packagist
✅ Badges display correctly
✅ Installation works via Composer
✅ GitHub Actions pass
✅ Documentation renders properly
✅ Examples run without errors

---

**Ready to publish!** 🚀

Your package includes:
- Complete source code
- Comprehensive documentation
- Testing infrastructure
- CI/CD workflows
- Contributing guidelines
- Security policy
- MIT License

**Next step**: Follow the publishing steps above to make it available on Packagist.
