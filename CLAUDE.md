# I am Janice Mercedes - Website

## Projekt
- **Kundin**: Janice Mercedes (Tattoo Artist & Beauty Expertin)
- **Domain**: iamjanicemercedes.de
- **Hosting**: IONOS (PHP + MySQL)
- **GitHub**: github.com/Marlonzo187/-i-am-janice-mercedes

## Tech-Stack
- **Frontend**: HTML5, CSS3, Vanilla JS (statische Seiten)
- **Backend**: PHP 8 mit PDO (Prepared Statements)
- **Datenbank**: MySQL auf IONOS (db5019662144.hosting-data.io)
- **E-Mail**: PHPMailer ueber IONOS SMTP
- **Bilder**: WebP-Format, optimiert

## Wichtige Dateien
- `config.php` - DB + SMTP Zugangsdaten (NIEMALS committen!)
- `db.php` - PDO Singleton Verbindung
- `formular.php` - Formular-Handler (Beauty + Tattoo)
- `tracker.php` - Besucher-Tracking Endpoint
- `admin/` - Komplettes Admin-Panel mit Login

## Admin-Panel (/admin/)
- Login: Session-basiert, bcrypt, Rate-Limiting
- dashboard.php - Anfragen-Uebersicht
- kunden.php - Kundenliste
- kunde.php - Kunden-Detail + Buchungshistorie
- statistiken.php - Besucher-Statistiken
- papierkorb.php - Geloeschte Anfragen

## Datenbank-Tabellen
- `kunden` - Kundenstammdaten
- `anfragen` - Buchungsanfragen (mit Soft-Delete)
- `datenschutz_dokumente` - Hochgeladene Datenschutzerklaerungen
- `besuche` - Besucher-Tracking (Seitenaufrufe)

## Deployment auf IONOS
1. Dateien aendern und testen
2. Git commit + push (Mentor sieht Fortschritt)
3. Geaenderte Dateien per IONOS WebSpace Explorer in /public hochladen
4. Bei DB-Aenderungen: db_setup.php hochladen, aufrufen, wieder loeschen

## Design-System
- Farben: --tobacco (#B59E7D), --sand (#CEC1A8), --vanilla (#F1EADA), --darker (#1E1B18)
- Fonts: Cormorant Garamond (Headlines), Segoe UI (Body)
- Stil: Elegant, dunkel, minimalistisch
