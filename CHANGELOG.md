# Unveröffentlicht

## Fixes

- Geräteauslöser im Klick-Editor unterstützen Vergleichsoperatoren für
  Messwerte sowie Gleichheit/Ungleichheit für Zustände und Ereignisse.
- Geräte- und Zeitautomationen bieten eine Liste mehrerer UND-verknüpfter
  Bedingungen mit vollständiger Vorbelegung, Bearbeitung und Löschung.
  Das optionale Zeitfenster gilt zusätzlich; verständliche Debug-Ausgaben
  begleiten Auswahl, Prüfung und Übernahme.

- Die Kodierungsprüfung verwendet jetzt die gemeinsame Implementierung aus
  Symcon_ModuleCI. Lokal ist dessen Checkout erforderlich; Fremdquellen bleiben
  ausgeschlossen.

- Projektprüfungen erkennen beschädigte Umlaute und ungültiges UTF-8 frühzeitig.
  Eingebundene Fremdquellen bleiben von dieser Prüfung ausgenommen.

- Einzelne Schaltkanäle von Mehrkanalgeräten erhalten im begrenzten Katalog
  denselben Vorrang wie der allgemeine Zustand. Sonderoptionen verdrängen sie
  dadurch nicht mehr. Auswahl zeigt beispielsweise „Zustand (Kanal L3)“;
  Kanalzustände werden im bereinigten Katalog-Debug gezählt.

- Gespeicherte lokale Regeln lassen sich nach einem Library-Neuladen auch ohne
  erneute Empfängerbestätigung zur Ansicht auswählen. Name, Uhrzeit, Bedingungen
  und Aktionen werden vorbelegt; Änderungen und Übertragung behalten die
  Empfängerprüfung. Interne Editor-Sichtbarkeit wird nicht mehr irrtümlich
  an den sichtbaren Bereich weitergereicht.

- Bearbeitungsüberschriften bleiben für Geräte- und Zeitautomationen sichtbar.
  Aufklappen aktiviert den passenden Editor; erneutes Auf-/Zuklappen im aktiven
  Bereich setzt keine Eingaben zurück. Redundante Neu-Buttons entfernt; neue und
  vorhandene Regeln werden ausschließlich über die Auswahl bestimmt.

- Zusätzlichen YAML-Anzeigeschalter entfernt. Die YAML ist direkt im aufklappbaren
  Expertenbereich verfügbar. Prüfung und Speicherung verwenden den Editorinhalt;
  manuelle Änderungen bleiben beim grafischen Bearbeiten geschützt.

- Grundlegende Ein-/Aus-Auswahlwerte werden im begrenzten Gerätekatalog vor
  Sonderoptionen berücksichtigt. Viele Optionen früher Geräte verdrängen dadurch
  nicht mehr die normalen Schaltaktionen späterer Geräte. Erweiterung aktualisieren
  und Geräte neu laden; die bisherigen Größenlimits bleiben erhalten.

- Debug beim Löschen eines Aktionsschritts kennzeichnet die vom nativen Formular
  übergebene Liste als Vorbestand. Gelöschte Schritte erscheinen nicht mehr als
  vermeintlich verbleibende Folge; diese wird beim Übernehmen geprüft.

## Neu

- Gerätebereich um eine Auslöserliste mit ein bis acht Geräteereignissen ergänzt.
  Jeder Auslöser startet dieselbe Aktionsfolge (ODER), mit gemeinsamer Bedingung
  und optionaler Mindestdauer je Eintrag. Bearbeiten/Löschen und Wiederladen
  unterstützen Mehrfachregeln; alte Einzelaufrufe dürfen sie nicht verkürzen.

- Bearbeitung direkt in Geräte- und Zeitbereiche integriert: eigene Eingaben,
  Bedingungen, Aktionsfolgen und Übernehmen-/Löschen-Buttons. Die Auslöserart
  ist durch den Bereich festgelegt. Prüfung, Speicherung und Ausführungssteuerung
  bleiben gemeinsam. Veraltete bereichsfremde Formularaufrufe werden mit einer
  verständlichen deutschen Debug-Meldung abgewiesen.

- Natives Formular in geräteausgelöste und zeitgesteuerte Automationen aufgeteilt,
  jeweils mit eigener Übersicht, Regelauswahl und Neu-Button. Gemeinsame Bearbeitung
  führt durch Auslöser, optionale Bedingungen, Aktionen und Entwurfsübernahme.
  Einrichtung und YAML sind eingeklappt; Status und Stopp bleiben erreichbar.
  Deutsche Debug-Ausgaben erklären Bereichsauswahl und Ablehnungen.

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
