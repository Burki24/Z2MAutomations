# Entwicklung

Es gelten AGENTS.md und die zentrale Basis in ../SymconDevelopment/.
Zielplattform: IP-Symcon 9.0 / PHP 8.5. Branch: dev.

## Abhängigkeiten

Nach dem Klonen: git submodule update --init --recursive.
.style stammt unverändert aus symcon/StylePHP, tests/stubs unverändert aus
symcon/SymconStubs. Die Gitlinks pinnen den jeweiligen Stand.
Noch keine produktive Helper-Abhängigkeit und keine npm-Abhängigkeiten.

## Lokale Prüfungen

- php tests/run.php
- php tests/validate_structure.php
- php .style/json-check.php
- php /path/to/php-cs-fixer.phar fix --config=.style/.php-cs-fixer.php --dry-run --diff --using-cache=no --allow-risky=yes

Die beiden letzten Befehle verwenden die offizielle StylePHP-Konfiguration.
CI nutzt Symcon_ModuleCI v1.0.0 für StylePHP und PHP-Tests. Zusätzlich läuft
php-cs-fixer explizit im Prüfmodus mit festgelegter Werkzeugversion.

tests/run.php umfasst Strukturprüfung und den offiziellen Stub-Test für
Instanzerstellung, wiederholtes ApplyChanges und den inaktiven Zustand.
Diese Nachweise ersetzen keine Symcon-Laufzeitprüfung.

## Grenzen

Keine MQTT- oder Z2M-Praxisprüfung im Grundgerüst. Keine Live-Installation,
Freischaltung, Neustarts oder Schaltbefehle ohne ausdrücklichen Praxisauftrag.
Commit und Push macht ausschließlich der Benutzer über GitHub Desktop.

## Nachweis des Grundgerüsts am 27.10.2026

- PHP 8.5.10: tests/run.php und tests/validate_structure.php erfolgreich.
- PHP-Syntaxprüfung aller vier eigenen PHP-Dateien erfolgreich.
- Offizieller StylePHP-JSON-Check erfolgreich.
- PHP CS Fixer 3.95.27 mit offizieller StylePHP-Konfiguration im Prüfmodus:
  keine Änderungen an den vier eigenen PHP-Dateien erforderlich.
- Eigene Textdateien als UTF-8 ohne BOM mit LF-Zeilenenden geprüft.
- Beide offiziellen Submodule unverändert und über Gitlinks festgelegt.
- Git-Diff-Prüfungen ohne Whitespace-Fehler.
- GitHub-Actions-Ausführung und echte Symcon-/Z2M-Laufzeit noch nicht geprüft.
