# Projektkontext: Z2MAutomations

## Ziel

Automationen komfortabel in Symcon erstellen und verwalten. Eine vollständig
eigene externe Zigbee2MQTT-Erweiterung empfängt die erzeugte YAML, speichert
sie persistent und führt sie unabhängig von Symcon aus.

## Stand

- Verbliebene rote Topic-Warnung lokal korrigiert: auch der JSON-codierte
  Live-Auswahlwert war ein falscher skalarer Wert. Native MultiSelect erhält
  jetzt weder beim Aufbau noch beim Update ein explizites value; die Zuordnung
  erfolgt aus BaseTopic. Regression gegen direkte Wertupdates ergänzt.
  Native Konsolenabnahme dieses korrigierten Stands steht noch aus.

- Gateway-Wechsel lokal korrigiert: Topic-Vorschläge und Hinweis werden bei
  FM_CONNECT/FM_DISCONNECT und ApplyChanges im bestehenden Formular aktualisiert.
  Native MultiSelect-Optionen besitzen durchgehend dieselbe BaseTopic-Zuweisung;
  kein leerer []-Platzhalter. Gespeicherte Topics und Entwürfe bleiben erhalten.
  Bei erkanntem Topic-Konflikt wartet die automatische Prüfung auf Übernahme.
  Synthetische Lifecycle-/Formulartests ergänzen die Gesamtsuite; dieser Stand
  wurde noch nicht installiert oder in der nativen Konsole abgenommen.

- Systemübergreifende Aktionen lokal implementiert: pro Modul ein ausdrücklich
  gewählter Partner mit anderem Basistopic; ein gemeinsamer Broker und derselbe
  MQTT-Client sind zulässig. Gateway und Topic werden zusammen geprüft, auch bei
  gleichen Geräte-/Gruppenkennungen. Dessen schaltbare Geräte/Gruppen werden
  aus dem lokalen Partnerkatalog angeboten. Quellen und Bedingungen bleiben lokal.
  Auswahl, Import deaktivierter Regeln, Vorbelegung und Speicherung sind offline
  geprüft. `mesh_peers` und `target.system` erweitern Schema 1 additiv.
  Zwei aktivierte Z2M-Engines kommunizieren über `z2ma/mesh/#` mit kurzlebiger,
  aktionsgebundener Einmalfreigabe und Bestätigung der nativen Übergabe.
  Stopp sperrt auch Empfang; keine Wiederholung und kein Start nach Neustart.
  Neue Statusvariable und sichere deutsche Debug-Ausgaben; siehe ADR 0009.
  Dieser Stand ist noch nicht installiert oder real geschaltet. Vor der späteren
  Praxisprüfung benötigen auch die Z2M-Benutzer Mesh-Rechte; die Bridge-Rechte
  allein genügen nicht. Keine Live-Konfiguration durch diese Implementierung.

- Reale Ereignisstrukturen auf ausdrücklich nur lesend freigegebener Produktion
  geprüft. Zwei Implementierungsfehler lokal korrigiert: natives Aktionsziel aus
  Event-Elternobjekt statt zwingendem TARGET; Statusvariable-schalten-Wrapper
  mit IDENT und verschachtelter JSON-Aktion. Keine produktiven Änderungen.
  Auslöser mit TriggerSubsequentExecution=false werden als `on_change: true`
  importiert. Der Klick-Editor erhält „Nur bei Wertänderung“ mit Vorbelegung und
  verlustfreier Übernahme. Schema/Runtime unterstützen den Vergleich gegen den
  Z2M-Ausgangszustand bzw. den letzten frischen Bericht; Diagnose erklärt
  unveränderte Meldungen. Quellen und Tests bleiben vollständig synthetisch.
  Neuer Modul-/Erweiterungsstand noch nicht installiert oder live importiert.

- Importformular verbessert: Ereignisse nennen das auslösende Gerät und auf
  ausdrücklichen Anwenderwunsch seine Symcon-Instanz-ID (auch in der Auswahl).
  Fehlende Z2M-Daten verweisen auf einen direkten, bestätigten Ladebutton.
  `LoadImportDevices` nutzt den bestehenden Editor-Workflow; dessen Abschluss
  aktualisiert Suche und ausgewählte Vorschau automatisch. Fehler werden
  kontextgebunden angezeigt, ohne Importfreigabe; lokaler Entwurf wird erst
  durch den bestätigten Ladevorgang wie beim regulären Editor-Laden ersetzt.
  Verzögerte Antworten, Timeout, Retry und vollständige Übernahme offline geprüft.
  Neuer Stand noch nicht in der Testinstallation geprüft.

- Ereignisimport korrigiert: fremde Variablen/Eltern werden vor Instanz- und
  Konfigurationszugriffen klassifiziert. Kein konkreter Instanz-ID-Bezug im Code.
  Native Z2M-Instanzen werden über Modultyp, eigene MQTT-ConnectionID und
  Basistopic entdeckt. Zwei MQTT-Server bleiben zwei Automationsinstanzen.
  Lokale Topic-Vorschläge befüllen das bestehende BaseTopic-Feld; manuelle Eingabe
  bleibt möglich. Ereignissuche läuft auch ohne Katalog, Import nur mit passendem
  Katalog. Such-/Vorschaucaches sind an Gateway/Topic gebunden.
  Offline gegen Kategorien, fremde Module und getrennte Gateways geprüft;
  neuer Modulstand noch nicht auf der Testinstallation geprüft.

- Erster Import ausgelöster nativer Symcon-Ereignisse implementiert: lesende Suche,
  Vorschau, erneute Prüfung und deaktivierte lokale Übernahme, ohne Originaländerung.
  Bestimmter Wert mit oder ohne Wiederholung, native boolesche/Textwerte und Umschalten;
  Bedingungen, Begrenzung, Skripte und ungesicherte Zahlenskalierung werden abgewiesen.
  Gruppen werden mit der Editorantwort geladen und sind Aktionsziele mit nativer
  schaltbarer Variable. Neue Schema-1-Aktion `toggle` kehrt den Z2M-Cachezustand um;
  fehlender Zustand führt zu `state_unavailable`. Siehe ADR 0008.
  Neuer Modul- und Erweiterungsstand noch nicht live installiert.

- Messwertvergleiche im Geräteauslöser-Dialog und mehrere UND-verknüpfte
  Gerätebedingungen im Geräte- und Zeiteditor implementiert. Vergleich,
  Schwellenwert, Mindestdauer und sämtliche Bedingungen werden vorbelegt.
  Ein optionales Zeitfenster bleibt separat; insgesamt maximal 16 Bedingungen.
  Offline mit echten Formularskripten und dem JavaScript-Empfänger geprüft,
  einschließlich Persistenz und synthetischer Ausführung bei wahren/falschen
  Bedingungen. Dieser neue Stand ist noch nicht live installiert.

- Mehrkanal-Katalog korrigiert: explizite state_l1/state_l2/state_l3-Exposes werden
  wie allgemeine Zustände priorisiert, ihre Eigenschaften unverändert übernommen.
  Kanalbeschriftungen und sichere Kanalanzahl im Debug ergänzt. Modul und neue
  Erweiterung auf Testinstallation aktualisiert; native Auswahl/Vorbelegung aller
  drei Kanäle und einer Kanalaktion sowie Z2M-Validierung bestätigt. Original-YAML
  unverändert, Revision 18 weiterhin gestoppt; keine Geräte geschaltet.

- Reale ODER-Prüfung: Anwender löste Radar und Mehrkanaltaster physisch aus;
  beide starteten dieselbe Ein-/Warte-/Aus-Folge am Testlicht. Beim Weg zum
  Taster entstand ein zusätzlicher Radar-Lauf, separat im Debug erkennbar.
  Anschließend gestoppt, ursprüngliche Regeln inhaltlich erhalten (Revision 18),
  Testlicht aus, Laufzeitfehler leer. Der Einzelkanal des Mehrkanaltasters fehlte
  beim damaligen Test im Klick-Katalog; die oben beschriebene Korrektur behebt dies.
  Siehe docs/DEVELOPMENT.md auch zu zurückgesetzten Diagnoseoptionen.

- Geräteeditor unterstützt ein bis acht ODER-Auslöser mit eigener Mindestdauer.
  Alle unterstützten Einträge werden wieder geladen. Alte Einzelaufrufe können
  Mehrfachregeln nicht verkürzen; gemischte Zeit-/Geräteregeln bleiben geschützt.
  Schema und Z2M-Erweiterung bleiben unverändert. Native List-Skripte, Validierung,
  Persistenz und alternative Ausführung durch zwei synthetische Geräte sind geprüft.
  Testinstallation aktualisiert; native Erstellung/Wiederladen/Bearbeiten/Löschen
  mit Bewegungsmelder und Taster sowie Z2M-Validierung bestätigt. Original-YAML
  wiederhergestellt; keine Live-Speicherung/Aktivierung, Z2M weiterhin gestoppt.

- Auswahl und Vorbelegung eines gültigen lokalen Entwurfs benötigen keine
  erneute Z2M-Bestätigung nach Verlust des flüchtigen Transportcaches. Bekannte
  Empfängerkonflikte, Scope-/YAML-Konflikte sowie jede Änderung/Übertragung bleiben
  geschützt. Live-Feldwerte und sichtbarer Zeitbereich sind im Regressionstest
  geprüft; Korrektur noch nicht auf der Testinstallation installiert.

- Beide Bearbeitungsüberschriften bleiben sichtbar. Öffnen aktiviert den passenden
  Bereich; Auf-/Zuklappen des aktiven Editors erhält ungespeicherte Eingaben.
  Die redundanten Neu-Buttons entfallen; die Auswahl enthält „Neue Automation
  erstellen“ und vorhandene Regeln. Native Panel-onClick-Skripte sind offline
  geprüft; visuelle Live-Abnahme dieses Korrekturstands steht aus.

- Geräte- und Zeitbereiche enthalten jetzt jeweils die vollständige Bearbeitung
  mit eigenen Feldnamen und festgelegter Auslöserart. Eine Auswahl ist jeweils
  aktiv; Wechsel setzen die Bearbeitung wie bisher zurück. Öffentliche Methoden
  besitzen optionale Bereichsparameter für Schutz vor verspäteten Formularaufrufen.
  Gemeinsame Validierung, Übertragung und Aktivierungs-/Stoppsteuerung bleiben
  erhalten. Echte Formularskripte sind offline auf Erstellen/Bearbeiten/Löschen
  beider Typen, Bedingungen, Aktionsfolgen und bereinigtes Debug geprüft. Die
  Live-Installation und visuelle schmale/breite Konsolenabnahme stehen aus.

- Der zusätzliche YAML-Anzeigeschalter entfällt. YAML ist direkt im eingeklappten
  Expertenbereich verfügbar; Formularaktionen berücksichtigen immer dessen Inhalt.
  Der bestehende ShowYamlEditor-Aufruf bedient kompatibel den Panelzustand.

- Native Formularbereiche trennen geräteausgelöste und zeitgesteuerte Regeln.
  Jede Übersicht bietet eine gefilterte Auswahl für neue/vorhandene Regeln; mehrere
  oder andere Auslöser bleiben unter weiteren Automationen erreichbar. Bearbeitung
  ist in Wenn/Nur wenn/Dann/Übernehmen gegliedert, Einrichtung und YAML eingeklappt.
  Dynamische Feldbefüllung berücksichtigt verschachtelte Bereiche. Offline wurden
  die echten Formularskripte, Zuordnung, Persistenz und Debug geprüft;
  Installation und schmale/breite Konsolenabnahme dieses Formularstands stehen aus.

- Entwicklung auf dev; kein Release. Installation und Schaltprüfung nur im
  ausdrücklich freigegebenen Testsystem durchgeführt.
- IPSModuleStrict-Verwaltungsmodul mit optional aktivierter MQTT-Anbindung.
- Native MQTT Client und MQTT Server werden als gemeinsam nutzbare Eltern
  angeboten; Gateway-Einstellungen und Subscriptions bleiben unverändert.
- Konfigurierbares Basistopic, lesbare Statusvariablen, normalisierter Bridge-Cache
  und asynchroner health_check mit Transaktion und Zeitlimit implementiert.
- Standardmäßig deaktiviert; keine automatischen Anfragen oder Schaltaktionen.
- Eigene JavaScript-Erweiterung und YAML-Schema Version 1 implementiert.
- Erweiterungsbefehle validate/store/get/status mit Protokollversion 1,
  Sitzungskennung, Revisionskontrolle, SHA-256 und serialisierter Verarbeitung.
- Persistente YAML mit Vorversion und temporärem, gespültem Schreiben vor rename.
  Beschädigte Hauptdateien führen mit gültiger Sicherung zu recovered_read_only.
- Installation über die offizielle MQTT-API mit anschließender korrelierter
  Empfängerprüfung im Modul integriert.
- YAML-Editor, Prüfung, Vorschau und bestätigte Speicherung aus dem Formular
  implementiert. Vor Store erneute Prüfung von Sitzung und Revision; kein
  blindes Überschreiben, keine automatischen Wiederholungen oder Revisionsänderungen.
- Zusätzliche lesbare Statusvariablen für Empfängerbereitschaft, Speicherrevision,
  Aktionsstatus und Fehler. Eigene Ausführungsengine mit ausdrücklicher
  MQTT-Aktivierung einer gespeicherten Revision implementiert; siehe docs/RUNTIME.md.
  Das Modul integriert Aktivierungs-/Stoppbuttons, Einmalkennung und zusätzliche
  frische Statusbestätigung. Ausführungsstatus, aktive Revision, Regelanzahl und
  Engine-Fehler besitzen eigene lesbare Statusvariablen.
- Erster Klick-Editor für diskrete Geräteereignisse und Set-Aktionen aus Z2M-Exposes.
  Vorhandene Regeln werden beim Ergänzen erhalten; YAML bleibt optional sichtbar.
  Gruppen benötigen weiterhin die erweiterte YAML-Ansicht.
- Native Aktionsliste für bis zu 16 geordnete Geräteaktionen implementiert:
  hinzufügen, bearbeiten, löschen und durch Ziehen umordnen; pro Schritt eine
  Wartezeit vor der Aktion von 0 bis 86400 Sekunden. Gespeicherte Folgen werden
  vollständig wieder geladen. Alte Einzelaktionsaufrufe dürfen sie nicht verkürzen.
  Offline-Integration prüft die echten Formularskripte, Validierung, Persistenz,
  verzögerte Ausführung für zwei synthetische Geräte und bereinigte deutsche
  Debug-Ausgaben. Schema 1 bleibt unverändert. Die Konsolenprüfung
  bestätigt Erstellen, Bearbeiten, Wiederladen und Löschen sowie Prüfung und
  Speicherung. Umsortieren wurde durch den Anwender manuell ausgeführt und durch
  den Agenten einschließlich Persistenz geprüft. Die unmittelbare Löschdiagnose
  kennzeichnet den nativen Vorbestand nun ausdrücklich und listet gelöschte
  Schritte nicht als verbleibende Folge auf. Regressionstest und erneute native
  Löschprüfung (2 auf 1 sowie 1 auf 0 Formularzeilen) bestätigen die Korrektur.
  Ursprüngliche Regeln wurden wiederhergestellt, die Engine blieb gestoppt.
  Eine per Klick erstellte Folge wurde real geprüft: Testlicht einschalten,
  fünf Sekunden warten, ausschalten. Frische Geräte-Rückmeldungen und native
  Z2M-Debug-Ausgaben bestätigten den Ablauf; anschließend gestoppt und bereinigt.
- Der begrenzte Gerätekatalog priorisiert jetzt grundlegende binäre state-Werte
  über alle berücksichtigten Geräte vor deren Sonderoptionen. Ein späteres
  Testlicht war zuvor wegen ausgeschöpften Auswahlbudgets nicht auswählbar.
  Regressionstests und die aktualisierte Testinstallation bestätigen die Korrektur;
  globale Größenlimits bleiben unverändert. Erweiterung aktualisieren und Katalog
  neu laden, um das korrigierte Verhalten zu verwenden.
- Uhrzeit-Auslöser, Zeitfenster mit Wochentagen sowie eine zusätzliche Geräte-
  oder Zahlenbedingung sind grafisch erstell- und bearbeitbar. Mindestdauer und
  Verzögerung werden übernommen. Die Erweiterung liefert dafür lesbare numerische
  Exposes. Dieser Stand wurde offline und in der nativen Testkonsole geprüft:
  Zeitregel mit Nachtfenster, Wochentagen und numerischer Bedingung erstellen,
  speichern, neu laden und bearbeiten; ungültige Uhrzeit wird sicher abgewiesen.
  Die Testregel bleibt deaktiviert, die Engine gestoppt. Keine Schaltprüfung
  der Zeitregel in diesem Bedienprüfungslauf.
- Bearbeiten einfacher Regeln und bestätigtes Löschen beliebiger vorhandener
  Regeln im lokalen Entwurf implementiert. Bei erweiterten Regeln sind Name und
  Aktivierung änderbar, ohne die Bedingungen oder Schritte zu ersetzen. Die neue
  Bedienung ist offline und in der nativen Testkonsole geprüft: Name/Aktivierung
  ändern, speichern, erneut laden sowie Löschen abbrechen/bestätigen und die leere
  Konfiguration speichern. Keine Geräteaktionen in diesem Prüflauf.
- DebugFlowHelper und dessen DebugHelper-Abhängigkeit sind kanonisch eingebunden.
  Die bestehende Fachinstrumentierung delegiert ihre Abläufe an den zentralen Helper.
- Einzeldatei-Artefakt reproduzierbar aus eigenen Quellen und Schema erzeugt.
  Host-Abhängigkeiten js-yaml 5.4.2 und Ajv 8.20.0 lokal mit Lockfile geprüft;
  Referenzvertrag Z2M 2.14.2; einzelner Geräteablauf mit 2.14.2-dev praktisch geprüft.
- StylePHP und SymconStubs sind unveränderte offizielle Git-Submodule.
- DataFlowHelper, ParentConnectionHelper und VariablePresentationHelper sind
  unverändert über das kanonische Bundle-Verfahren manifestiert eingebunden.
  .helper-sync.json deklariert die Consumer-Subscription. Zentrale Freischaltung
  des Consumers und App-Berechtigungen für automatischen Sync sind noch zu prüfen;
  andere Repositories wurden für diese Implementierung nicht geändert.
- Das dynamische Formular verwendet den kanonisch eingebundenen
  ConfigurationFormHelper. Öffnen plant eine verzögerte einmalige Statusprüfung;
  der Formularaufbau selbst sendet kein MQTT. Nach ApplyChanges und Wiederverbindung
  gilt dieselbe Prüfung mit Vorrang laufender Aufträge und zehn Sekunden Abstand.
- GitHub-Identität in Metadaten und Badges: Burki24/Z2MAutomations.
- Lizenz gemäß Projektkonvention: PolyForm Noncommercial License 1.0.0.
- Initiale Metadaten 0.1 / Build 0 / Datum 0 bezeichnen keinen Release.

## Nächste Schritte

1. Zeitregeln bei Bedarf nach weiterem Praxisauftrag auch live auslösen und
   weitere Geräte-/Bedingungskombinationen in der Konsole prüfen.
2. Weitere Aktionsfolgen und Geräte nach Praxisauftrag prüfen; Gruppen bei Bedarf
   im Klick-Editor ergänzen.
2. Weitere Z2M-Versionen, Geräte-/Gruppen- und Zeitregeln nach Praxisauftrag prüfen;
   der einzelne erfolgreiche Geräteablauf ist keine vollständige Kompatibilitätsabnahme.

## Dokumentation

- [Architektur](docs/ARCHITECTURE.md)
- [ADR 0001](docs/adr/0001-execute-automations-in-z2m.md)
- [ADR 0002](docs/adr/0002-use-native-symcon-mqtt.md)
- [MQTT-Vertrag und Testgrenzen](docs/MQTT.md)
- [YAML-Schema](docs/YAML_SCHEMA.md)
- [Erweiterungsprotokoll und Persistenz](docs/EXTENSION_PROTOCOL.md)
- [ADR 0003](docs/adr/0003-versioned-yaml-storage.md)
- [ADR 0004](docs/adr/0004-correlated-module-workflows.md)
- [Entwicklung und Prüfungen](docs/DEVELOPMENT.md)

Installation, Klick-Editor, YAML-Prüfung und Speicherung sowie Aktivierungs- und
Stoppbuttons wurden in der freigegebenen Testinstallation praktisch bestätigt.
Die gespeicherte Geräteautomation wurde real ausgelöst und ausgeführt; nach
Stopp blieb die erneute Geräteaktion unbeeinflusst. Die Engine übergibt Aktionen
direkt an Z2Ms native Befehlsverarbeitung (ADR 0007). Beim anschließenden
Bearbeiten-/Löschen-Test wurde die Testregel entfernt; die bestätigte leere
Konfiguration ist gestoppt. Siehe den aktuellen Nachweis in docs/DEVELOPMENT.md.
