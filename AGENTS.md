# Z2MAutomations

Lies zuerst ../SymconDevelopment/AGENTS.md, ../SymconDevelopment/ARCHITECTURE.md,
die zentralen principles/ und die relevanten standards/. Sie gelten für dieses Projekt.

- Ziel: IP-Symcon 9.0 / PHP 8.5; eigenständige Library mit IPSModuleStrict.
- Zweck: Automationen in Symcon erstellen, als YAML übertragen und durch eine
  eigene externe Erweiterung direkt in Zigbee2MQTT ausführen lassen.
- Einstieg: PROJECT_CONTEXT.md, docs/ARCHITECTURE.md und docs/DEVELOPMENT.md.
- Modul: Z2MAutomations; öffentliches vierstelliges Präfix Z2MA.
- Eigene Implementierung von PHP, JavaScript, YAML-Schema und Tests. Kein Code,
  übersetzter Code oder interne Implementierungsstruktur aus
  Luligu/zigbee2mqtt-automations übernehmen. Externe Dokumentation beschreibt
  Schnittstellen und ist keine Kopiervorlage.
- MQTT ist der vorgesehene Übertragungsweg: offizielle Z2M-API für die Installation,
  eigene Erweiterungsbefehle für YAML-Übertragung und Aktivierung.
- Symcon verwaltet Regeln; ihre Laufzeit liegt in Z2M und benötigt nach der
  erfolgreichen Übertragung kein laufendes Symcon.
- Live-Installation, externe JavaScript-Freischaltung, Neustarts und reale
  Schaltbefehle benötigen einen ausdrücklichen Praxisauftrag.
- Offline-Tests verwenden synthetische Daten. Keine Zugangsdaten oder privaten
  Geräte- und Installationsdaten in Repository, Beispielen oder Logs.
- Vor jeder Umsetzung passende Helper in Symcon_ModuleHelper prüfen.
  libs/helper enthält nur unveränderte, manifestierte zentrale Quellen.
  Z2M-Protokoll und Automationslogik bleiben librarylokal.
- Lizenz: PolyForm Noncommercial License 1.0.0.
- Entwicklung auf dev. Commit und Push ausschließlich durch den Benutzer über
  GitHub Desktop. Keine Releases oder Veröffentlichung ohne gesonderten Auftrag.
- Nach Änderungen englischen Commit-Betreff und englische Erklärung liefern.
- Prüfung: php tests/run.php, php tests/validate_structure.php, offizielle
  StylePHP-Prüfung und php-cs-fixer im Prüfmodus.
- Erweiterung: libs/z2m/src/extension.mjs und automations.schema.json sind die
  Quellen. Das hochladbare libs/z2m/z2m-automations.mjs wird ausschließlich mit
  npm run build:extension erzeugt; npm run check:extension prüft seine Aktualität.
- npm ci installiert nur lokale Entwicklungs-/Testabhängigkeiten. Der Z2M-Host
  stellt js-yaml und Ajv bereit; keine fremden Implementierungen in das
  Erweiterungsartefakt kopieren. tests/run.php benötigt Node und npm ci.
