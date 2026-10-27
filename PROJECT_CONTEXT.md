# Projektkontext: Z2MAutomations

## Ziel

Automationen komfortabel in Symcon erstellen und verwalten. Eine vollständig
eigene externe Zigbee2MQTT-Erweiterung empfängt die erzeugte YAML, speichert
sie persistent und führt sie unabhängig von Symcon aus.

## Stand

- Entwicklung auf dev; kein Release und keine Live-Installation durch den Agenten.
- IPSModuleStrict-Verwaltungsmodul mit optional aktivierter MQTT-Anbindung.
- Native MQTT Client und MQTT Server werden als gemeinsam nutzbare Eltern
  angeboten; Gateway-Einstellungen und Subscriptions bleiben unverändert.
- Konfigurierbares Basistopic, lesbare Statusvariablen, normalisierter Bridge-Cache
  und asynchroner health_check mit Transaktion und Zeitlimit implementiert.
- Standardmäßig deaktiviert; keine automatischen Anfragen oder Schaltaktionen.
- Eigene JavaScript-Erweiterung und YAML-Schema Version 1 implementiert.
- Erweiterungsbefehle validate/store/get/status mit Protokollversion 1,
  Sitzungskennung, Revisionskontrolle, SHA-256 und serialisierter Verarbeitung.
- Persistente YAML mit Vorversion und temporärem, gespültem Schreiben vor rename.
  Beschädigte Hauptdateien führen mit gültiger Sicherung zu recovered_read_only.
- Installation über die offizielle MQTT-API mit anschließender korrelierter
  Empfängerprüfung im Modul integriert.
- YAML-Editor, Prüfung, Vorschau und bestätigte Speicherung aus dem Formular
  implementiert. Vor Store erneute Prüfung von Sitzung und Revision; kein
  blindes Überschreiben, keine automatischen Wiederholungen oder Revisionsänderungen.
- Zusätzliche lesbare Statusvariablen für Empfängerbereitschaft, Speicherrevision,
  Aktionsstatus und Fehler. Noch keine Ausführung oder Aktivierung.
- Einzeldatei-Artefakt reproduzierbar aus eigenen Quellen und Schema erzeugt.
  Host-Abhängigkeiten js-yaml 5.4.2 und Ajv 8.20.0 lokal mit Lockfile geprüft;
  Referenzvertrag Z2M 2.14.2. Keine reale Z2M-Abnahme.
- StylePHP und SymconStubs sind unveränderte offizielle Git-Submodule.
- DataFlowHelper, ParentConnectionHelper und VariablePresentationHelper sind
  unverändert über das kanonische Bundle-Verfahren manifestiert eingebunden.
  .helper-sync.json deklariert die Consumer-Subscription. Zentrale Freischaltung
  des Consumers und App-Berechtigungen für automatischen Sync sind noch zu prüfen;
  andere Repositories wurden für diese Implementierung nicht geändert.
- Das dynamische Formular verwendet den kanonisch eingebundenen
  ConfigurationFormHelper. Öffnen löst keine Netzwerkanfrage aus.
- GitHub-Identität in Metadaten und Badges: Burki24/Z2MAutomations.
- Lizenz gemäß Projektkonvention: PolyForm Noncommercial License 1.0.0.
- Initiale Metadaten 0.1 / Build 0 / Datum 0 bezeichnen keinen Release.

## Nächste Schritte

1. MQTT-Anbindung nach ausdrücklichem Auftrag mit realem Gateway und Z2M prüfen.
2. Erweiterung nach ausdrücklichem Praxisauftrag in Z2M prüfen und kompatible
   Versionen anhand realer Nachweise festlegen.
3. Grafischen Regeleditor auf Basis des bestehenden YAML-Vertrags entwickeln.
4. Ausführungsengine und getrennte Aktivierungsbestätigung entwickeln.

## Dokumentation

- [Architektur](docs/ARCHITECTURE.md)
- [ADR 0001](docs/adr/0001-execute-automations-in-z2m.md)
- [ADR 0002](docs/adr/0002-use-native-symcon-mqtt.md)
- [MQTT-Vertrag und Testgrenzen](docs/MQTT.md)
- [YAML-Schema](docs/YAML_SCHEMA.md)
- [Erweiterungsprotokoll und Persistenz](docs/EXTENSION_PROTOCOL.md)
- [ADR 0003](docs/adr/0003-versioned-yaml-storage.md)
- [ADR 0004](docs/adr/0004-correlated-module-workflows.md)
- [Entwicklung und Prüfungen](docs/DEVELOPMENT.md)

Keine Installation in Symcon oder Z2M und keine Praxisabnahme erfolgt.
