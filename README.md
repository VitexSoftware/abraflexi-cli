# AbraFlexi CLI

A powerful, Symfony-based Command Line Interface for [AbraFlexi](https://www.abraflexi.eu/), allowing you to interact with your accounting data directly from the terminal. This tool is built on top of the robust [spojenet/flexibee](https://github.com/Spoje-NET/php-abraflexi) library.

## Features

- 🏢 **Multi-company Support**: Easily list and switch between available companies.
- 📂 **Evidence Discovery**: Browse all available API endpoints (evidences) on your server.
- 🔍 **Record Inspection**: List records from any evidence with custom columns and limits.
- 📄 **Detail View**: Show full details for specific records.
- 🔐 **Secure Configuration**: Uses `.env` files for managing server credentials.

## Installation

Ensure you have [Composer](https://getcomposer.org/) installed.

1. **Clone the repository**:

    ```bash
    git clone https://github.com/VitexSoftware/abraflexi-cli.git
    cd abraflexi-cli
    ```

2. **Install dependencies**:

    ```bash
    composer install
    ```

## Deb package repository

<https://repo.vitexsoftware.com/>

```bash
sudo curl -fsSL http://repo.vitexsoftware.com/KEY.gpg -o /usr/share/keyrings/vitexsoftware-archive-keyring.gpg

echo "deb [signed-by=/usr/share/keyrings/vitexsoftware-archive-keyring.gpg] http://repo.vitexsoftware.com/debian/ trixie main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list

sudo apt update

sudo apt install abraflexi-cli  
```

note: Change trixie to your debian/ubuntu distribution name (bookworm, jammy, sid, etc. )

## Configuration

The CLI uses environment variables for authentication. Create a `.env` file in the project root:

```env
ABRAFLEXI_URL=https://demo.flexibee.eu:5434
ABRAFLEXI_LOGIN=winstrom
ABRAFLEXI_PASSWORD=winstrom
ABRAFLEXI_COMPANY=demo_de
```

*Note: The tool supports both `ABRAFLEXI_LOGIN` and `ABRAFLEXI_USER` variables.*

### Custom Environment File

You can specify a custom environment file using the `--envfile` global option:

```bash
bin/abraflexi-cli --envfile=/path/to/your.env list-companies
```

## Usage

The main executable is located at `bin/abraflexi-cli`.

### General Help

```bash
bin/abraflexi-cli list
```

### 1. List Available Companies

Get a list of all companies accessible on the server:

```bash
bin/abraflexi-cli list-companies
```

### 2. List Evidences

List all available evidences (endpoints) and their descriptions:

```bash
bin/abraflexi-cli list-evidences
```

### 3. List Records from Evidence

List records from a specific evidence (e.g., `adresar` for Addresses):

```bash
bin/abraflexi-cli record adresar list
```

**Common Options**:

- `--columns` or `-c`: Comma-separated list of columns to display (default: `id,kod,nazev`).
- `--limit` or `-l`: Number of records to retrieve (default: `20`).
- `--start` or `-s`: Offset for pagination.
- `--order` or `-o`: Sorting (e.g., `nazev@A` for ascending, `nazev@D` for descending).
- `--filter` or `-f`: Filtering query (e.g., `nazev BEGINS 'A'`).
- `--detail` or `-d`: Level of detail (`summary`, `full`, `id`, `mini`, `custom`).

**Examples**:

- **Filtering & Limiting**:

  ```bash
  bin/abraflexi-cli record adresar list --filter "nazev BEGINS 'A'" --limit 5
  ```

- **Sorting**:

  ```bash
  bin/abraflexi-cli record faktura-vydana list --order sumCelkem@D --columns id,kod,sumCelkem
  ```

- **Pagination**:

  ```bash
  bin/abraflexi-cli record adresar list --limit 10 --start 20
  ```

### 4. Show Record Details

Show all fields for a specific record by its ID or Code:

```bash
bin/abraflexi-cli record adresar show "code:AAA"
```

## Requirements

- PHP 8.1 or higher
- Composer
- Access to an AbraFlexi server

## Development

The project is structured following PSR-4 autoloading:

- `bin/abraflexi-cli`: CLI entry point.
- `src/Command/`: Symfony Console command implementations.
- `src/Command/BaseCommand.php`: Shared logic for authentication.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---
Created by [Vitex Software](https://www.vitexsoftware.cz/).
