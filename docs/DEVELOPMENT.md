# Entwicklung

## Zeitsteuerungen und Bedingungen im Klick-Editor

Neue Formularfelder und die eigene Schedule-Logik erzeugen das unveränderte Schema 1:
ein Geräte- oder täglicher Uhrzeit-Auslöser, eine Geräteaktion, optional ein
Zeitfenster und eine Gerätebedingung. Mindestdauer und Aktionsverzögerung bleiben
auf 86400 Sekunden begrenzt. Mehrere gleichartige Bedingungen, mehrere Auslöser,
Gruppen und Aktionsfolgen werden nicht vereinfacht; ihre Metadaten bleiben bearbeitbar.
Der additive numerische Katalog enthält nur lesbare Eigenschaften und kurze Einheiten.

Offline-Nachweise verwenden den echten JavaScript-Empfänger: Uhrzeit-/Zahlenregel
mit Nachtfenster und Wochentag erzeugen, prüfen, speichern, neu laden und bearbeiten;
danach Wechsel zu Geräteereignis mit Zustandsbedingung, Dauer und Verzögerung.
Ungültige Uhrzeiten, Zeitzonen, leere Tagesauswahl, identische Fenstergrenzen,
unbekannte Eigenschaften und untypisierte Zahlen lassen den Entwurf unverändert.
Die vorhandenen Engine-Tests sichern strikte Vergleiche, unbekannte Zustände,
UND-Bedingungen, Mitternacht, Sommerzeit und verpasste Zeitpunkte ab.

Die neue Bedienung und der numerische Katalog wurden noch nicht live aktualisiert
oder in der nativen Konsole geprüft. Dafür müssen Modul und mitgeliefertes
Erweiterungsartefakt aktualisiert werden; anschließend den Editor neu laden.

## Bearbeiten und Löschen im Klick-Editor

Die Erweiterung des Editors wurde offline gegen denselben echten JavaScript-
Empfänger geprüft: Auswahl über stabile IDs auch bei gleichen Namen, Vorbelegen
der Felder, Ändern von Ereignis/Aktion/Name/Aktivierung, Erhalt anderer Regeln,
Löschen der letzten Regel und anschließende Prüfung/Speicherung einer leeren
Konfiguration. Erweiterte Regeln erlauben Metadatenänderungen ohne Verlust ihrer
Bedingungen und Schritte. Auswahlkennungen binden Aktionen an den Entwurf;
veraltete Formulare, fehlende IDs und ungespeicherte Expertentexte werden abgewiesen.
Alle dynamischen Optionen und Listenwerte bleiben SDK-konform JSON-codiert.

Die neuen Auswahl-/Ändern-/Löschbuttons wurden anschließend auf ausdrücklichen
Auftrag in der nativen Testkonsole praktisch geprüft. Die geladenen Modul-,
Formular- und Editorquellen stimmen per Hash mit dem lokalen Stand überein;
der Modul-Reload meldete keine Warnungen oder Fehler. Eine vorhandene gestoppte
Testregel wurde ausgewählt, umbenannt und deaktiviert. „Änderungen in den Entwurf
übernehmen“ erhielt ihre ID, Auslöser und Aktionen. Die Änderung wurde über die
Formularbuttons in Z2M geprüft, bestätigt gespeichert und erneut unverändert geladen.

Beim Löschen blieb nach Abbruch des Bestätigungsdialogs der Entwurf unverändert.
Nach Bestätigung verschwand die ausgewählte letzte Regel zunächst nur lokal;
Z2M behielt bis zum Speichern seine vorige Revision. Der leere Entwurf wurde
über die Formularbuttons erfolgreich geprüft und gespeichert. Eine zusätzliche
frische Editorabfrage lieferte null Regeln zurück; Liste und Auswahl sind leer,
der Löschbutton ist deaktiviert. Die Konfiguration bleibt gestoppt. Geräte wurden
nicht geschaltet, die Z2M-Erweiterung benötigte kein Update oder Neustart.
Nativer Debug belegt Auswahl, Änderung, Löschung und die abgeschlossenen Prüf-/
Speicheraufträge ohne Fehlermeldungen. Die Debug-Dateisitzung wurde beendet.
Native Änderungen an erweiterten Regeln oder weiteren Gerätefeldern waren nicht
Teil dieses Laufs; dafür bleiben die beschriebenen Offline-Nachweise maßgeblich.

## Native Praxisprüfung: Klick-Editor und Steuerung

Auf der ausdrücklich freigegebenen Testinstallation mit Z2M 2.14.2-dev wurde
der Editor im geöffneten nativen Konsolenformular bedient: Geräte laden,
Quelle/Ereignis/Ziel/Aktion auswählen, Regel ohne sichtbaren YAML-Editor erzeugen,
validieren und als nächste Revision speichern. Die dynamischen Select-Optionen
und List-Werte benötigen JSON-codierte UpdateFormField-Werte; der Regressionstest
prüft diesen SDK-Vertrag auch bei bereits geöffnetem Formular.

Aktivieren mit Bestätigungsdialog und Stoppen wurden mit anschließenden frischen
Z2M-Bestätigungen, Statusvariablen und nativen deutschen Debug-Ausgaben geprüft.
Der Neustart wurde direkt über MQTT mit frischer Antwort, Offline-/Onlinewechsel
und neuer Erweiterungssitzung nachgewiesen. Die gespeicherte Revision blieb erhalten
und war anschließend inaktiv. Der exakte Artefakthash wurde mit dem von Z2M
gemeldeten Erweiterungscode verglichen.

Ein eigener MQTT-Set-Befehl erreichte auf diesem MQTT-Weg den Z2M-Empfänger
auch mit skipReceive=false nicht. Der begrenzte Echo-Kontrolltest erhielt keine
Rückzustellung; die native EventBus-Übergabe schaltete dagegen dasselbe Testgerät.
Die Engine verwendet deshalb nun Z2Ms interne Befehlsverarbeitung (ADR 0007).
Nach Installation des korrigierten Artefakts wurde die gespeicherte Regel über
den Symcon-Aktivierungsbutton bestätigt und praktisch ausgelöst: Das eingeschaltete
Testlicht meldete anschließend Aus. Nach Betätigung des Stoppbuttons blieb es
beim erneuten Einschalten An. Aktive Revision, Regelanzahl, gestoppter Zustand
und leere Fehleranzeige wurden über Formular und Statusvariablen kontrolliert.
Der native Moduldebug belegt Start/Ende und abgeschlossene Steueraufträge.
Temporäre Diagnoseerweiterung und Debug-Dateisitzung wurden nach Prüfung beendet;
die gespeicherte Testregel bleibt gestoppt. Private Gerätedaten sind nicht Teil
dieses Berichts. Ein vollständiger Ausfall des Symcon-Dienstes wurde nicht erzeugt;
die Unabhängigkeit bei getrenntem Datenfluss bleibt zusätzlich offline abgesichert.

Es gelten AGENTS.md und die zentrale Basis in ../SymconDevelopment/.
Zielplattform: IP-Symcon 9.0 / PHP 8.5. Branch: dev.

## Abhängigkeiten

Nach dem Klonen: git submodule update --init --recursive.
.style stammt unverändert aus symcon/StylePHP, tests/stubs unverändert aus
symcon/SymconStubs. Die Gitlinks pinnen den jeweiligen Stand.
DataFlowHelper, ParentConnectionHelper, VariablePresentationHelper und ConfigurationFormHelper sind
unverändert aus dem kanonischen Consumer-Bundle eingebunden; Herkunft und Hashes
stehen in libs/helper/manifest.json. .helper-sync.json deklariert die Auswahl.
DebugFlowHelper und dessen DebugHelper-Abhängigkeit werden ebenfalls über das
kanonische Bundle bezogen. ModuleDiagnostics delegiert nur noch an diesen Ablauf.
Der zentrale Consumer-Eintrag und GitHub-App-Zugriff wurden hier nicht eingerichtet.
Node ab 22.2 und npm ci sind für die Erweiterungstests erforderlich. package-lock.json
fixiert js-yaml 5.4.2 und Ajv 8.20.0 als lokale Testabhängigkeiten. In Z2M werden
die vorhandenen Host-Abhängigkeiten verwendet, kein npm-Verzeichnis mitgeliefert.

## Lokale Prüfungen

- php .style/json-check.php
- php /path/to/php-cs-fixer.phar fix --config=.style/.php-cs-fixer.php --dry-run --diff --using-cache=no --allow-risky=yes
- npm ci --ignore-scripts --no-audit --no-fund
- npm run check:extension
- php tests/run.php
- php tests/validate_structure.php
- npm test (nur Erweiterungstests)

`tests/runtime.test.mjs` prüft die Engine über das erzeugte Artefakt mit
synthetischen Z2M-Geräten, virtueller Zeit und echtem temporärem YAML-Speicher.
Aktivierung, Abbruch, Mindestdauer, Zeitfenster, Sommerzeit, Sequenzen,
Transportfehler und Mengenbegrenzung sind eigene Verhaltensnachweise.

`tests/module-extension.php` verwendet den echten Node-Empfänger zusätzlich für
Gerätekatalog, Klick-Entwurf, Aktivierung und Stopp mit anschließender Verifikation.
Der Datenfluss wird während einer synthetischen Geräteaktion getrennt: Die
gespeicherte Regel muss unabhängig vom Symcon-Modul weiter ausgeführt werden.
Negative Fälle prüfen fremde Revision, retained ACK, Timeout, Stoppvorrang,
unveränderte vorhandene Regeln, ungespeicherte Expertentexte und defektes Debug.
Das Formular wird in den Stubs strukturell geprüft. Seine reale Bedienung in
der nativen Konsole ist noch praktisch zu prüfen; kein Live-Schalten durch diese Tests.

Die beiden ersten Befehle verwenden die offizielle StylePHP-Konfiguration.
Der unveränderte StylePHP-JSON-Checker durchsucht rekursiv auch node_modules;
seine Vollprüfung erfolgt deshalb in einem Checkout ohne installierte Node-
Abhängigkeiten. CI hält Style- und Testjob entsprechend getrennt.
CI nutzt Symcon_ModuleCI v1.0.0 für StylePHP und PHP-Tests. Zusätzlich läuft
php-cs-fixer explizit im Prüfmodus mit festgelegter Werkzeugversion.

tests/run.php umfasst Strukturprüfung, Helper-Integrität, Instanzerstellung,
wiederholtes ApplyChanges, MQTT-Integration mit synthetischen Gateways und
Erweiterungstests mit echten Dateien in temporären Testverzeichnissen.
Die PHP-Tests laden das produktive Modul gezielt über loadSingleModule, weil
der offizielle Stub-Lader node_modules sonst fälschlich als Modul behandelt.
Library-Metadaten prüft weiterhin tests/validate_structure.php.
Diese Nachweise ersetzen keine Symcon-Laufzeitprüfung.

tests/module-extension.php testet die produktive PHP-Integration gegen das echte
Erweiterungsartefakt in einem Node-Teilprozess. Der Peer simuliert ausschließlich
nativen MQTT-/extension-save-Rahmen in einem temporären Z2M-Verzeichnis. Geprüft
werden Installation mit Bereitschaftsprobe, Draft-Prüfung, Persistenz und
idempotentes Speichern. Weitere deterministische Fälle decken falsche/retained
und verspätete Antworten, Prüfsummen, Versionen, Revisionskonflikte, Sitzungs-
wechsel, Speicherlesemodus, Größen-/UTF-8-Grenzen, Kontextwechsel und Fehler ab.
Das Formular wird über den echten ConfigurationFormHelper geprüft; seine
Bestätigungsfelder und Übersetzungen sind Teil der Strukturprüfung.

## Symcon-Integration: Nachweis am 27.10.2026

Vor Implementierung scheiterte der neue Test an der fehlenden InstallExtension-
Methode. Der integrierte PHP-/JavaScript-Pfad besteht nun mit echten temporären
Dateien und der produktiven Erweiterung: Upload des exakten Artefakts, frische
Bereitschaftsprobe, Draft-Prüfung, Vorschau, bestätigte Speicherung, idempotente
Wiederholung und Neustart mit erneut erforderlicher Prüfung.

Die gesamte tests/run.php-Suite einschließlich der 13 Erweiterungstests besteht
unter PHP 8.5.10 mit Node 24.20.0 und zusätzlich über Node 22.2.0. Fehlerfalltests
prüfen auch das Widerrufen einer vorherigen Bereitschaft bei Protokollfehlern.
Struktur-/Helper-Integritätsprüfung, offizieller StylePHP-JSON-Check und
php-cs-fixer 3.95.27 im Prüfmodus sind erfolgreich. Der JSON-Check läuft wie
dokumentiert vorübergehend ohne node_modules im Quellbaum; diese Abhängigkeiten
werden anschließend wiederhergestellt. Keine Änderungen an offiziellen Quellen.

Nicht ausgeführt: GitHub-CI, native MQTT-/Z2M-Installation und Bedienprüfung des
Formulars in der echten Konsole. Das JSON-/Callback-/Bestätigungsformat wurde
gegen die offizielle Dokumentation und die Integrationstests geprüft.

## Erweiterungsartefakt

Nach Änderungen an libs/z2m/src/extension.mjs oder automations.schema.json:
npm run build:extension. Der Builder ersetzt ausschließlich den Schema-Import
durch das kanonische Schema und erzeugt libs/z2m/z2m-automations.mjs.
Kein Fremdcode wird gebündelt. npm test und tests/run.php prüfen vor Ausführung,
dass das Artefakt exakt zu den Quellen passt. Nur dieses Einzeldatei-Artefakt
gehört bei einer später beauftragten Installation nach data/external_extensions.

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

## Erweiterung: Nachweis am 27.10.2026

- Der erste Fehltest bestätigte das fehlende Einzeldatei-Artefakt vor Implementierung.
- 13 Node-Tests mit echten temporären Dateien bestanden unter Node 24.20.0 und
  zusätzlich unter der vorgesehenen Mindestversion Node 22.2.0.
- Geprüft: eigenes Beispieldokument, strikte Syntax-/Schema-/Semantikprüfung,
  Schreibfreiheit von validate, Konflikte, Idempotenz, Sitzung und Replay,
  serialisierte begrenzte Warteschlange, Open-/Write-/Sync-/Close-/Rename-Fehler,
  beschädigte Dateien, Sicherungslesemodus, externe Änderungen und MQTT-Ausfälle.
- Der Test einer kopierten Einzeldatei in synthetischem data/external_extensions
  prüft die tatsächliche Ableitung des persistenten Datenpfads ohne Test-Override.
- tests/run.php prüft zusätzlich alle bisherigen PHP-/MQTT-Tests und die
  reproduzierbare Übereinstimmung von Quellen, Schema und Artefakt.
- Offizieller StylePHP-JSON-Check und php-cs-fixer im Prüfmodus erfolgreich.
  node_modules wurde dafür vorübergehend außerhalb des Quellbaums gehalten und
  anschließend unverändert zurückgelegt. Offizielle Quellen bleiben unverändert.
- Noch keine reale Installation, kein Broker-Verbindungstest und keine Abnahme
  in Z2M; keine Ausführungsengine implementiert oder geprüft.
