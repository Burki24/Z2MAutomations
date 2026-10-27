# UnverÃ¶ffentlicht

## Neu

- MQTT-Empfangsfilter auf vollständige JSON-Umschläge angepasst; der Filter
  für deaktivierte oder ungültige Konfigurationen benötigt keine Lookarounds.

- Durchgängiges deutsches Moduldebug für Aufrufe, MQTT-Datenfluss, Status und
  Fehler ergänzt; sensible Nutzdaten bleiben ausgeschlossen.
- Passende frische Health-Antworten bestätigen jetzt auch ohne vorherigen
  Empfang von bridge/state die Z2M-Erreichbarkeit.

- Die eigene Z2M-Erweiterung lÃ¤sst sich jetzt aus dem Modul installieren und
  aktualisieren. Der EmpfÃ¤nger wird nach dem Upload ausdrÃ¼cklich geprÃ¼ft.
- YAML kann im Formular eingegeben, in Z2M geprÃ¼ft und nach Vorschau und
  BestÃ¤tigung gespeichert werden. Sitzung, Revision und PrÃ¼fsumme verhindern
  die Ãœbertragung eines geÃ¤nderten oder veralteten Entwurfs.
- Erweiterungsstatus, Speicherrevision und verstÃ¤ndliche Fehler werden im
  Formular und in zusÃ¤tzlichen lesbaren Statusvariablen angezeigt.
- Eigene Zigbee2MQTT-Erweiterung mit versioniertem YAML-Schema, Validierung und
  persistentem Speicher ergÃ¤nzt. UngÃ¼ltige Ãœbertragungen ersetzen keine gÃ¼ltige
  Konfiguration; die vorherige Fassung wird gesichert. Noch keine AusfÃ¼hrung.
- Vorhandene Symcon-MQTT-Client- und MQTT-Server-Verbindungen kÃ¶nnen fÃ¼r den
  Empfang von Zigbee2MQTT-Status, Version und Erweiterungsliste genutzt werden.
- Manuelle VerbindungsprÃ¼fung mit zugeordneter Antwort, Zeitlimit und lesbaren
  Statusvariablen ergÃ¤nzt. Die Anbindung ist standardmÃ¤ÃŸig deaktiviert.
- GrundgerÃ¼st fÃ¼r Z2MAutomations mit einem inaktiven Verwaltungsmodul angelegt.
