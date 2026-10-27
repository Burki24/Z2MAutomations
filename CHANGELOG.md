# Unveröffentlicht

## Neu

- Die eigene Z2M-Erweiterung lässt sich jetzt aus dem Modul installieren und
  aktualisieren. Der Empfänger wird nach dem Upload ausdrücklich geprüft.
- YAML kann im Formular eingegeben, in Z2M geprüft und nach Vorschau und
  Bestätigung gespeichert werden. Sitzung, Revision und Prüfsumme verhindern
  die Übertragung eines geänderten oder veralteten Entwurfs.
- Erweiterungsstatus, Speicherrevision und verständliche Fehler werden im
  Formular und in zusätzlichen lesbaren Statusvariablen angezeigt.
- Eigene Zigbee2MQTT-Erweiterung mit versioniertem YAML-Schema, Validierung und
  persistentem Speicher ergänzt. Ungültige Übertragungen ersetzen keine gültige
  Konfiguration; die vorherige Fassung wird gesichert. Noch keine Ausführung.
- Vorhandene Symcon-MQTT-Client- und MQTT-Server-Verbindungen können für den
  Empfang von Zigbee2MQTT-Status, Version und Erweiterungsliste genutzt werden.
- Manuelle Verbindungsprüfung mit zugeordneter Antwort, Zeitlimit und lesbaren
  Statusvariablen ergänzt. Die Anbindung ist standardmäßig deaktiviert.
- Grundgerüst für Z2MAutomations mit einem inaktiven Verwaltungsmodul angelegt.
