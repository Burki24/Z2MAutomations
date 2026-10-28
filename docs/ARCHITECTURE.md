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
Bestätigung validierte YAML. Die eigene Engine verarbeitet eine ausdrücklich
aktivierte Speicherrevision; siehe [ADR 0005](adr/0005-explicit-execution.md).
Aktivierungs-/Stoppbuttons im Modul verwenden eine frische Abfrage, den
korrelierten Steuerauftrag und eine zusätzliche Statusverifikation.
Der erste Klick-Editor liest begrenzte diskrete Exposes und die gespeicherte
Konfiguration aus der Erweiterung. Neue Regeln werden lokal ergänzt; Prüfung,
Speicherung und Aktivierung bleiben ausdrücklich getrennte Bedienaktionen.
Regeln werden über stabile IDs ausgewählt. Bearbeiten und Löschen erzeugen nur
einen lokalen Entwurf. Eine Auswahlkennung bindet die Bedienung an den aktuellen
Entwurf und verhindert Änderungen aus veralteten Formularen. Einfache Regeln
werden vollständig bearbeitet; bei erweiterten Regeln bleiben die Strukturen
für sichere Namens-/Aktivierungsänderungen erhalten.
Die native Aktionsliste verwaltet bis zu 16 geordnete Geräteaktionen mit Wartezeit
vor jedem Schritt. Alle List-Zeilen werden atomar mit den Auslöser-/Bedingungsfeldern
in den Entwurf übernommen. Nur vollständig im Katalog darstellbare Folgen werden
bearbeitet; nicht unterstützte Strukturen werden nicht teilweise vereinfacht.
Geräteaktionen werden innerhalb Z2Ms an `eventBus.emitMQTTMessage` übergeben;
sie benötigen keine Rückzustellung eigener Set-Veröffentlichungen vom Broker.
Siehe [ADR 0007](adr/0007-native-command-dispatch.md).

## Persistenz und Fehlerfälle

Die Erweiterung hält die letzte gültige YAML im eigenen persistenten Unterordner
und lädt sie beim Neustart. Revisionskontrolle, Prüfsumme, Größenlimits,
idempotente Wiederholung und Sicherung der Vorversion sind implementiert.
Offline-Tests decken echte Dateischreibvorgänge, Schreibfehler und Wiederanlauf ab.
Die neue Engine startet inaktiv, verwirft bei Stopp Verzögerungen und wird durch
eine geänderte erfolgreich gespeicherte Revision deaktiviert. Kein automatischer
Wiederanlauf von Regeln nach einem Z2M- oder Erweiterungsneustart.

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
