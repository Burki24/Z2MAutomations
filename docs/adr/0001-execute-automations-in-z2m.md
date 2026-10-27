# ADR 0001: Automationen in Zigbee2MQTT ausführen

- Datum: 27.10.2026
- Status: Angenommen

## Kontext

Regeln sollen bequem in Symcon erstellt und als YAML an Zigbee2MQTT übertragen
werden. Danach sollen sie auch ohne laufendes Symcon ausgeführt werden.
Fremder Automationscode darf nicht übernommen werden.

## Entscheidung

Die eigene Symcon-Library erzeugt die Konfiguration. Eine eigene externe
JavaScript-Erweiterung in Z2M übernimmt Validierung, Persistenz und Ausführung.
Die Installation nutzt die offizielle MQTT-Erweiterungsschnittstelle.
Die separate YAML-Übertragung nutzt eigene Befehle der Erweiterung.

Luligu/zigbee2mqtt-automations dient ausschließlich als fachliche Inspiration.
Kein Fork, keine Übersetzung und keine Übernahme des Codes oder interner Struktur.
Dokumentierte Z2M-Verträge sind die Grundlage für unsere eigene Implementierung.

## Folgen

Beide Laufzeitumgebungen benötigen Tests. Z2M-Updates können Erweiterungsverträge
ändern; unterstützte Versionen sind ausdrücklich zu prüfen. Installation,
JavaScript-Freischaltung und nötige Neustarts sind sichtbare, bewusste Aktionen.
Das Grundgerüst führt diese Aktionen noch nicht aus.
