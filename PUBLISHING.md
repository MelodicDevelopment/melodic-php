# Publishing Melodic Framework to Packagist

This is a step-by-step guide for publishing `melodic/framework` so anyone can install it with:

```bash
composer require melodic/framework
```

## Prerequisites

- A GitHub account (you already have the repo at https://github.com/MelodicDevelopment/melodic-php)
- The repo pushed to GitHub with all changes committed

## Step 1: Create a Packagist Account

1. Go to https://packagist.org
2. Click **Sign up** (top right)
3. Sign up with your GitHub account (easiest) or email/password
4. Verify your email if prompted

## Step 2: Submit the Package

1. Log in to Packagist
2. Click **Submit** in the top navigation
3. Paste your repository URL: `https://github.com/MelodicDevelopment/melodic-php`
4. Click **Check** — Packagist reads your `composer.json` and shows the package name (`melodic/framework`)
5. Click **Submit**

Your package is now live at `https://packagist.org/packages/melodic/framework`.

## Step 3: Set Up Auto-Update (GitHub Webhook)

This makes Packagist automatically pick up new tags and commits.

1. On your Packagist package page, go to **Settings**
2. Copy the **API Token** shown there
3. Go to your GitHub repo → **Settings** → **Webhooks** → **Add webhook**
4. Set:
   - **Payload URL**: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
   - **Content type**: `application/json`
   - **Secret**: paste the API token from step 2
   - **Events**: select "Just the push event"
5. Click **Add webhook**

Alternatively, Packagist now supports connecting directly via GitHub OAuth — check if there's a "Connect to GitHub" button on your Packagist profile settings, which handles this automatically.

## Step 4: Tag Your First Release

Packagist uses git tags as version numbers. Without tags, users can only install `dev-main` (unstable).

```bash
# Make sure everything is committed and pushed
git status

# Create a version tag
git tag v1.0.0

# Push the tag to GitHub
git push origin v1.0.0
```

Packagist picks up the tag within a few minutes (instantly if the webhook is set up).

### Choosing a Version Number

Follow [Semantic Versioning](https://semver.org/):

| Version | When to use |
|---------|-------------|
| `v0.1.0` | Early development, API may change at any time |
| `v1.0.0` | First stable release, you're committing to the public API |
| `v1.1.0` | Added new features, backward compatible |
| `v1.0.1` | Bug fixes only, backward compatible |
| `v2.0.0` | Breaking changes to existing API |

If you're not ready to commit to a stable API, start with `v0.x` (e.g., `v0.1.0`). This signals to users that things may change.

## Step 5: Verify It Works

From any other project directory:

```bash
mkdir /tmp/test-melodic && cd /tmp/test-melodic
composer init --no-interaction
composer require melodic/framework
```

You should see Composer download `melodic/framework` and `firebase/php-jwt`. Then verify:

```php
<?php
// test.php
require 'vendor/autoload.php';

$app = new Melodic\Core\Application(__DIR__);
echo "Melodic loaded successfully!\n";
```

```bash
php test.php
```

Clean up:

```bash
rm -rf /tmp/test-melodic
```

## Ongoing: Publishing New Versions

Each time you want to release:

```bash
# Commit your changes
git add -A && git commit -m "Your changes"
git push

# Tag and push the new version
git tag v1.1.0
git push origin v1.1.0
```

That's it. Packagist picks up the new tag and users get it on their next `composer update`.

## What Users See

After publishing, your Packagist page shows:

- **Package name**: melodic/framework
- **Description**: from composer.json
- **Homepage**: https://php.melodic.dev
- **Versions**: each git tag
- **Requirements**: PHP >=8.2, firebase/php-jwt
- **Install count**: updated as people download it
- **Stars**: linked from GitHub

## What Gets Installed

The `.gitattributes` file ensures users only receive the essential files:

| Included | Excluded (export-ignore) |
|----------|--------------------------|
| `src/` | `tests/` |
| `composer.json` | `web/` |
| `LICENSE` | `docs/` |
| `README.md` | `phpunit.xml`, `phpstan.neon` |
| | `CLAUDE.md`, `PUBLISHING.md` |
| | `.github/`, `.gitignore`, `.gitattributes` |

## Quick Reference

| Action | Command |
|--------|---------|
| Validate package | `composer validate --strict` |
| Tag a release | `git tag v1.0.0 && git push origin v1.0.0` |
| List tags | `git tag -l` |
| Delete a tag (local + remote) | `git tag -d v1.0.0 && git push origin :refs/tags/v1.0.0` |
| Install from Packagist | `composer require melodic/framework` |
| Install specific version | `composer require melodic/framework:^1.0` |
| Install dev branch (no tag) | `composer require melodic/framework:dev-main` |
