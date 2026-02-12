#!/bin/bash

set -e

export ABRAFLEXI_URL=https://demo.flexibee.eu
export ABRAFLEXI_LOGIN=winstrom
export ABRAFLEXI_PASSWORD=winstrom
export ABRAFLEXI_COMPANY=demo

# Test list bank records
/usr/bin/abraflexi-cli record banka list

# Test list address book
/usr/bin/abraflexi-cli record adresar list

# Test create incoming payment (dry run)
/usr/bin/abraflexi-cli record banka create --dry-run --typPohybuK=typPohybu.prijem --kod=TEST123 --datVyst=2023-01-01 --typDokl=code:BANK --banka=code:BANK

# Test create issued invoice (dry run)
/usr/bin/abraflexi-cli record faktura-vydana create --dry-run --kod=TESTINV --varSym=123456 --datVyst=2023-01-01