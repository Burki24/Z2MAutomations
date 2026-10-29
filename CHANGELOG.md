# Unveröffentlicht

## Features

- Aktionen können Geräte und Gruppen eines ausdrücklich ausgewählten zweiten
  Z2M-Systems schalten. Klick-Editor, Vorbelegung und Ereignisimport erhalten
  systemgebundene Ziele und prüfen weiterhin native Symcon-Schaltrechte.
- Eigener Mesh-Transport über eine vorhandene MQTT-Bridge mit kurzlebigen
  Einmalfreigaben, Rückmeldung der Befehlsübergabe und Schutz gegen doppelte
  oder alte Ausführung. Beide Seiten benötigen gespeicherte, ausdrücklich
  aktivierte Freigaben. Stopp sperrt auch eingehende Aktionen.
- Zusätzliche Statusvariable für freigegebene eingehende Aktionen und
  verständliche Debug-/Fehlermeldungen. Noch keine Installation dieses Stands.

## Fixes

- Gerätedaten für den Ereignisimport lassen sich nachladen, ohne vorhandene
  Entwürfe zu ersetzen. Abweichende gespeicherte Z2M-Stände und unübernommene
  YAML-Änderungen werden erkannt; es erfolgt kein stilles Überschreiben.
- Der begrenzte Gerätekatalog berücksichtigt nach Ein-/Aus-Kanälen zuerst
  Tasterereignisse und Auf-/Zu-/Stopp-Befehle. Umfangreiche Sonderoptionen
  anderer Geräte verdrängen diese Grundfunktionen nicht mehr.

- Die Importprüfung unterscheidet nicht unterstützte Variablen, fehlende
  Gerätedaten, andere MQTT-Verbindungen und fehlende Schaltrechte. Liste und
  Vorschau nennen die tatsächlich geprüfte Variable. Ein passendes Gateway wird
  nicht mehr fälschlich als Zuordnungsproblem dargestellt.
- Namenlose Symcon-Ereignisse erhalten beim Import einen bearbeitbaren Namen.
  Das ursprüngliche Ereignis wird nicht umbenannt; neue Regeln bleiben deaktiviert.

- Deutsche Status-, Fehler- und Hilfetexte für Einrichtung, Bearbeitung,
  Prüfung, Speicherung, Ausführung und Ereignisimport verständlicher formuliert.
  Meldungen nennen Ergebnis und nächsten Schritt; längere Hinweise nutzen die
  verfügbare Formularbreite. Technische Kennungen und Abläufe bleiben erhalten.

- Die Importprüfung nennt zuerst „Importierbar“, „Nicht importierbar“ oder
  „Noch nicht prüfbar“ und erklärt anschließend den Grund bzw. nächsten Schritt.

- Die Liste „Geprüfte Ereignisse“ zeigt 15 statt 5 Zeilen gleichzeitig,
  damit sich größere Ereignisbestände übersichtlicher durchsuchen lassen.

- Der Import löst native Ereignisziele aus dem Elternobjekt auf und unterstützt
  Instanzaktionen mit einer über IDENT ausgewählten Statusvariable und eingebetteter
  nativer Aktion. Ein gespeichertes TARGET ist dafür nicht erforderlich.
- Auslöser bei einem bestimmten Wert werden mit und ohne wiederholte Ausführung
  übernommen. „Nur bei Wertänderung“ bleibt im Klick-Editor bearbeitbar und wird
  in Z2M ausgeführt; unveränderte Meldungen werden nachvollziehbar verworfen.

- Die Ereignisübersicht und Auswahl zeigen das auslösende Gerät mit Namen und
  Symcon-Instanz-ID. Fehlende Gerätedaten werden mit verständlicher Handlungsanweisung
  erklärt. Ein direkter Ladebutton aktualisiert nach Bestätigung Übersicht und
  ausgewählte Vorschau; Fehler bleiben sichtbar und erteilen keine Importfreigabe.

- Die Ereignissuche prüft fremde Variablen und Elternobjekte vor Instanzzugriffen;
  Kategorien und fremde Module erzeugen keine ungültigen Instanz-/Property-Abfragen.
- Geräte, Gruppen und Ereignisse werden nach dem eigenen MQTT-Gateway und dem
  gewählten Basistopic getrennt. Zwei Server verwenden getrennte Automationsmodule.
  Erkannte Topics am eigenen Gateway lassen sich in den MQTT-Einstellungen auswählen.
  Die Ereignissuche benötigt keinen geladenen Z2M-Katalog; die Importprüfung schon.

- Unterstützte ausgelöste Symcon-Ereignisse lassen sich mit Prüfung und Vorschau
  als deaktivierte Regeln in den Klick-Editor importieren. Originalereignisse
  bleiben unverändert; geänderte Vorschauen und doppelte Imports werden abgewiesen.
- Gruppen stehen als schaltbare Aktionsziele bereit. Umschaltaktionen verwenden
  den aktuellen Z2M-Zustand und melden unbekannte Zustände verständlich als Fehler.

- Modulupdate, Formularöffnung und MQTT-Wiederverbindung lösen eine verzögerte
  einmalige Prüfung von Zigbee2MQTT und Erweiterungsstatus aus. Laufende Aufträge
  haben Vorrang; deutsche Debug-Ausgaben erklären Ablauf und Fehler.

- Listen in den Automationsformularen verwenden eine flexible Hauptspalte
  und kompakte Zeitspalten. Prozentbreiten der Spalten entfallen, damit
  Beschriftungen und Bedienknöpfe in der sichtbaren Formularbreite bleiben.

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
