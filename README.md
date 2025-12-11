# Sidworks Composer Patcher

This composer plugin automatically applies all patch files in your project. It works with PHP project using Composer.

- All `*.patch` files in the root `patches` directory are applied every time `composer install` is run.
- In development mode (Composer run without `--no-dev`), it also applies `*.patch.dev` files.
- If any patch fails, `composer install` will exit with an error.

## Installation

```bash
composer require sidworks/composer-patcher
```
