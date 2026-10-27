# Z2MAutomations

[![Symcon PHPModul](https://img.shields.io/badge/Symcon-PHPModul-blue.svg)](https://www.symcon.de/)
[![Symcon Version](https://img.shields.io/badge/Symcon-9.0%2B-blue.svg)](library.json)
[![License](https://img.shields.io/badge/License-PolyForm%20Noncommercial%201.0.0-blue.svg)](LICENSE)
[![Check Style](https://github.com/Burki24/Z2MAutomations/actions/workflows/style.yml/badge.svg?branch=dev)](https://github.com/Burki24/Z2MAutomations/actions/workflows/style.yml?query=branch%3Adev)
[![Tests](https://github.com/Burki24/Z2MAutomations/actions/workflows/tests.yml/badge.svg?branch=dev)](https://github.com/Burki24/Z2MAutomations/actions/workflows/tests.yml?query=branch%3Adev)

Z2MAutomations soll die Einrichtung von Zigbee2MQTT-Automationen in Symcon
vereinfachen. Symcon erstellt die Regeln und überträgt sie als YAML an eine
eigene Erweiterung. Die Automationen werden direkt in Zigbee2MQTT ausgeführt.

Die MQTT-Anbindung empfängt den Z2M-Status, Versionsinformationen und die Liste
externer Erweiterungen. Eine Verbindungsprüfung sendet eine Gesundheitsanfrage
und zeigt das Ergebnis nach der passenden Antwort an.

Die eigene Z2M-Erweiterung lässt sich aus dem Modul über MQTT installieren.
Das Modul prüft YAML über den Empfänger und überträgt sie nach Vorschau und
Bestätigung in dessen persistenten Speicher. Die Erweiterung kann eine gespeicherte
Revision nach ausdrücklicher Aktivierung ausführen. Die Aktivierungs- und
Stoppbefehle der Erweiterung sind implementiert; ihre Formularintegration folgt
als eigener Schritt. [Ausführung und Grenzen](docs/RUNTIME.md).
Ein [synthetisches YAML-Beispiel](libs/z2m/examples/automations.yaml) zeigt das
eigene Format; der [YAML-Vertrag](docs/YAML_SCHEMA.md) beschreibt die Felder.

Ein grafischer Regeleditor folgt später.

## Voraussetzungen

- IP-Symcon ab Version 9.0 mit PHP 8.5.
- Zigbee2MQTT und eine konfigurierte Symcon-Instanz vom Typ MQTT Client oder
  MQTT Server, über die dieselbe Z2M-Installation erreichbar ist.
- Beim MQTT Client müssen die Z2M-Bridge- und Erweiterungstopics abonniert sein,
  beispielsweise zigbee2mqtt/bridge/# und zigbee2mqtt/symcon/automations/#.
  Ein vorhandenes Abonnement # genügt ebenfalls.
- Für die Erweiterungsinstallation muss externes JavaScript in Z2M bereits
  freigeschaltet sein. Das Modul ändert diese Einstellung nicht und startet Z2M nicht neu.

## Module

- [Z2MAutomations](Z2MAutomations/README.md): MQTT-Anbindung, Statusanzeige und
  Verbindungsprüfung als Grundlage der geplanten Automationsverwaltung.
- [Eigene Z2M-Erweiterung](libs/z2m/README.md): Empfänger mit Schema-Prüfung,
  persistentem Konfigurationsspeicher und Sicherung der vorherigen Fassung.

## Installation

Es gibt noch keine für den Live-Betrieb freigegebene Version.
In einer Entwicklungsinstallation die Instanz mit dem vorhandenen MQTT-Gateway
verbinden, das Basistopic einstellen und die MQTT-Anbindung aktivieren.
Details stehen in der [Modulanleitung](Z2MAutomations/README.md).

Die Anbindung ist zunächst deaktiviert. Aktivieren oder Öffnen des Formulars
sendet keine automatischen Anfragen. Bedienaktionen werden ausdrücklich über
die jeweiligen Schaltflächen gestartet. Details zum Ablauf für Installation,
YAML-Prüfung und Speicherung stehen in der Modulanleitung.

## Lizenz und Änderungen

PolyForm Noncommercial License 1.0.0, siehe [LICENSE](LICENSE).
Änderungen stehen im [Changelog](CHANGELOG.md).
