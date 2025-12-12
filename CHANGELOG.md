# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.6] - 12-12-2025

### Changed

- Cleaner output when no patches found (skips patch application entirely)

## [1.0.5] - 12-12-2025

### Added

- Custom display name in output (instead of showing package description)

## [1.0.4] - 12-12-2025

### Added

- Packagist badges in README (version, PHP, license)
- Keywords for better Packagist discoverability
- Homepage and support links in composer.json
- PHP ^8.0 requirement in composer.json
- Composer plugin API ^2.0 requirement

### Changed

- Improved package description

## [1.0.3] - 12-12-2025

### Changed

- Improved patch creation flow with Y/n prompt for package folder structure
- Suggests filename based on modified file

### Fixed

- Better error handling for empty input

## [1.0.2] - 12-12-2025

### Added

- Interactive patch creation command (`composer sidworks:composer-patcher --create`)
- Auto-creates patches directory if missing
- Suggests package folder structure for organizing patches
- Input validation with retry prompts

## [1.0.1] - 11-12-2025

### Changed

- Performance improvements for patch discovery
- Grouped output display by folder

## [1.0.0] - 11-12-2025

### Added

- Automatic patching on `composer install` and `composer update`
- Support for `.patch` and `.patch.dev` files
- Recursive directory support for organizing patches
- Idempotent patch application (reverse and reapply)
- Whitespace-tolerant patching
