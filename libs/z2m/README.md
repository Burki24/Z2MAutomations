# Eigene Zigbee2MQTT-Erweiterung

Die eigene Erweiterung empfängt Automationen über MQTT, validiert YAML und
Schema und speichert die letzte gültige Fassung im persistenten Z2M-Datenordner.
Automationen werden in dieser Ausbaustufe nicht ausgeführt.

- Hochladbare Einzeldatei: z2m-automations.mjs.
- Eigener Quellcode: src/extension.mjs.
- Kanonisches Schema: automations.schema.json, JSON Schema Draft 7.
- Synthetisches Beispiel: examples/automations.yaml.

Das Einzeldatei-Artefakt enthält unser Schema und unseren Code. Ajv und js-yaml
werden aus den vorhandenen Z2M-Abhängigkeiten geladen. Ein separates Hochladen
von Schema- oder Hilfsdateien ist nicht erforderlich. Das Artefakt wird mit
npm run build:extension erstellt und durch npm run check:extension geprüft.

Vertragsziel dieser Ausbaustufe: Z2M 2.14.2 mit Node ab 22.2, js-yaml 5.4.2
und Ajv 8.20.0. Genau diese Parser-/Validatorstände sind als lokale
Testabhängigkeiten festgelegt. Eine reale Z2M-Installation ist noch nicht geprüft.

Der eigene Constructor nutzt die offiziell dokumentierten zehn Z2M-Argumente;
ein optionales elftes Argument ersetzt ausschließlich im Test Dateisystem und
Datenverzeichnis. In Z2M leitet sich der Speicherort aus der tatsächlichen Lage
der .mjs unter data/external_extensions ab, auch bei temporären Importkopien.

Details: [YAML-Vertrag](../../docs/YAML_SCHEMA.md),
[MQTT- und Speicherprotokoll](../../docs/EXTENSION_PROTOCOL.md).

Verträge und Zuständigkeiten stehen in [Architektur](../../docs/ARCHITECTURE.md).
Keine Implementierung aus fremden Automationsprojekten übernehmen.
