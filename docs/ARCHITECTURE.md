# Architektur

## Zuständigkeiten

Symcon ist der Editor und Verwalter. Zigbee2MQTT ist die Laufzeit.
Beide Teile erhalten eigenen Implementierungscode in diesem Repository:

- Z2MAutomations/: Symcon-Modul mit Formularen und künftig MQTT-Übertragung.
- libs/z2m/: eigener JavaScript-Erweiterungscode, sobald implementiert.
- libs/: librarylokale Logik, sobald benötigt.
- tests/: Offline-Tests mit synthetischen Daten.

Ein einzelnes Verwaltungsmodul ist der Einstieg. Weitere Module, Verbindungen
und öffentliche Datenfluss-GUIDs werden erst bei konkretem Bedarf eingeführt.
Das Grundgerüst legt keine künstliche MQTT-Verbindung und keine unfertigen
Konfigurationsfelder an.

## Geplanter Übertragungsweg

1. Z2M-Version und externe JavaScript-Freischaltung prüfen.
2. Eigene .mjs über <base>/bridge/request/libs/z2m/save übertragen.
3. Offizielle Antwort und eigene Bereitschaftsmeldung der Erweiterung auswerten.
4. YAML als String in JSON über ein eigenes MQTT-Topic übertragen.
5. Erweiterung validiert, speichert und aktiviert die Regeln.
6. Symcon zeigt erst nach Bestätigung die aktive Revision an.

Diese Schritte sind noch nicht implementiert. Der MQTT-Basistopic ist später
konfigurierbar. Eigene Topics und YAML-Schema sind noch nicht veröffentlicht.

## Persistenz und Fehlerfälle

Die Erweiterung soll die letzte gültige YAML im persistenten Z2M-Datenverzeichnis
halten und beim Neustart laden. Fehler dürfen die gültige Konfiguration nicht
ersetzen. Geplant sind Revisionskontrolle, Inhaltsprüfsumme, begrenzte Nachrichten,
wiederholbar sichere Aufträge und eine gesicherte vorherige Fassung. Persistenz,
Aktivierung und Rücknahme benötigen eigene Tests.

Code-Updates und Regeländerungen sind getrennte Vorgänge. Der offizielle Upload
schreibt JavaScript vor dem Laden und garantiert keinen automatischen Rollback.
Ein eigenes Updateverfahren muss diesen Fehlerfall berücksichtigen.

Keine Übertragungsbefehle mit MQTT-Retain speichern. Broker-Berechtigungen müssen
Code-Upload und Konfigurationsänderungen auf berechtigte Clients begrenzen.

## Externe Verträge

- [Externe Erweiterungen](https://www.zigbee2mqtt.io/advanced/more/external_extensions.html)
- [MQTT-API](https://www.zigbee2mqtt.io/guide/usage/mqtt_topics_and_messages.html)
- [JavaScript-Freischaltung](https://www.zigbee2mqtt.io/guide/configuration/all-settings.html#enable_external_js)
- [Upload-Implementierung](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/libs/z2m/externalJS.ts)

Quellenprüfung: 27.10.2026. Bei neuen Installationen ab Z2M 2.11.0 sind externe
Erweiterungen zunächst deaktiviert; die Freischaltung benötigt einen Neustart.
Die unterstützte Z2M-Mindestversion wird erst nach Verifikation festgelegt.
YAML-Upload ist eine eigene Erweiterungsfunktion; der offizielle Erweiterungsupload
akzeptiert nur JavaScript-Dateien.
