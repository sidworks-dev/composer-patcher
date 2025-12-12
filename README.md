# Sidworks Composer Patcher

[![Latest Version](https://img.shields.io/packagist/v/sidworks/composer-patcher.svg)](https://packagist.org/packages/sidworks/composer-patcher)
[![PHP Version](https://img.shields.io/packagist/php-v/sidworks/composer-patcher.svg)](https://packagist.org/packages/sidworks/composer-patcher)
[![License](https://img.shields.io/packagist/l/sidworks/composer-patcher.svg)](LICENSE)

A Composer plugin that automatically applies git-format patches to your project. Useful for patching vendor packages without forking.

## Features

- **Automatic patching** - Patches are applied after every `composer install` and `composer update`
- **Development patches** - Use `.patch.dev` files for patches that only apply in dev mode
- **Organized structure** - Supports subdirectories to organize patches by package
- **Idempotent** - Safely re-applies patches on every run (reverses first, then applies)
- **Whitespace tolerant** - Ignores whitespace differences when applying patches
- **Interactive patch creation** - Generate patches from modified vendor files with a single command
- **Clear reporting** - Grouped output showing success/failure status for all patches

## Installation

```bash
composer require sidworks/composer-patcher
```

## Quick Start

1. Create a `patches` directory in your project root
2. Add `.patch` files (git diff format)
3. Run `composer install`

## Directory Structure

```
your-project/
├── composer.json
├── patches/
│   ├── fix-typo.patch              # Applied always
│   ├── debug-helper.patch.dev      # Applied only in dev mode
│   └── acme/                       # Organize by package
│       └── utils/
│           └── fix-calculation.patch
└── vendor/
```

## Creating Patches

### Using the Built-in Command

The easiest way to create a patch from a modified vendor file:

```bash
composer sidworks:composer-patcher --create
```

This interactive command will:
1. Ask for the file path (e.g., `vendor/acme/utils/src/Calculator.php`)
2. Extract the original file from the package
3. Generate a diff between original and modified versions
4. Ask if you want to save in the package folder (e.g., `patches/acme/utils/`)
5. Save the patch with your chosen filename

Example session:
```
Enter the file path (relative to project root):
> vendor/acme/utils/src/Calculator.php

Save in patches/acme/utils/? [Y/n] y

Enter patch filename [Calculator.php.patch]:
>

✓ Patch created successfully!
Location: patches/acme/utils/Calculator.php.patch
```

### Manually Creating Patches

Generate a patch using git diff:

```bash
# For tracked files
git diff vendor/package/file.php > patches/my-fix.patch

# Or from scratch using diff
diff -u original.php modified.php > patches/my-fix.patch
```

Patches must use git-style headers:

```diff
--- a/vendor/package/src/File.php
+++ b/vendor/package/src/File.php
@@ -10,7 +10,7 @@
     public function example()
     {
-        return 'old';
+        return 'new';
     }
```

## Patch Types

| Extension | Applied When |
|-----------|--------------|
| `.patch` | Always (install and update) |
| `.patch.dev` | Only in dev mode (`composer install` without `--no-dev`) |

## Commands

### Apply Patches Manually

```bash
composer sidworks:composer-patcher
```

Runs the patcher manually (useful for testing). Always runs in dev mode.

### Create a Patch

```bash
composer sidworks:composer-patcher --create
# or
composer sidworks:composer-patcher -c
```

## How It Works

1. On `composer install` or `composer update`, the plugin activates
2. All existing patches are reversed (to handle updates cleanly)
3. Patches are re-applied in alphabetical order
4. Results are displayed grouped by folder
5. If any patch fails, Composer exits with error code 1

## Requirements

- PHP 8.0+
- Composer 2.x
- Git (for applying patches)

## License

MIT
