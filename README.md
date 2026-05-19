# Foto Scan App

Schlanke PHP-Anwendung für Handscanner und Admin-Arbeitsplätze.

## Funktionen

- Login für Admins und normale User
- Scan-Workflow: Auftragsnummer eingeben, Kategorie wählen, mehrere Fotos anhängen
- Automatische Dateinamen wie `co_[Auftragsnummer]_[Kategorie]_[laufendeNummer].jpg`
- Foto-Metadaten mit Benutzer, Zeit, Auftrag und Kategorie
- Suche und Filter nach Auftrag, Kategorie, Benutzer und Zeitraum
- Admin-Verwaltung für Benutzer, Kategorien und Systemeinstellungen
- Lokaler Speicher oder optional FTP/SFTP-Konfiguration

## Voraussetzungen

- PHP 8.2 oder neuer
- Für den produktiven SQLite-Betrieb wird die `pdo_sqlite`-Erweiterung benötigt; in dieser Umgebung läuft die App ersatzweise mit einem dateibasierten Fallback, falls SQLite nicht verfügbar ist

## Starten

Im Projektordner:

```powershell
php -S 127.0.0.1:8000 -t public
```

Dann im Browser öffnen: `http://127.0.0.1:8000`

## Demo-Zugänge

Beim ersten Start werden zwei Demo-Accounts angelegt:

- Admin: `admin` / `Admin123!`
- User: `scanner` / `Scanner123!`

Bitte diese Passwörter nach dem ersten Login ändern, bevor die App produktiv genutzt wird.

## Speicher

Ohne FTP/SFTP werden Fotos lokal unter `storage/uploads` gespeichert und über die Anwendung ausgeliefert.

Wenn ein Remote-Ziel konfiguriert wird, versucht die App zusätzlich dort zu speichern.

## SQLite-Schema

Das SQLite-Backend für die Benutzerverwaltung ist in [database/schema.sql](database/schema.sql) definiert.

## Hinweise zur Laufzeitumgebung

Die lokale Testumgebung hier liefert kein `pdo_sqlite` und kein `mbstring`. Deshalb ist die App auf diese fehlenden Module vorbereitet und läuft dennoch ohne Zusatzpakete.
