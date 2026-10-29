# Automationsschema Version 1

## Aktionen im zweiten System

Optionales Dokumentfeld `mesh_peers`: bis zu acht eindeutige Basistopics,
jeweils 1–128 Zeichen aus Buchstaben, Ziffern, Unterstrich und Bindestrich,
getrennt durch einzelne `/`. Der Klick-Editor verwaltet einen ausgewählten
Partner. Alle neuen Felder sind optional; lokale Dokumente bleiben gültig.

Ein Geräte- oder Gruppenziel kann zusätzlich `system` mit dem Basistopic des
Zielsystems enthalten, beispielsweise `{type: group, id: 7, system: synthetic/b}`.
Ohne `system` bleibt es ein lokales Ziel. Bei eingeschalteten Regeln muss das
Zielsystem in `mesh_peers` enthalten sein. Das empfangende Dokument muss
umgekehrt die Quelle freigeben und ausdrücklich aktiviert sein. Ein deaktivierter
Entwurf darf entfernte Referenzen behalten, ohne eine Empfangsfreigabe zu erteilen.
Auslöser und Bedingungen bleiben lokal. Siehe [ADR 0009](adr/0009-cross-system-actions-over-mqtt-bridge.md).

Die kanonische maschinenlesbare Definition steht in
[automations.schema.json](../libs/z2m/automations.schema.json).
Sie wird unverändert in das eigene Einzeldatei-Artefakt eingebettet.
Die Erweiterung validiert die gespeicherten Daten erneut beim Start.

Empfang und Speicherung aktivieren keine Ausführung, auch wenn eine Regel
enabled: true enthält. Die eigene Engine führt ausdrücklich aktivierte Revisionen
aus; [Laufzeitvertrag](RUNTIME.md). Quellen und Ziele müssen beim Aktivieren
vorhanden sein. Zulässige Schaltwerte setzt der native Z2M-Converter um;
eine vollständige Prüfung aller Exposes erfolgt nicht.

## Dokument

```yaml
schema_version: 1
revision: 1
automations: []
```

Alle drei Felder sind erforderlich. revision ist eine positive sichere Ganzzahl
bis 9007199254740991. Die leere Liste ist eine gültige Konfiguration.
Ein [vollständiges synthetisches Beispiel](../libs/z2m/examples/automations.yaml)
zeigt Geräte- und Zeit-Auslöser, Zeitfenster und Geräte-/Gruppenaktionen.

Unbekannte Felder sind in allen strukturierten Objekten unzulässig. Keine
Skripte, Code-Ausdrücke, Dateipfade, frei wählbaren MQTT-Topics oder Referenzen
auf fremde Szenen. Der Vertrag ist eigenständig und kein Importformat des
Referenzprojekts Luligu/zigbee2mqtt-automations.

## Automation

| Feld | Inhalt |
| --- | --- |
| id | Eindeutiger stabiler Schlüssel, 1–64 Zeichen; beginnt mit a–z, danach a–z, 0–9, _ oder - |
| name | Anzeigename, 1–120 Zeichen; Namen dürfen sich wiederholen |
| enabled | Regel wird nach ausdrücklicher Aktivierung der Revision berücksichtigt |
| triggers | 1–8 Auslöser |
| conditions | 0–16 Bedingungen; die leere Liste ist ausdrücklich anzugeben |
| actions | 1–16 Aktionen |

Maximal 100 Automationen pro Dokument. IDs dürfen nicht mehrfach vorkommen.
Die Engine unterstützt alternative Auslöser, gemeinsam zu erfüllende Bedingungen
und geordnete Aktionen. Zeit-, Verzögerungs-, Neustart- und Fehlerverhalten
sind in [RUNTIME.md](RUNTIME.md) festgelegt.

## Auslöser

- type: device mit device, property, operator und value. device ist eine
  kleingeschriebene IEEE-Adresse als String nach 0x plus 16 Hex-Ziffern.
  operator ist eq, ne, gt, gte, lt oder lte. value ist String, Zahl, Boolean oder
  null; bei Größenvergleichen ist eine Zahl erforderlich.
  Optional for_seconds: Ganzzahl 0–86400 für eine durchgehende Mindestdauer.
- type: time mit at im Format HH:MM:SS und expliziter timezone.

IEEE-Adressen sollten in YAML in Anführungszeichen stehen, damit sie Strings
bleiben. Namen werden nicht als technische Geräteidentität verwendet.

## Bedingungen

- type: device_value mit denselben Vergleichsfeldern wie beim Geräte-Auslöser,
  ohne for_seconds.
- type: time_window mit after, before im Format HH:MM:SS und timezone.
  after und before müssen verschieden sein; Fenster über Mitternacht sind
  zulässig. Optional weekdays: eindeutige Liste mit ISO-Wochentagen 1–7,
  Montag ist 1. Ohne weekdays gilt kein Wochentagsfilter.

Zeitzonen müssen von Intl.DateTimeFormat des Z2M-Node-Runtimes akzeptiert werden,
beispielsweise Europe/Berlin oder UTC. Es wird nicht implizit die Zeitzone des
Symcon- oder Z2M-Rechners verwendet.

## Aktionen

type: set mit target und values. target ist entweder type: device mit einer
IEEE-Adresse in id oder type: group mit einer numerischen id von 1 bis 65535.
values enthält 1–32 Eigenschaften als JSON-kompatible Werte; verschachtelte
Objekte und Listen sind begrenzt zulässig. Optional delay_seconds: 0–86400.

Eigenschaftsnamen beginnen mit einem Buchstaben, enthalten nur Buchstaben,
Ziffern und Unterstriche und sind maximal 64 Zeichen lang. Die Schlüssel
__proto__, constructor, prototype und YAML-Merge-Schlüssel sind ausgeschlossen.
Strings in Vergleichswerten und Aktionswerten sind maximal 256 Zeichen lang.
Verschachtelte Werteobjekte und Listen haben jeweils maximal 32 Einträge.

## YAML-Grenzen

- Maximal 128 KiB UTF-8 pro YAML-Text; ungültige UTF-8-/Surrogatwerte werden abgewiesen.
- Genau ein Dokument, höchstens 16 Collection-Ebenen und 20000 Parser-Ereignisse.
- Nur JSON-kompatible Typen; keine expliziten Tags oder Direktiven.
- Keine Anker, Aliase, Merge-Keys, doppelten Mapping-Schlüssel oder nicht-textuellen Schlüssel.
- Keine automatische Typkonvertierung durch den Schema-Validator.

Syntax-, Schema- und ergänzende Semantikprüfungen sind getrennte Fehlerklassen.
Fehlerantworten enthalten feste Codes und Meldungen, keine Roh-YAML oder
Dateisystempfade. Eine neue inkompatible Datenstruktur benötigt eine neue
schema_version und eine ausdrücklich geplante Migration.
