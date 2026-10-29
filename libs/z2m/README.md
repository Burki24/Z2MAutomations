# Eigene Zigbee2MQTT-Erweiterung

Aktionen können über einen gemeinsamen Broker oder eine vorhandene Broker-Bridge
an ein zweites Z2M-System übergeben werden. Der eigene Mesh-Transport nutzt ausschließlich `z2ma/mesh/#`
und verlangt eine aktivierte Empfangsfreigabe auf der Gegenseite. Kurzlebige
Einmalfreigaben verhindern zusätzliche Ausführung durch alte oder doppelte
Execute-Nachrichten. Der neue Stand wurde noch nicht praktisch installiert;
siehe [ADR 0009](../../docs/adr/0009-cross-system-actions-over-mqtt-bridge.md).

Die eigene Erweiterung empfängt Automationen über MQTT, validiert YAML und
Schema und speichert die letzte gültige Fassung im persistenten Z2M-Datenordner.
Eine gespeicherte Revision kann anschließend ausdrücklich aktiviert werden.
Die Engine unterstützt das bestehende Schema mit Geräte-/Zeittriggern,
Bedingungen und geordneten Set-/Umschaltaktionen. Speichern allein aktiviert nichts.
Das Symcon-Modul bietet Aktivierungs-/Stoppbuttons und einen ersten Klick-Editor.
Die Erweiterung stellt dafür einen begrenzten Gerätekatalog aus diskreten Exposes
und die bereits gespeicherte Konfiguration bereit. Nach der Aktivierung laufen
Regeln ohne Symcon weiter; ein Z2M-Neustart erfordert weiterhin erneutes Aktivieren.

- Hochladbare Einzeldatei: z2m-automations.mjs.
- Eigener Quellcode: src/extension.mjs, src/runtime.mjs und src/mesh.mjs.
- Kanonisches Schema: automations.schema.json, JSON Schema Draft 7.
- Synthetisches Beispiel: examples/automations.yaml.

Das Einzeldatei-Artefakt enthält unser Schema und unseren Code. Ajv und js-yaml
werden aus den vorhandenen Z2M-Abhängigkeiten geladen. Ein separates Hochladen
von Schema- oder Hilfsdateien ist nicht erforderlich. Das Artefakt wird mit
npm run build:extension erstellt und durch npm run check:extension geprüft.

Vertragsziel dieser Ausbaustufe: Z2M 2.14.2 mit Node ab 22.2, js-yaml 5.4.2
und Ajv 8.20.0. Genau diese Parser-/Validatorstände sind als lokale
Testabhängigkeiten festgelegt. Installation und YAML-Übertragung wurden praktisch
geprüft; für die neue Engine stehen Live-Upload und Schaltversuch noch aus.

Der eigene Constructor nutzt die offiziell dokumentierten zehn Z2M-Argumente;
ein optionales elftes Argument ersetzt ausschließlich im Test Dateisystem und
Datenverzeichnis sowie Uhr/Timer. In Z2M leitet sich der Speicherort aus der tatsächlichen Lage
der .mjs unter data/external_extensions ab, auch bei temporären Importkopien.

Details: [YAML-Vertrag](../../docs/YAML_SCHEMA.md),
[MQTT- und Speicherprotokoll](../../docs/EXTENSION_PROTOCOL.md),
[Ausführung und Aktivierung](../../docs/RUNTIME.md).

Verträge und Zuständigkeiten stehen in [Architektur](../../docs/ARCHITECTURE.md).
Keine Implementierung aus fremden Automationsprojekten übernehmen.

## Gruppen und Umschalten

Editorantworten enthalten zusätzlich bis zu 256 Gruppen mit stabiler ID und
Anzeigename. Symcon prüft die zugehörige native Aktionsvariable und bietet
Ein-/Aus-Aktionen an. Die neue Schema-1-Variante `{type: toggle, target, property}`
verwendet `state.get(entity)` des Z2M-Hosts. Ein bekannter ON/OFF-Zustand wird
invertiert und als normaler Set-Wert übergeben. Ohne Cachewert endet die Folge mit
`state_unavailable`; ohne Host-Schnittstelle wird die Aktivierung abgewiesen.
Gruppen werden anhand ihres Aggregatzustands einheitlich ein-/ausgeschaltet.
`toggle` besitzt keine `values`, `set` keine `property`.
