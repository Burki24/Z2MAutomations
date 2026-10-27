# Z2MAutomations

[![Symcon PHPModul](https://img.shields.io/badge/Symcon-PHPModul-blue.svg)](https://www.symcon.de/)
[![Symcon Version](https://img.shields.io/badge/Symcon-9.0%2B-blue.svg)](library.json)
[![License](https://img.shields.io/badge/License-PolyForm%20Noncommercial%201.0.0-blue.svg)](LICENSE)
[![Check Style](https://github.com/Burki24/Z2MAutomations/actions/workflows/style.yml/badge.svg?branch=dev)](https://github.com/Burki24/Z2MAutomations/actions/workflows/style.yml?query=branch%3Adev)
[![Tests](https://github.com/Burki24/Z2MAutomations/actions/workflows/tests.yml/badge.svg?branch=dev)](https://github.com/Burki24/Z2MAutomations/actions/workflows/tests.yml?query=branch%3Adev)

Z2MAutomations soll die Einrichtung von Zigbee2MQTT-Automationen in Symcon
vereinfachen. Symcon erstellt die Regeln und überträgt sie als YAML an eine
eigene Erweiterung. Die Automationen werden direkt in Zigbee2MQTT ausgeführt.

Aktuell liegt ein Grundgerüst vor. Es kann noch keine Regeln erstellen,
Erweiterungen installieren oder Automationen übertragen und ausführen.

## Voraussetzungen

- IP-Symcon ab Version 9.0 mit PHP 8.5.
- Für den späteren Betrieb: Zigbee2MQTT und ein erreichbarer MQTT-Broker.
- Für die spätere Erweiterungsinstallation muss externes JavaScript in Z2M
  freigeschaltet sein.

## Module

- [Z2MAutomations](Z2MAutomations/README.md): Verwaltung der Automationen;
  aktuell ein inaktives Grundgerüst mit Hinweis im Konfigurationsformular.

## Installation

Es gibt noch keine veröffentlichte oder für den Live-Betrieb freigegebene
Version. Das Grundgerüst nimmt keine Verbindung zu Zigbee2MQTT auf.

## Lizenz und Änderungen

PolyForm Noncommercial License 1.0.0, siehe [LICENSE](LICENSE).
Änderungen stehen im [Changelog](CHANGELOG.md).
