#!/bin/bash

set -e

export ABRAFLEXI_URL=https://demo.flexibee.eu
export ABRAFLEXI_LOGIN=winstrom
export ABRAFLEXI_PASSWORD=winstrom
export ABRAFLEXI_COMPANY=demo

# Verify connection and company state
/usr/bin/abraflexi-cli status

# List available companies
/usr/bin/abraflexi-cli list-companies

# List available evidences
/usr/bin/abraflexi-cli list-evidences

# List bank records (default columns, default limit)
/usr/bin/abraflexi-cli record banka list

# List address book with custom columns
/usr/bin/abraflexi-cli record adresar list --columns=id,kod,nazev --limit=5

# Show all fields of the first bank record
/usr/bin/abraflexi-cli record banka show 1 || true

# Dry-run: create an incoming bank payment
/usr/bin/abraflexi-cli record banka create \
    --dry-run \
    --typPohybuK=typPohybu.prijem \
    --kod=TEST123 \
    --datVyst=2023-01-01 \
    --typDokl=code:BANK \
    --banka=code:BANK

# Dry-run: create an issued invoice
/usr/bin/abraflexi-cli record faktura-vydana create \
    --dry-run \
    --kod=TESTINV \
    --varSym=123456 \
    --datVyst=2023-01-01
