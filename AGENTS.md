# Z2MAutomations

Lies zuerst ../SymconDevelopment/AGENTS.md, ../SymconDevelopment/ARCHITECTURE.md,
die zentralen principles/ und die relevanten standards/. Sie gelten fÃ¼r dieses Projekt.

- Ziel: IP-Symcon 9.0 / PHP 8.5; eigenstÃ¤ndige Library mit IPSModuleStrict.
- Zweck: Automationen in Symcon erstellen, als YAML Ã¼bertragen und durch eine
  eigene externe Erweiterung direkt in Zigbee2MQTT ausfÃ¼hren lassen.
- Einstieg: PROJECT_CONTEXT.md, docs/ARCHITECTURE.md und docs/DEVELOPMENT.md.
- Modul: Z2MAutomations; Ã¶ffentliches vierstelliges PrÃ¤fix Z2MA.
- Eigene Implementierung von PHP, JavaScript, YAML-Schema und Tests. Kein Code,
  Ã¼bersetzter Code oder interne Implementierungsstruktur aus
  Luligu/zigbee2mqtt-automations Ã¼bernehmen. Externe Dokumentation beschreibt
  Schnittstellen und ist keine Kopiervorlage.
- MQTT ist der vorgesehene Ãœbertragungsweg: offizielle Z2M-API fÃ¼r die Installation,
  eigene Erweiterungsbefehle fÃ¼r YAML-Ãœbertragung und Aktivierung.
- Symcon verwaltet Regeln; ihre Laufzeit liegt in Z2M und benÃ¶tigt nach der
  erfolgreichen Ãœbertragung kein laufendes Symcon.
- Live-Installation, externe JavaScript-Freischaltung, Neustarts und reale
  Schaltbefehle benÃ¶tigen einen ausdrÃ¼cklichen Praxisauftrag.
- Offline-Tests verwenden synthetische Daten. Keine Zugangsdaten oder privaten
  GerÃ¤te- und Installationsdaten in Repository, Beispielen oder Logs.
- Vor jeder Umsetzung passende Helper in Symcon_ModuleHelper prÃ¼fen.
  libs/helper enthÃ¤lt nur unverÃ¤nderte, manifestierte zentrale Quellen.
  Z2M-Protokoll und Automationslogik bleiben librarylokal.
- Lizenz: PolyForm Noncommercial License 1.0.0.
- Entwicklung auf dev. Commit und Push ausschlieÃŸlich durch den Benutzer Ã¼ber
  GitHub Desktop. Keine Releases oder VerÃ¶ffentlichung ohne gesonderten Auftrag.
- Nach Ã„nderungen englischen Commit-Betreff und englische ErklÃ¤rung liefern.
- PrÃ¼fung: php tests/run.php, php tests/validate_structure.php, offizielle
  StylePHP-PrÃ¼fung und php-cs-fixer im PrÃ¼fmodus.
- Erweiterung: libs/z2m/src/extension.mjs und automations.schema.json sind die
  Quellen. Das hochladbare libs/z2m/z2m-automations.mjs wird ausschlieÃŸlich mit
  npm run build:extension erzeugt; npm run check:extension prÃ¼ft seine AktualitÃ¤t.
- npm ci installiert nur lokale Entwicklungs-/TestabhÃ¤ngigkeiten. Der Z2M-Host
  stellt js-yaml und Ajv bereit; keine fremden Implementierungen in das
  Erweiterungsartefakt kopieren. tests/run.php benÃ¶tigt Node und npm ci.

- Debug ist für alle Module verpflichtend: ../SymconDevelopment/standards/DEBUG_OUTPUT.md
  und docs/DEBUG.md beachten. Öffentliche Einstiege, Entscheidungen und Datenfluss
  müssen mit deutschen, bereinigten nativen Debug-Ausgaben nachvollziehbar sein.
