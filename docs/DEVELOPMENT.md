# Entwicklung

Es gelten AGENTS.md und die zentrale Basis in ../SymconDevelopment/.
Zielplattform: IP-Symcon 9.0 / PHP 8.5. Branch: dev.

## Abhängigkeiten

Nach dem Klonen: git submodule update --init --recursive.
.style stammt unverändert aus symcon/StylePHP, tests/stubs unverändert aus
symcon/SymconStubs. Die Gitlinks pinnen den jeweiligen Stand.
DataFlowHelper, ParentConnectionHelper und VariablePresentationHelper sind
unverändert aus dem kanonischen Consumer-Bundle eingebunden; Herkunft und Hashes
stehen in libs/helper/manifest.json. .helper-sync.json deklariert die Auswahl.
Der zentrale Consumer-Eintrag und GitHub-App-Zugriff wurden hier nicht eingerichtet.
Es gibt keine npm-Abhängigkeiten.

## Lokale Prüfungen

- php tests/run.php
- php tests/validate_structure.php
- php .style/json-check.php
- php /path/to/php-cs-fixer.phar fix --config=.style/.php-cs-fixer.php --dry-run --diff --using-cache=no --allow-risky=yes

Die beiden letzten Befehle verwenden die offizielle StylePHP-Konfiguration.
CI nutzt Symcon_ModuleCI v1.0.0 für StylePHP und PHP-Tests. Zusätzlich läuft
php-cs-fixer explizit im Prüfmodus mit festgelegter Werkzeugversion.

tests/run.php umfasst Strukturprüfung, Helper-Integrität, Instanzerstellung,
wiederholtes ApplyChanges sowie MQTT-Integration mit synthetischen Gateways.
Diese Nachweise ersetzen keine Symcon-Laufzeitprüfung.

## Grenzen

Noch keine native MQTT- oder Z2M-Praxisprüfung. Keine Live-Installation,
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

## MQTT-Ausbaustufe

Der Fehltest vor Implementierung scheiterte an der noch fehlenden Active-Property.
Die Integrationstests verwenden offizielle Kernel-Stubs und synthetische MQTT-
Eltern für Client und Server. Die Testuhr wird über eine abgeleitete Modulklasse
ersetzt; Kernel-Nachrichten werden wegen der Stub-Grenzen explizit zugestellt.
Keine Änderungen an offiziellen Stubs oder Helper-Quellen.

Geprüft werden Strict-HEX, passende und unpassende Topics, Unicode und JSON-
Maskierung, normalisierter Cache ohne Geheimnisse, Transaktionszuordnung,
Retain-Antworten, verspätete und sofortige Antworten, fehlgeschlagener Versand,
Zeitlimit, Deaktivierung, Verbindungsabbruch, Topic-/Gateway-Wechsel und Kernelstart.
Nachweise für native MQTT-Gateways und das Konsolenformular bleiben offen.

Abschlussprüfung am 27.10.2026 unter PHP 8.5.10: tests/run.php,
tests/validate_structure.php, offizieller StylePHP-JSON-Check und PHP CS Fixer
3.95.27 mit offizieller Konfiguration im Prüfmodus erfolgreich. Alle acht eigenen
PHP-Dateien bestehen die Syntaxprüfung. Helper-Hashes stimmen mit dem Manifest;
offizielle Submodule sind unverändert. Eigene Textdateien verwenden LF ohne BOM,
git diff --check ist sauber. GitHub-CI wurde lokal nicht ausgeführt.
