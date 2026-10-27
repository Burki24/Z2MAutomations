# ADR 0003: Versionierte YAML und getrennte Speicherbestätigung

- Datum: 27.10.2026
- Status: Angenommen

## Entscheidung

Schema-Version 1 definiert eine eigene, strikt validierte Automationsstruktur.
Die Z2M-Erweiterung empfängt JSON-Aufträge mit YAML-Text, Transaktions-ID,
Sitzungskennung und erwarteter Revision. Sie speichert die Quelle unverändert
und bestätigt ausdrücklich nur deren Speicherung. Ausführung und Aktivierung
werden in einer späteren Ausbaustufe getrennt festgelegt.

Der Host stellt den YAML-Parser js-yaml und den JSON-Schema-Validator Ajv bereit.
Eigener Quellcode und kanonisches Schema werden reproduzierbar zu genau einer
hochladbaren .mjs zusammengeführt. Fremde Parser- oder Automationsimplementierung
wird nicht in das Artefakt kopiert. PHP-Helper wurden geprüft; deren
Symcon-Attributspeicher ist kein Baustein für Z2M-Dateipersistenz.

Die Speicherdateien liegen ausschließlich im eigenen Unterordner des echten
Z2M-Datenverzeichnisses. Schemafehler werden vor jedem Schreibzugriff abgewiesen.
Vorversion und temporäres Schreiben schützen die gültige Fassung vor
fehlgeschlagenen Updates. Eine beschädigte Hauptdatei führt nicht zu einer
stillen Rücksetzung der Revisionsgeschichte.

## Begründung und Folgen

Der aktuelle Z2M-MQTT-Bus reicht Retain-Metadaten nicht weiter. Die Sitzung bindet
Speicheraufträge an den aktuellen Erweiterungsstart. Revisionskontrolle und
bytegenaue Prüfsumme machen Wiederholungen nachvollziehbar.

Die Revision gilt für das gesamte Dokument. Gleichzeitige Aufträge werden
innerhalb der Erweiterung serialisiert. Mehrere unabhängige Prozesse mit
demselben Speicherordner sind nicht unterstützt.

Symcon-Editor, Übertragung der neuen Befehle und Ausführungsengine bleiben
getrennte nächste Schritte. Bestehende PHP-Transportverträge werden nicht geändert.
