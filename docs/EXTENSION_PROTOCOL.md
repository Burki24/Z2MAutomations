# Erweiterungsprotokoll Version 1

## Umfang und Installation

Diese Ausbaustufe implementiert die eigene Z2M-Seite: Empfang, Validierung,
persistente Speicherung, Wiederanlauf und Rückmeldung. Der Symcon-Transport
integriert Installation, aktive Statusabfragen, validate und store;
siehe [Modulanleitung](../Z2MAutomations/README.md). Eine tatsächlich ausgeführte
Installation und YAML-Übertragung wurden praktisch geprüft. Die neue
Ausführungsengine ergänzt getrennte Aktivierungsbefehle; ihr Praxistest steht aus.

Für eine spätere Installation wird ausschließlich
[z2m-automations.mjs](../libs/z2m/z2m-automations.mjs) mit der offiziellen
extension/save-API hochgeladen. Der Host stellt Ajv und js-yaml bereit.
Das Schema und sämtliche eigene Logik sind in dieser Datei enthalten.

## Topics

Alle Topics liegen unter dem in Z2M konfigurierten Basistopic.
Der Präfix symcon/automations ist unser eigener Vertrag.

| Suffix | Bedeutung |
| --- | --- |
| symcon/automations/request/status | Aktuellen Zustand einschließlich Sitzung abfragen |
| symcon/automations/request/validate | YAML ohne Schreibzugriff prüfen |
| symcon/automations/request/store | YAML persistent speichern, ohne Ausführung |
| symcon/automations/request/get | Geladene gültige YAML und Metadaten zurückgeben |
| symcon/automations/request/activate | Gespeicherte Revision ausdrücklich aktivieren; [Vertrag](RUNTIME.md) |
| symcon/automations/request/deactivate | Ausführung und ausstehende Verzögerungen stoppen |
| symcon/automations/response/OPERATION | Antwort auf die jeweilige Anfrage |
| symcon/automations/status | Zustand bei Start, erfolgreicher Speicherung und Stop; retained |

Ein MQTT Client in Symcon muss zusätzlich BaseTopic/symcon/automations/#
abonnieren. Der Modul-Empfang verarbeitet diesen Präfix bereits. Ein bestehendes
Abonnement # genügt auf Gateway-Ebene. Die bisherigen bridge/#-Daten decken den
neuen Präfix nicht ab.

Jede Anfrage ist ein JSON-Objekt mit protocol_version: 1 und transaction als
String mit 1–64 ASCII-Zeichen aus A–Z, a–z, 0–9, _ und -.
Unbekannte Felder werden abgewiesen. validate benötigt zusätzlich yaml.
status und get haben keine weiteren Felder. Die zusätzlichen Felder für
activate/deactivate stehen in [RUNTIME.md](RUNTIME.md).

Beispiel für store; SESSION stammt aus der aktuellen Statusmeldung:

```json
{
    "protocol_version": 1,
    "transaction": "synthetic-store-1",
    "session": "SESSION",
    "expected_revision": 0,
    "yaml": "schema_version: 1\nrevision: 1\nautomations: []\n"
}
```

Die erste Speicherung verwendet expected_revision 0 und YAML-revision 1.
Danach müssen expected_revision und gespeicherte Revision übereinstimmen;
die neue YAML-revision ist genau um eins höher.
Ein bytegleiches Wiederholen der aktuellen Fassung mit gleichem oder unmittelbar
vorherigem expected_revision wird ohne erneutes Schreiben als unchanged bestätigt.
Gleiche Revision mit anderem Inhalt ist kein erlaubtes Update.

## Sitzung und Antworten

Die Erweiterung erzeugt bei jedem Start eine neue zufällige session und gibt sie
im Status zurück. store verlangt diese Sitzung. Sie ist eine Aktualitätskennung,
keine Authentifizierung. MQTT-Berechtigungen müssen Schreiben und Lesen auf
berechtigte Clients begrenzen. Befehle werden grundsätzlich ohne Retain gesendet.

Wichtige Z2M-Grenze: Der geprüfte MQTT-Eventbus liefert topic und message,
aber kein Retain-Merkmal. Eine alte retained Nachricht aus einer vorherigen
Erweiterungssitzung wird deshalb über session abgewiesen. Liefert ein Adapter
retain: true ausdrücklich mit, wird store ebenfalls abgewiesen.
Innerhalb derselben Sitzung schützen Revision, Prüfsumme und Idempotenz vor
erneutem Anwenden einer bereits gespeicherten Fassung. Die Erweiterung kann den
Retain-Zustand im nativen Bus nicht allgemein erkennen.

Erfolg: protocol_version, transaction, status: ok und data. Bei store enthält
data stored_revision, checksum, automation_count, storage, executing und
unchanged. Die Prüfsumme ist SHA-256 über die exakten UTF-8-YAML-Bytes einschließlich
Kommentaren und Zeilenenden. Eine Bestätigung bedeutet gespeichert; sie aktiviert
nichts. Eine geänderte erfolgreiche Speicherung stoppt die Engine. Eine bytegleiche
Speicherung erhält die bereits aktive Revision und meldet dann executing: true.

Fehler: status: error und error mit code und fester message. Gültige
Transaktions-IDs werden auch bei ungültigem Inhalt gespiegelt. Eine ungültige
oder fehlende ID wird nicht übernommen.

Codes: invalid_request, invalid_yaml, invalid_schema, invalid_semantics,
session_mismatch, revision_conflict, revision_reused, storage_unavailable,
storage_error, busy und not_ready. Keine Parser-Snippets, Roh-Konfigurationen,
internen Pfade oder rohen Dateisystemfehler in Antworten oder eigenen Logs.

Die JSON-Anfrage ist maximal 1 MiB groß. Es werden höchstens 16 Anfragen
einschließlich der laufenden Anfrage gehalten und seriell verarbeitet.
Antworten werden mit QoS 0 ohne Retain veröffentlicht. Ein verlorenes ACK kann
durch die idempotente Wiederholung und Statusabfrage aufgelöst werden.

## Persistenz und Fehlerzustände

Eigener Ordner neben external_extensions im tatsächlich verwendeten
Z2M-Datenverzeichnis: data/z2m-automations/. Keine festen Hostpfade und keine
vom Client übergebenen Dateinamen.

- automations.yaml: aktuelle gültige YAML.
- automations.previous.yaml: unmittelbar vor dem letzten Update gültige Fassung.
- .write-UUID.tmp: ausschließlich eigene temporäre Schreibdateien.

Vor einem Update wird die Hauptdatei erneut validiert und mit der geladenen
Prüfsumme verglichen. Fremde Änderungen sperren Schreibzugriffe. Auch eine
idempotente Anfrage umgeht diesen Vergleich nicht.

Jede Datei wird im selben Verzeichnis exklusiv temporär angelegt, vollständig
geschrieben und mit FileHandle.sync gespült. Erst nach dem Schließen wird sie
per rename ersetzt. Die Vorversion wird vor dem Ersetzen der Hauptdatei gesichert.
Der geladene Zustand wechselt erst nach erfolgreichem Ersetzen der Hauptdatei.
Temporäre Dateien werden nach Fehlern bestmöglich entfernt; etwaige Überreste
werden beim Start nicht als Konfiguration geladen.

Neue Ordner erhalten unter POSIX 0700, neue Dateien 0600. Die Prüfungen akzeptieren
keine symbolischen Links als verwaltete Dateien oder als direkten Speicherordner.
Die Speicherung ist für einen Erweiterungsschreiber in einem lokalen persistenten
Z2M-Datenverzeichnis vorgesehen. Netzwerkdateisysteme, mehrere Prozesse mit
gemeinsamem Datenordner und Stromausfallsicherheit sind nicht nachgewiesen.
Es gibt keine zusätzliche Verzeichnis-fsync-Garantie nach rename.

| storage | Bedeutung |
| --- | --- |
| empty | Hauptdatei und Sicherung fehlen; Erstübertragung mit Revision 1 möglich |
| ok | Gültige Hauptdatei geladen oder gespeichert |
| recovered_read_only | Hauptdatei fehlt/ist beschädigt; gültige Sicherung nur lesend geladen |
| error | Speicher nicht verfügbar, Dateien ungültig oder fremde Änderung erkannt |

Bei recovered_read_only bleibt die beschädigte Hauptdatei erhalten. Neue
Schreibzugriffe werden abgewiesen; die Sicherung ist mit get lesbar. Eine
Wiederherstellung benötigt eine bewusste Prüfung der Dateien und Revisionen
bei gestoppter Erweiterung. Keine automatische Rücksetzung auf Revision 0.

state: ready bezeichnet die Erreichbarkeit des Empfängers. storage ist gesondert
auszuwerten. executing bezeichnet die ausdrücklich aktivierte Engine, nicht eine
bestätigte Gerätewirkung. Status und Aktivierungsvertrag stehen in [RUNTIME.md](RUNTIME.md).

## Quellen und Testgrenzen

- [Z2M-Erweiterungsvertrag](https://www.zigbee2mqtt.io/advanced/more/external_extensions.html)
- [Geprüfter MQTT-Eventbus](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/mqtt.ts)
- [Z2M-Abhängigkeiten](https://github.com/Koenkk/zigbee2mqtt/blob/master/package.json)
- [js-yaml API](https://github.com/nodeca/js-yaml/blob/master/docs/usage.md)
- [Node-Dateisystem](https://nodejs.org/api/fs.html)

Vertragsprüfung am 27.10.2026 gegen Z2M 2.14.2. Offline geprüft mit js-yaml 5.4.2,
Ajv 8.20.0, echter Dateispeicherung in temporären Testordnern und synthetischem
MQTT-/Eventbus. Keine fremde Automationsimplementierung übernommen.
Die Tests bestätigen keinen Betrieb in einer echten Z2M-Installation.
