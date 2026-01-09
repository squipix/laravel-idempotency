#!/usr/bin/env bash

# Publishing to Packagist Guide
# =============================

echo "Laravel Idempotency - Packagist Publishing Guide"
echo "================================================"
echo ""

# Step 1: Initialize Git Repository
echo "Step 1: Initialize Git Repository"
echo "-----------------------------------"
echo "If not already initialized:"
echo "  git init"
echo "  git add ."
echo "  git commit -m \"Initial commit: Laravel Idempotency v1.0.0\""
echo ""

# Step 2: Create GitHub Repository
echo "Step 2: Create GitHub Repository"
echo "---------------------------------"
echo "1. Go to https://github.com/new"
echo "2. Repository name: laravel-idempotency"
echo "3. Description: Stripe-style idempotency for Laravel APIs and queues"
echo "4. Public repository"
echo "5. Do NOT initialize with README (we already have one)"
echo "6. Click 'Create repository'"
echo ""

# Step 3: Push to GitHub
echo "Step 3: Push to GitHub"
echo "----------------------"
echo "Run these commands:"
echo "  git remote add origin https://github.com/YOUR-USERNAME/laravel-idempotency.git"
echo "  git branch -M main"
echo "  git push -u origin main"
echo ""

# Step 4: Create Git Tag
echo "Step 4: Create Git Tag"
echo "----------------------"
echo "  git tag -a v1.0.0 -m \"Release version 1.0.0\""
echo "  git push origin v1.0.0"
echo ""

# Step 5: Submit to Packagist
echo "Step 5: Submit to Packagist"
echo "---------------------------"
echo "1. Go to https://packagist.org/"
echo "2. Login or create an account"
echo "3. Click 'Submit' in the top navigation"
echo "4. Enter your repository URL:"
echo "   https://github.com/YOUR-USERNAME/laravel-idempotency"
echo "5. Click 'Check'"
echo "6. If validation passes, click 'Submit'"
echo ""

# Step 6: Setup Auto-Updates
echo "Step 6: Setup Auto-Updates"
echo "--------------------------"
echo "GitHub (Recommended):"
echo "1. Go to your Packagist package page"
echo "2. Click 'Settings' tab"
echo "3. Copy the webhook URL"
echo "4. Go to GitHub repository Settings > Webhooks"
echo "5. Add webhook with:"
echo "   - Payload URL: [Packagist webhook URL]"
echo "   - Content type: application/json"
echo "   - Just the push event"
echo "6. Add webhook and test it"
echo ""

# Step 7: Add Badges
echo "Step 7: Add Badges to README"
echo "----------------------------"
echo "Badges are already in README.md:"
echo "  - Latest Version"
echo "  - Total Downloads"
echo "  - License"
echo ""

# Step 8: Verify Installation
echo "Step 8: Verify Installation"
echo "----------------------------"
echo "Test installation in a fresh Laravel project:"
echo "  composer require squipix/laravel-idempotency"
echo ""

# Additional Tips
echo "Additional Tips"
echo "---------------"
echo "1. Create releases on GitHub for each version"
echo "2. Keep CHANGELOG.md updated"
echo "3. Follow semantic versioning (semver.org)"
echo "4. Tag format: v1.0.0, v1.1.0, v2.0.0, etc."
echo "5. Consider enabling Packagist GitHub Actions"
echo ""

# CI/CD Status
echo "CI/CD Setup"
echo "-----------"
echo "GitHub Actions workflows are configured:"
echo "  - tests.yml: Runs PHPUnit tests"
echo "  - phpstan.yml: Static analysis"
echo "  - code-style.yml: Code style checks"
echo ""
echo "These will run automatically on push and PR"
echo ""

echo "================================================"
echo "Your package is ready for Packagist!"
echo "================================================"
echo ""
echo "Quick checklist:"
echo "  [ ] Git repository initialized"
echo "  [ ] Pushed to GitHub"
echo "  [ ] Tagged version v1.0.0"
echo "  [ ] Submitted to Packagist"
echo "  [ ] Webhook configured"
echo "  [ ] Installation tested"
echo ""
echo "Package URL (after submission):"
echo "  https://packagist.org/packages/squipix/laravel-idempotency"
echo ""
