# UnverÃ¶ffentlicht

## Neu

- Klick-Editor um Aktionsfolgen mit bis zu 16 Schritten erweitert. Schritte lassen
  sich hinzufügen, bearbeiten, löschen und durch Ziehen umsortieren. Jeder Schritt
  besitzt eine Wartezeit; gespeicherte Folgen werden vollständig wieder geladen.
  Deutsche Debug-Ausgaben erklären Änderungen, Schrittreihenfolge und Wartezeiten.

- Klick-Editor um tägliche Uhrzeit-Auslöser, Zeitzonen, Zeitfenster mit Wochentagen
  und eine zusätzliche Zustands- oder Zahlenbedingung erweitert. Mindestdauer und
  Aktionsverzögerung sind auswählbar; Regeln werden zum Bearbeiten wieder vorbelegt.
  Der begrenzte Z2M-Katalog liefert lesbare Messgrößen und Einheiten. Neue Abläufe
  verwenden bereinigte deutsche Debug-Ausgaben.

- Klick-Editor um Auswahl, Bearbeitung und bestätigtes Löschen bestehender Regeln
  erweitert. Einfache Regeln behalten ihre ID; andere Regeln bleiben unverändert.
  Name und Aktivierung erweiterter Regeln können ohne Verlust ihrer Schritte
  geändert werden. Veraltete Formularauswahlen werden abgewiesen.

- Dynamische Geräte-/Aktionsauswahl und Entwurfsliste werden für die native
  Konsole JSON-codiert aktualisiert. Regressionstest prüft diesen SDK-Vertrag.
- Set-Aktionen werden direkt an Z2Ms native Befehlsverarbeitung übergeben.
  Ihre Ausführung benötigt keine MQTT-Rückzustellung eigener Veröffentlichungen.

- Aktivierungs- und Stoppbuttons mit frischer Statusbestätigung, aktiver Revision,
  Regelanzahl und verständlichen Ausführungsfehlern im Symcon-Modul ergänzt.
- Erster Klick-Editor lädt Geräte aus Z2M und erstellt einfache Regeln durch
  Auswahl von Quelle, Ereignis, Ziel und Aktion. Bestehende Regeln bleiben erhalten;
  YAML ist nur noch eine zusätzliche erweiterte Ansicht.
- Debug-Abläufe verwenden den zentralen DebugFlowHelper. Tests belegen die
  Z2M-Ausführung auch bei getrenntem Symcon-Datenfluss.

- Eigene Ausführungsengine für gespeicherte YAML mit Geräte-/Zeittriggern,
  Bedingungen, Mindestdauer und geordneten verzögerten Set-Aktionen ergänzt.
- Ausführung wird getrennt per MQTT aktiviert und gestoppt. Aktivierung prüft
  Sitzung, Revision, Prüfsumme und eine frische Einmalkennung. Neustart oder
  geänderte Speicherung starten keine Regeln automatisch.

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
