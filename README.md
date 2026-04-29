# Mobilhotell

Mobilhotell er et enkelt kiosk- og adminsystem for innlevering og utlevering av mobiltelefoner under arrangement.

Systemet er bygget for Raspberry Pi/Linux med PHP, SQLite og direkte ESC/POS-utskrift til kvitteringsskriver.

## Funksjoner

- Innsjekk via QR-kode
- Automatisk tildeling av ledig slot (oppbevaring/lading)
- Utlevering via token og adminfunksjoner
- Adminpanel for drift, aktive innleveringer, skjermtid og slots
- Kvitteringsutskrift med logo og QR-kode via ESC/POS
- Lokal SQLite-database med migrering ved oppstart

## Teknisk oversikt

- Backend: PHP (PDO + SQLite)
- Frontend: Vanlig HTML/CSS/JavaScript
- Database: SQLite-fil i data/mobilhotell.sqlite
- Utskrift: print_receipt.php + mike42/escpos-php

## Krav

- Linux (testet på Raspberry Pi OS)
- Apache2 med PHP 8.2+ (anbefalt 8.4)
- PHP-utvidelser:
  - pdo_sqlite
  - gd
  - mbstring
- Composer
- ESC/POS-kompatibel kvitteringsskriver (for eksempel Citizen CT-E351)
- Skriveren tilgjengelig som /dev/usb/lp0

## Installasjon

### 1. Installer systempakker

```bash
sudo apt update
sudo apt install -y apache2 php php-sqlite3 php-gd php-mbstring composer
```

### 2. Plasser prosjektet i webroot

Eksempel:

```bash
sudo mkdir -p /var/www/mobilhotell
sudo chown -R $USER:$USER /var/www/mobilhotell
# kopier prosjektfilene hit
```

### 3. Installer PHP-avhengigheter

Kjør i prosjektmappen:

```bash
composer install
```

Hvis vendor-mappen ikke finnes i repoet, er dette obligatorisk.

### 4. Sett riktige rettigheter for data-mappen

```bash
mkdir -p data
sudo chown -R www-data:www-data data
sudo chmod -R 775 data
```

### 5. Gi webbruker tilgang til kvitteringsskriver

```bash
sudo usermod -aG lp www-data
sudo systemctl restart apache2
```

Kontroller at webbruker har lp-gruppen:

```bash
id www-data
```

Du skal se lp i gruppelisten.

### 6. (Valgfritt) Test skriver fra webbruker

```bash
sudo -u www-data /usr/bin/php /var/www/mobilhotell/print_receipt.php --session-id=1
```

### 7. Åpne applikasjonen

- Kiosk: http://localhost/index.php
- Admin: http://localhost/admin.php

## Første oppstart

- Databasen opprettes automatisk ved første kall til appen.
- Tabeller og migreringer håndteres i db.php.
- Demo/testdata kan ligge i eksisterende database avhengig av miljø.

## Viktige filer

- index.php: Kioskgrensesnitt
- admin.php: Admingrensesnitt
- admin_api.php: API for adminpanel
- checkin.php: Innsjekk og print-trigger
- checkout.php: Utlevering
- print_receipt.php: Utskriftslayout og ESC/POS-print
- db.php: DB-tilkobling, schema og migreringer

## Feilsøking

### Ingen kvittering ved innlevering

1. Sjekk at webbruker har tilgang til skriver:

```bash
ls -l /dev/usb/lp0
id www-data
```

2. Sjekk loggfil for print:

```bash
tail -n 100 /var/www/mobilhotell/data/print.log
```

3. Sjekk at Apache er restartet etter gruppeendringer:

```bash
sudo systemctl restart apache2
```

### Composer-feil

Kjør:

```bash
composer clear-cache
composer install
```

## Git

Opprett nytt repository og push:

```bash
git init
git add .
git commit -m "Initial commit: Mobilhotell"
```

Hvis repository allerede finnes:

```bash
git add .
git commit -m "Oppdater README med installasjon"
```

## Lisens

Dette prosjektet er lisensiert under MIT-lisensen.

Se LICENSE-filen for full tekst.
