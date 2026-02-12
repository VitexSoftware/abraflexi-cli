# AGENTS.md

This file provides guidance to AI coding agents (GitHub Copilot, Warp, Claude, etc.) when working with code in this repository.

## Project Overview

AbraFlexi CLI is a command-line interface tool for interacting with the Czech economic system AbraFlexi. It provides commands for listing, viewing, and creating records in various AbraFlexi evidences (entities).

## Development Commands

### Package Management
```bash
composer install          # Install dependencies
composer update           # Update dependencies
```

### Running the CLI
```bash
./bin/abraflexi-cli                              # Show available commands
./bin/abraflexi-cli record banka list            # List bank records
./bin/abraflexi-cli record faktura-vydana show 1 # Show invoice details
./bin/abraflexi-cli record banka create --data '{"typPohybuK":"typPohybu.prijem"}'
```

### Code Quality
```bash
vendor/bin/php-cs-fixer fix                    # Fix code style issues
vendor/bin/php-cs-fixer fix --dry-run         # Check code style without fixing
vendor/bin/phpstan analyze                     # Run static analysis
```

### Testing
```bash
vendor/bin/phpunit                            # Run all tests
vendor/bin/phpunit tests/                     # Run tests in directory
```

## Core Architecture

### Command Structure

Commands are located in `src/Command/` and extend `BaseCommand`:

- **`BaseCommand`**: Base class providing AbraFlexi connection options from environment variables
- **`RecordCommand`**: Main command for CRUD operations on evidence records
- **`PropertiesHelper`**: Helper class for loading evidence field properties

### AbraFlexi Library Integration

This CLI uses the `spojenet/flexibee` library:

- **`AbraFlexi\RO`**: Read-only operations (list, show)
- **`AbraFlexi\RW`**: Read-write operations (create, update, delete)
- **`RO::getColumnsInfo($evidence)`**: Get field properties for an evidence (offline or online)
- **`RW::insertToAbraFlexi($data)`**: Create new records

### Evidence Field Properties

Field properties are obtained via `RO::getColumnsInfo()` which uses:
- Static JSON files from `vendor/spojenet/flexibee/static/Properties.{evidence}.json`
- Or live API endpoint `/{company}/{evidence}/properties.json`

Key field properties:
- `mandatory`: "true"/"false" - whether field is required
- `isWritable`: "true"/"false" - whether field can be written
- `type`: field type (string, integer, numeric, date, datetime, select, relation, logic)
- `values`: for select fields, contains allowed values

### Common Evidences

- `banka` - Bank records (payments)
- `faktura-vydana` - Issued invoices
- `faktura-prijata` - Received invoices  
- `adresar` - Address book (contacts)
- `pokladna` - Cash register

## Configuration

### Environment Variables
```bash
ABRAFLEXI_URL=https://demo.flexibee.eu    # AbraFlexi server URL
ABRAFLEXI_LOGIN=winstrom                   # Username (or ABRAFLEXI_USER)
ABRAFLEXI_PASSWORD=winstrom                # Password
ABRAFLEXI_COMPANY=demo                     # Company identifier
```

### .env File
The CLI loads `.env` files automatically from:
1. Path specified by `--envfile=` argument
2. Project root directory
3. Current working directory

## Code Standards

### From Project Conventions:
- **PHP Version**: PHP 8.1+
- **Code Style**: PSR-12 coding standard (enforced by php-cs-fixer)
- **Documentation**: Include docblocks for all functions and classes
- **Type Hints**: Always include type hints for parameters and return types
- **Internationalization**: Use `_()` functions for translatable strings
- **Error Handling**: Handle exceptions with meaningful messages

### Command Implementation Pattern
```php
class NewCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('command-name')
            ->setDescription('Command description')
            ->addArgument('arg', InputArgument::REQUIRED, 'Argument description')
            ->addOption('opt', 'o', InputOption::VALUE_OPTIONAL, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->getAbraFlexiOptions();
        // ... implementation
        return Command::SUCCESS;
    }
}
```

## Project Structure

```
bin/
└── abraflexi-cli           # CLI entry point

src/Command/
├── BaseCommand.php         # Base command with connection setup
├── ListCompaniesCommand.php
├── ListEvidencesCommand.php
├── PropertiesHelper.php    # Evidence field properties helper
├── RecordCommand.php       # Main record CRUD command
└── StatusCommand.php

tests/                      # PHPUnit tests
vendor/spojenet/flexibee/   # AbraFlexi library
  └── static/               # Offline evidence properties JSON files
```

## Important Implementation Notes

### Creating Records
When creating records via `record create`:
1. Use `PropertiesHelper::getMandatoryFields()` to get required fields
2. Warn user about missing mandatory fields before creation
3. Use `--force` flag to allow creation with missing fields
4. Use `--dry-run` flag to test without actual creation

### Field Types for Data Input
- `string`: Plain text value
- `date`: Format YYYY-MM-DD
- `datetime`: Format YYYY-MM-DD HH:MM:SS
- `numeric`: Decimal number (use . as separator)
- `integer`: Whole number
- `logic`: "true" or "false"
- `select`: Use key from allowed values (e.g., "typPohybu.prijem")
- `relation`: Reference by code (e.g., "code:BANK") or ID

### Bank Record (banka) Mandatory Fields
- `kod` - Internal number
- `typPohybuK` - Payment type: `typPohybu.prijem` (incoming) or `typPohybu.vydej` (outgoing)
- `datVyst` - Issue date
- `typDokl` - Document type reference
- `banka` - Bank account reference

### Invoice (faktura-vydana) Mandatory Fields
- `kod` - Internal number
- `varSym` - Variable symbol
- `datVyst` - Issue date

## Dependencies

- **Runtime**: PHP 8.1+, symfony/console, symfony/dotenv, spojenet/flexibee
- **Development**: PHPUnit, PHP-CS-Fixer, PHPStan

## License

MIT License
