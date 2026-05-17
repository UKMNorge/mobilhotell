# Mobilhotell

Mobilhotell er et enkelt kiosk- og adminsystem for innlevering og utlevering av mobiltelefoner under arrangement.

Systemet er bygget for Linux med PHP, MySQL/MariaDB og direkte ESC/POS-utskrift til kvitteringsskriver.

## Funksjoner

- Innsjekk via QR-kode
- Automatisk tildeling av ledig slot (oppbevaring/lading)
- Utlevering via token og adminfunksjoner
- Adminpanel for drift, aktive innleveringer, skjermtid og slots
- Mulighet i admin for aa tømme skjermtidlogg (historiske utleveringer)
- Kvitteringsutskrift med logo og QR-kode via ESC/POS
- Felles MySQL/MariaDB-database med migrering ved oppstart

## Teknisk oversikt

- Backend: PHP (PDO + MySQL)
- Frontend: Vanlig HTML/CSS/JavaScript
- Database: MySQL/MariaDB (anbefalt: MariaDB 10.6+)
- Utskrift: print_receipt.php + mike42/escpos-php

## Krav

- Linux (desktop)
- Apache2 med PHP 8.2+ (anbefalt 8.4)
- PHP-utvidelser:
  - pdo_mysql
  - gd
  - mbstring
- Composer
- ESC/POS-kompatibel kvitteringsskriver (for eksempel Citizen CT-E351)
- CUPS installert og skriver satt opp som kø (standard: CT-E351)

## Installasjon

### 1. Installer systempakker

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php php-mysql php-gd php-mbstring composer cups cups-client
```

### 1b. Opprett database og bruker (paa hovedserver)

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS mobilhotell CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'mobilhotell'@'localhost' IDENTIFIED BY 'BYTT_TIL_STERKT_PASSORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON mobilhotell.* TO 'mobilhotell'@'localhost'; FLUSH PRIVILEGES;"
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

### 4b. Sett database-miljo for Apache

```bash
sudo tee /etc/apache2/conf-available/mobilhotell-db.conf >/dev/null <<'EOF'
SetEnv MOBILHOTELL_DB_HOST 127.0.0.1
SetEnv MOBILHOTELL_DB_PORT 3306
SetEnv MOBILHOTELL_DB_NAME mobilhotell
SetEnv MOBILHOTELL_DB_USER mobilhotell
SetEnv MOBILHOTELL_DB_PASS BYTT_TIL_STERKT_PASSORD
EOF

sudo a2enconf mobilhotell-db
sudo systemctl restart apache2
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

Hvis kønavnet ikke er CT-E351, sett miljøvariabel for Apache:

```bash
echo 'SetEnv MOBILHOTELL_PRINTER DITT_KONAVN' | sudo tee /etc/apache2/conf-available/mobilhotell-printer.conf
sudo a2enconf mobilhotell-printer
sudo systemctl restart apache2
```

Valgfritt: Du kan overstyre rå-enhet for fallback med `MOBILHOTELL_PRINTER_DEVICE`
(standard er `/dev/usb/lp0`). Dette er nyttig for feilsøking/testing.

### 6. (Valgfritt) Test skriver fra webbruker

```bash
sudo -u www-data /usr/bin/php /var/www/mobilhotell/print_receipt.php --session-id=1
```

### 7. Åpne applikasjonen

- Kiosk: http://localhost/index.php
- Admin: http://localhost/admin.php

## Automatisk USB-backup (anbefalt)

Hvis en USB-pinne alltid er montert paa `/mnt/usb`, oppdaterer systemet automatisk
statusfiler ved hver innlevering og utlevering.

For at webprosessen (`www-data`) skal kunne skrive direkte til USB, maa den mountes
med gruppe `www-data` og gruppe-skrivetilgang.

Eksempel (engangs remount):

```bash
sudo umount /mnt/usb
sudo mount -t exfat -o uid=1000,gid=33,fmask=0002,dmask=0002 /dev/sda1 /mnt/usb
```

For varig oppsett: legg tilsvarende opsjoner i `/etc/fstab` for USB-disken.

### Ingen cron kreves for plasseringer

`checkin.php` og `checkout.php` oppdaterer disse filene direkte:

- `latest/active-sessions-latest.txt`
- `latest/active-sessions-latest.csv`
- `latest/active-sessions-latest.json`

Dette er de viktigste filene hvis systemet gaar ned.

### Valgfritt: sjeldnere databasebackup

Hvis du vil ha databasebackup i tillegg, kan `usb_backup.php` kjores sjeldnere,
for eksempel hver natt:

```bash
crontab -l 2>/dev/null | { cat; echo '17 3 * * * /usr/bin/php /var/www/mobilhotell/usb_backup.php >/dev/null 2>&1'; } | crontab -
```

Merk: denne cron-jobben er valgfri. Plasseringsfilene (`active-sessions-latest.*`)
oppdateres uten cron ved hver innlevering/utlevering.

### Hvor finner du data ved nedetid?

Backup lagres i:

```text
/mnt/usb/mobilhotell-backup/
```

Viktigste filer:

- `latest/active-sessions-latest.txt` (lettleselig oversikt over hvilke mobiler som ligger i hvilke slots)
- `latest/active-sessions-latest.csv` (samme i CSV)
- `latest/mobilhotell-latest.sql` (siste databasebackup)

Historikk:

- `db/` (tidsstemplede databasekopier)
- `status/` (tidsstemplede oversikter over aktive innleveringer)

## Første oppstart

- Databasen/tabeller opprettes automatisk ved første kall til appen.
- Tabeller og migreringer håndteres i db.php.
- Demo/testdata kan ligge i eksisterende database avhengig av miljø.

## To Linux-PC-er med felles database (samtidig skanning)

Anbefalt oppsett:

- Hoved-PC (server): Apache + app + MySQL/MariaDB
- Klient-PC: Apache + app (samme kode), men kobler til DB paa hoved-PC

### 1. Nettverk mellom maskinene

Gi faste IP-er, for eksempel:

- Hoved-PC: `10.10.10.1/24`
- Klient-PC: `10.10.10.2/24`

Test:

```bash
ping -c 3 10.10.10.1
ping -c 3 10.10.10.2
```

### 2. Tillat DB-tilgang fra klient-PC (paa hoved-PC)

```bash
sudo mysql -e "CREATE USER IF NOT EXISTS 'mobilhotell'@'10.10.10.2' IDENTIFIED BY 'BYTT_TIL_STERKT_PASSORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON mobilhotell.* TO 'mobilhotell'@'10.10.10.2'; FLUSH PRIVILEGES;"
```

Hvis MariaDB bare lytter lokalt, sett bind-adresse (filplassering varierer med distro):

```bash
sudo grep -R "bind-address" /etc/mysql /etc/my.cnf.d 2>/dev/null
```

Sett til hoved-PC sin IP eller `0.0.0.0`, restart deretter DB-tjenesten.

### 3. Pek klient-PC mot hoved-PC-databasen

Opprett samme Apache-konfig paa klient-PC, men med hoved-PC som host:

```bash
sudo tee /etc/apache2/conf-available/mobilhotell-db.conf >/dev/null <<'EOF'
SetEnv MOBILHOTELL_DB_HOST 10.10.10.1
SetEnv MOBILHOTELL_DB_PORT 3306
SetEnv MOBILHOTELL_DB_NAME mobilhotell
SetEnv MOBILHOTELL_DB_USER mobilhotell
SetEnv MOBILHOTELL_DB_PASS BYTT_TIL_STERKT_PASSORD
EOF

sudo a2enconf mobilhotell-db
sudo systemctl restart apache2
```

### 3b. Merk maskinene som hoved/klient (anbefalt)

For aa tydelig skille maskinene i UI, sett rolle i Apache-miljoet.

Paa hoved-PC:

```bash
echo 'SetEnv MOBILHOTELL_NODE_ROLE hoved' | sudo tee /etc/apache2/conf-available/mobilhotell-node.conf
sudo a2enconf mobilhotell-node
sudo systemctl restart apache2
```

Paa klient-PC:

```bash
echo 'SetEnv MOBILHOTELL_NODE_ROLE klient' | sudo tee /etc/apache2/conf-available/mobilhotell-node.conf
sudo a2enconf mobilhotell-node
sudo systemctl restart apache2
```

### 4. Del kvitteringsskriver fra hoved-PC til klient-PC

Kjor dette paa hoved-PC (server) for aa dele CUPS-skriveren i nettverket:

```bash
sudo cupsctl --share-printers --remote-any
sudo lpadmin -p CT-E351 -o printer-is-shared=true
sudo systemctl restart cups
```

Kjor dette paa klient-PC for aa legge til samme skriver via IPP:

```bash
sudo apt install -y cups-client
sudo lpadmin -p CT-E351 -E -v ipp://10.10.10.1/printers/CT-E351 -m raw
```

Sett skriverko paa klient-PC i Apache-miljoet:

```bash
echo 'SetEnv MOBILHOTELL_PRINTER CT-E351' | sudo tee /etc/apache2/conf-available/mobilhotell-printer.conf
sudo a2enconf mobilhotell-printer
sudo systemctl restart apache2
```

Test fra klient-PC:

```bash
echo "test fra klient" | lp -d CT-E351
sudo -u www-data /usr/bin/php /var/www/mobilhotell/print_receipt.php --session-id=1
```

### 5. Verifiser samtidig innsjekk

- Aapne kiosk paa begge maskiner
- Skann to ulike ID-kort samtidig
- Bekreft at begge innsjekk lagres og at samme slot ikke blir dobbeltbooket

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
lpstat -t
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
