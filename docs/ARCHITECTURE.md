# Architektur

## Zuständigkeiten

Symcon ist der Editor und Verwalter. Zigbee2MQTT ist die Laufzeit.
Beide Teile erhalten eigenen Implementierungscode in diesem Repository:

- Z2MAutomations/: Symcon-Modul mit Formularen und künftig MQTT-Übertragung.
- libs/z2m/: eigene Erweiterungsquellen, kanonisches YAML-Schema und hochladbare
  Einzeldatei mit eingebettetem Schema.
- libs/Z2MProtocol.php: librarylokaler MQTT-/Z2M-Vertrag.
- libs/ExtensionContract.php und ExtensionIntegration.php: normalisierte
  Erweiterungsantworten und korrelierte Installations-/YAML-Workflows.
- libs/helper/: unverändertes, manifestiertes Bundle aus Symcon_ModuleHelper.
- libs/: librarylokale Logik, sobald benötigt.
- tests/: Offline-Tests mit synthetischen Daten.

Ein einzelnes Verwaltungsmodul ist der Einstieg. Weitere Module, Verbindungen
und öffentliche Datenfluss-GUIDs werden erst bei konkretem Bedarf eingeführt.
Das Modul verbindet sich direkt mit einem vorhandenen nativen Symcon MQTT Client
oder MQTT Server. Es gibt keinen eigenen Broker-Client und keinen Zwischenweg
über die separate Zigbee2MQTT-Gerätelibrary. Siehe
[ADR 0002](adr/0002-use-native-symcon-mqtt.md) und [MQTT-Vertrag](MQTT.md).

## Geplanter Übertragungsweg

1. Z2M-Version und externe JavaScript-Freischaltung prüfen.
2. Eigene .mjs über <base>/bridge/request/extension/save übertragen.
3. Offizielle Antwort und eigene Bereitschaftsmeldung der Erweiterung auswerten.
4. YAML als String in JSON über ein eigenes MQTT-Topic übertragen.
5. Erweiterung validiert, speichert und aktiviert die Regeln.
6. Symcon zeigt erst nach Bestätigung die aktive Revision an.

Die MQTT-Grundlage mit konfigurierbarem Basistopic, Bridge-Status und manuellem
health_check ist implementiert. Die Z2M-Seite empfängt und speichert inzwischen
versionierte YAML über eigene Topics; siehe [Erweiterungsprotokoll](EXTENSION_PROTOCOL.md)
und [YAML-Vertrag](YAML_SCHEMA.md). Die Symcon-Seite installiert das feste Artefakt
auf ausdrückliche Bedienung, prüft den Empfänger und überträgt nach Vorschau und
Bestätigung validierte YAML. Ausführungsengine und Aktivierung sind noch nicht
implementiert; siehe [ADR 0004](adr/0004-correlated-module-workflows.md).

## Persistenz und Fehlerfälle

Die Erweiterung hält die letzte gültige YAML im eigenen persistenten Unterordner
und lädt sie beim Neustart. Revisionskontrolle, Prüfsumme, Größenlimits,
idempotente Wiederholung und Sicherung der Vorversion sind implementiert.
Offline-Tests decken echte Dateischreibvorgänge, Schreibfehler und Wiederanlauf ab.
Aktivierung und Rücknahme ausführbarer Regeln gehören zur künftigen Engine.

Code-Updates und Regeländerungen sind getrennte Vorgänge. Der offizielle Upload
schreibt JavaScript vor dem Laden und garantiert keinen automatischen Rollback.
Ein eigenes Updateverfahren muss diesen Fehlerfall berücksichtigen.

Keine Übertragungsbefehle mit MQTT-Retain speichern. Broker-Berechtigungen müssen
Code-Upload und Konfigurationsänderungen auf berechtigte Clients begrenzen.

## Externe Verträge

- [Externe Erweiterungen](https://www.zigbee2mqtt.io/advanced/more/external_extensions.html)
- [MQTT-API](https://www.zigbee2mqtt.io/guide/usage/mqtt_topics_and_messages.html)
- [JavaScript-Freischaltung](https://www.zigbee2mqtt.io/guide/configuration/all-settings.html#enable_external_js)
- [Upload-Implementierung](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/extension/externalJS.ts)

Quellenprüfung: 27.10.2026. Bei neuen Installationen ab Z2M 2.11.0 sind externe
Erweiterungen zunächst deaktiviert; die Freischaltung benötigt einen Neustart.
Die unterstützte Z2M-Mindestversion wird erst nach Verifikation festgelegt.
YAML-Upload ist eine eigene Erweiterungsfunktion; der offizielle Erweiterungsupload
akzeptiert nur JavaScript-Dateien.
