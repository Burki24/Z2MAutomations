# ADR 0004: Korrelierte Installation und geprüfte YAML-Übertragung

- Datum: 27.10.2026
- Status: Angenommen

## Entscheidung

Symcon nutzt die offizielle extension/save-API ausschließlich für das feste
eigene Artefakt. Externe JavaScript-Freischaltung und Z2M-Neustart bleiben
außerhalb des Installationsaufrufs. Die Upload-Bestätigung wird durch eine
frische korrelierte Statusanfrage an den neuen Empfänger ergänzt. Bereits
beobachtete oder retained Statusmeldungen autorisieren keine Übertragung.

YAML-Prüfung und Speicherung sind getrennte manuelle Aktionen. Die kanonische
Schema-Prüfung läuft in unserer Erweiterung; PHP implementiert keinen zweiten
YAML-Parser. Ein begrenzter gültiger UTF-8-Entwurf wird übergeben und seine
Prüfsumme mit der Empfängerantwort verglichen. Die lokale Prüffreigabe enthält
Prüfsumme, Kandidatenrevision, Anzahl der Automationen, Sitzung und die geprüfte
Speicherrevision samt bisheriger Prüfsumme.

Vor store wird der Empfänger erneut aktiv abgefragt. Geänderte Sitzung,
Speicherrevision, bisherige Prüfsumme oder Entwurfsbytes sperren den Schreibschritt.
Der Empfänger prüft expected_revision weiterhin selbst gegen konkurrierende
Änderungen. Antworten werden an Topic, Transaktion und Operationsphase gebunden;
die Bestätigung eines Stores muss Revision, Prüfsumme und Anzahl zurückliefern.

## Konsequenzen

Ein Workflow hat eine öffentliche Aktions-ID und je Phase eine eigene
Wire-Transaktion und Deadline. Pro Instanz läuft ein Erweiterungsworkflow neben
der bestehenden Gesundheitsanfrage. Ein gemeinsamer Timer bedient beide Kanäle.
Der Zustand wird innerhalb des vorhandenen Semaphores geändert; Netzwerkversand
liegt außerhalb, damit sofortige Antworten nicht blockieren.

Die YAML liegt als begrenzter Entwurf in einem Symcon-Attribut. Die Prüffreigabe
bleibt volatil und wird bei Ziel-/Sitzungswechsel verworfen. Der öffentliche
Zustand enthält weder Roh-YAML noch Erweiterungscode oder Sitzungskennungen.
Native und eigene Fehler werden nur über feste Meldungen ausgegeben.

Das dynamische Formular nutzt den unveränderten ConfigurationFormHelper aus dem
kanonischen Bundle. Der vorhandene zentrale Attributcache wurde geprüft, aber
für den rohen YAML-String wird kein JSON-Cache benötigt. Die Workflow-/Protokoll-
Logik bleibt librarylokal. Eine Native-MQTT-/Formular-/Z2M-Praxisabnahme steht aus.
