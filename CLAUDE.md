# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

This project uses [Castor](https://castor.jolicode.com/) (`castor.php` at the repo root) to drive the Dockerized dev environment. Run `castor` with no arguments (or `castor help <task>`) to list all available tasks.

### Setup & Lifecycle
```bash
# Build the image and install the app (composer install / skeleton scaffolding as needed)
castor install

# Start / stop / restart the stack
castor start
castor stop
castor restart

# Follow logs (defaults to the php service)
castor logs
castor logs node

# Shell into the PHP container
castor sh
```

### Running Commands in Containers
```bash
# Symfony console
castor console --cmd="debug:router"

# Composer
castor composer --cmd="require foo/bar"

# Doctrine migrations
castor migrate

# Sylius fixtures (this repo is a Sylius plugin, so PROJECT_TYPE=sylius-addon)
castor fixtures
castor fixtures --suite=my_suite

# Clear the Symfony cache
castor cache

# Generate gitignored keys (JWT / Sylius payment encryption key)
castor keys
```

### Frontend Assets
This plugin skeleton builds its front end through `vendor/sylius/test-application` and installs assets relative to it:
```bash
castor assets_plugin_skeleton
```
(`castor assets` is the generic one-shot Node build for a standalone app and is not the one used by this plugin skeleton.)

### Testing
```bash
# PHPUnit tests
castor sh
vendor/bin/phpunit

# Behat tests (non-JS)
castor sh
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"

# Behat tests (JS scenarios) — requires Chrome headless and a running Symfony server
castor sh
APP_ENV=test symfony server:start --port=8080 --daemon
vendor/bin/behat --strict --tags="@javascript,@mink:chromedriver"
```

### Code Quality
```bash
castor sh
# PHPStan analysis
vendor/bin/phpstan analyse -c phpstan.neon -l max src/

# Coding standards
vendor/bin/ecs check
```

### Toggles & Monitoring
```bash
# Toggle Xdebug (off | debug | profile | coverage) — rebuilds the image
castor xdebug --mode=debug
castor xdebug --mode=off

# Switch FrankenPHP between classic and worker mode
castor worker on
castor worker off

# Ember TUI dashboard for FrankenPHP monitoring (Linux hosts only, not Docker Desktop/macOS)
castor ember

# Apache Bench against the container over the internal Docker network
castor benchmark --requests=200 --concurrency=10 --path=/
```

## Architecture

This is a **Sylius Plugin Skeleton** - a template for creating Sylius e-commerce plugins. It provides a complete development environment with both traditional and Docker setups.

### Core Structure
- **Main Plugin Class**: `src/CylleneDigitalSyliusAdvancedTaxonPlugin.php` - Entry point using `SyliusPluginTrait`
- **DI Extension**: `src/DependencyInjection/CylleneDigitalSyliusAdvancedTaxonExtension.php` - Handles service loading and Doctrine migrations
- **Services**: `config/services.yaml` - Service definitions with XML configuration
- **Routes**: `config/routes/` - Separate admin and shop route definitions
- **Templates**: `templates/` - Twig templates for admin and shop with Twig hooks support

### Key Features
- **Test Application**: Uses `sylius/test-application` for plugin testing in isolation
- **Asset Management**: Webpack Encore for frontend asset compilation
- **Database**: Doctrine migrations with proper namespace handling
- **Testing**: Full Behat + PHPUnit setup with browser testing support
- **Code Quality**: PHPStan, ECS (Easy Coding Standard), and Rector integration

### Development Environment
- **Castor**: `castor.php` orchestrates the whole containerized environment (PHP/FrankenPHP, Node.js, database) — install, lifecycle, console/composer passthrough, migrations, fixtures, cache, and toggles (Xdebug, FrankenPHP worker mode) all go through `castor <task>`
- **Docker Compose**: underlying containers are defined under `.docker/` (`compose.yaml`, plus `compose.linux.yaml` merged in automatically on Linux hosts); Castor manages `COMPOSE_FILE`, `.docker/.env`, and UID/GID so the setup is portable across macOS and Linux without manual patching
- **Frontend**: Yarn-based asset pipeline through `vendor/sylius/test-application`, built one-shot via the Node container with `castor assets_plugin_skeleton`

### Testing Strategy
- **Unit/Integration**: PHPUnit for isolated component testing
- **Functional**: Behat for feature testing with browser automation
- **Static Analysis**: PHPStan for type checking and code quality
- **Standards**: ECS for coding standard enforcement

### Database Configuration
Database credentials should be configured in:
- `tests/TestApplication/.env` (for development)
- `tests/TestApplication/.env.test` (for testing)
