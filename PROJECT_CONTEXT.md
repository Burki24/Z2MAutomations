# Projektkontext: Z2MAutomations

## Ziel

Automationen komfortabel in Symcon erstellen und verwalten. Eine vollständig
eigene externe Zigbee2MQTT-Erweiterung empfängt die erzeugte YAML, speichert
sie persistent und führt sie unabhängig von Symcon aus.

## Stand

- Neues lokales Repository auf dev, ohne Commit, Remote oder Veröffentlichung.
- Library-Metadaten und ein IPSModuleStrict-Verwaltungsmodul vorhanden.
- Das Modul bleibt mit Status 104 inaktiv und zeigt den Entwicklungsstand.
- Noch keine Properties, Variablen, Timer, MQTT-Verbindungen oder Schaltaktionen.
- JavaScript-Erweiterung und YAML-Schema sind noch nicht implementiert.
- StylePHP und SymconStubs sind unveränderte offizielle Git-Submodule.
- Vorhandene Helper und ihre Zuständigkeiten wurden geprüft. Das statische
  Grundgerüst benötigt noch keinen Helper. Vor dynamischen Formularen und
  Datenfluss insbesondere ConfigurationFormHelper und DataFlowHelper prüfen.
- Vorgesehener GitHub-Name für Metadaten und Badges: Burki24/Z2MAutomations.
  Ein GitHub-Repository wurde nicht erstellt.
- Lizenz gemäß Projektkonvention: PolyForm Noncommercial License 1.0.0.
- Initiale Metadaten 0.1 / Build 0 / Datum 0 bezeichnen keinen Release.

## Nächste Schritte

1. Vorhandene Symcon-MQTT-Anbindung prüfen und konkreten Datenfluss festlegen.
2. Unterstützte Z2M-Version festlegen und Erweiterungsverträge dagegen prüfen.
3. YAML-Schema und versioniertes MQTT-Protokoll mit synthetischen Fixtures definieren.
4. Eigene Erweiterung mit Validierung, Persistenz und bestätigter Aktivierung bauen.
5. Regel-Formulare, Vorschau und bewusstes Übertragen implementieren.
6. Ausdrücklich beauftragte Praxisprüfung auf einer Entwicklungsinstallation.

## Dokumentation

- [Architektur](docs/ARCHITECTURE.md)
- [ADR 0001](docs/adr/0001-execute-automations-in-z2m.md)
- [Entwicklung und Prüfungen](docs/DEVELOPMENT.md)

Keine Installation in Symcon oder Z2M und keine Praxisabnahme erfolgt.
