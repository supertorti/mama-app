# Mama App - Aufgaben-Manager fuer Kinder

Eine mobile Web-App, mit der Eltern ihren Kindern Aufgaben zuweisen und Punkte vergeben koennen. Kinder sehen ihre eigenen Aufgaben, erledigen sie per PIN-Bestaetigung und sammeln Punkte.

## Funktionen

- **PIN-basierte Anmeldung** - Kein Benutzername/Passwort, nur ein 4-stelliger Code
- **Eltern-Dashboard** - Aufgaben aller Kinder im Ueberblick, neue Aufgaben erstellen
- **Kinder-Ansicht** - Eigene Aufgaben sehen, per PIN als erledigt markieren
- **Punktesystem** - Kinder sammeln Punkte fuer erledigte Aufgaben
- **Offline-faehiges Design** - Mobile-First SPA, als Home-Screen-App installierbar

## Tech-Stack

| Komponente | Technologie |
|---|---|
| Backend | Symfony 7.4 (PHP 8.2) |
| Datenbank | MySQL 8.0 (Docker) |
| Authentifizierung | JWT (lexik/jwt-authentication-bundle) |
| Frontend | Vanilla JS + Bootstrap 5 (Single-File SPA) |
| Code-Qualitaet | PHPStan Level 9 |

## Projektstruktur

```
mama-app-backend/
├── public/
│   └── index.html              # Komplette SPA (HTML + CSS + JS)
├── src/
│   ├── Controller/Api/
│   │   ├── PinAuthController    # POST /api/pin/check
│   │   ├── AdminController      # GET/POST /api/admin/*
│   │   └── ChildController      # GET/POST /api/child/*
│   ├── Entity/
│   │   ├── User                 # Eltern + Kinder (mit PIN-Hash)
│   │   ├── Task                 # Aufgaben mit Status + Punkten
│   │   └── PointTransaction     # Punkte-Historie
│   ├── Enum/
│   │   └── TaskStatus           # open | completed
│   ├── Security/
│   │   └── Voter/ChildAccessVoter
│   ├── Service/
│   │   ├── PinAuthenticationService
│   │   └── PointService
│   └── EventListener/
│       ├── JWTCreatedListener   # Fuegt userId/name/role zum JWT hinzu
│       └── ExceptionListener    # JSON-Fehlerantworten fuer /api
├── migrations/
├── config/
└── test-api.sh                  # Curl-basierter API-Integrationstest
```

## Installation

### Voraussetzungen

- PHP 8.2+
- Composer
- Docker (fuer MySQL)
- OpenSSL (fuer JWT-Schluessel)

### Setup

```bash
# Abhaengigkeiten installieren
composer install

# JWT-Schluessel generieren
php bin/console lexik:jwt:generate-keypair

# Datenbank erstellen und migrieren
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Testdaten laden
php bin/console doctrine:fixtures:load
```

### Server starten

```bash
symfony serve
# oder
php -S localhost:8000 -t public
```

Die App ist dann unter **http://localhost:8000/index.html** erreichbar.

## API-Endpunkte

| Methode | Pfad | Auth | Beschreibung |
|---|---|---|---|
| POST | `/api/pin/check` | - | PIN pruefen, JWT zurueckgeben |
| GET | `/api/admin/children` | Admin | Alle Kinder mit Punktestand |
| GET | `/api/admin/tasks` | Admin | Alle Aufgaben (Uebersicht) |
| POST | `/api/admin/tasks` | Admin | Neue Aufgabe erstellen |
| GET | `/api/child/{id}/tasks` | Kind | Eigene Aufgaben abrufen |
| POST | `/api/child/tasks/{id}/complete` | Kind | Aufgabe als erledigt markieren (PIN noetig) |
| GET | `/api/child/{id}/points` | Kind | Eigenen Punktestand abrufen |

## Testdaten (Fixtures)

| Benutzer | Rolle | PIN |
|---|---|---|
| Mama | Admin | `1234` |
| Kind 1 | Kind | `0000` |
| Kind 2 | Kind | `1111` |

Dazu 3 Testaufgaben (Zimmer aufraeumen, Hausaufgaben machen, Muell rausbringen).

## SPA-Screens

Die gesamte Oberflaeche ist in einer einzigen Datei (`public/index.html`) umgesetzt:

1. **Wer bist du?** - Rollenauswahl (Eltern / Kind)
2. **Admin PIN** - 4-stelliger Code, Auto-Submit
3. **Kind PIN** - 4-stelliger Code, Auto-Submit
4. **Admin Dashboard** - Aufgaben gruppiert nach Kind, Filter (Offen/Erledigt)
5. **Aufgabe hinzufuegen** - Kind, Titel, Uhrzeit, Punkte
6. **Kinder-Aufgaben** - Eigene Tasks mit Icons, Punkte-Anzeige, Filter

## Entwicklung

```bash
# PHPStan ausfuehren
vendor/bin/phpstan analyse

# API-Tests ausfuehren
./test-api.sh

# phpMyAdmin (Datenbank-UI)
docker run -d --name phpmyadmin --network db-network \
  -e PMA_HOST=mysql-server -p 8080:80 phpmyadmin/phpmyadmin
# -> http://localhost:8080 (root / root_super_secret_2024)
```
