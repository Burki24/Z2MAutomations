# Z2MAutomations

Dieses Modul verbindet die geplante Automationsverwaltung mit Zigbee2MQTT über
die vorhandene MQTT-Anbindung von Symcon.

## Voraussetzungen und Einrichtung

IP-Symcon ab 9.0 mit PHP 8.5 sowie eine konfigurierte MQTT-Client- oder
MQTT-Server-Instanz, die mit derselben Z2M-Installation verbunden ist.

1. Instanz anlegen und als übergeordnete Verbindung den vorhandenen MQTT Client
   oder MQTT Server auswählen. Gemeinsame Nutzung mit anderen Modulen ist möglich.
2. Das Basistopic der Z2M-Installation eintragen.
3. Bei MQTT Client das Basistopic mit /bridge/# und /symcon/automations/#
   abonnieren. Ein umfassenderes Abonnement wie # genügt. Die Gateway-
   Einstellungen werden nicht geändert.
4. „MQTT-Anbindung aktivieren“ einschalten und Änderungen übernehmen.
5. Bei Bedarf „Zigbee2MQTT-Verbindung prüfen“ betätigen. Das Ergebnis erscheint
   in der Statusvariable „Verbindungsprüfung“.

Broker, Zugangsdaten und TLS werden ausschließlich im vorhandenen Gateway und
seiner Socket-Verbindung eingerichtet. Die Library benötigt keine eigene Kopie.

## Erweiterung installieren

Z2M muss online melden; bridge/info muss aktiviertes externes JavaScript bestätigen.
Der geprüfte Erweiterungsvertrag setzt Z2M 2.14.2 mit Node ab 22.2, js-yaml 5.4.2
und Ajv 8.20.0 voraus. Die real unterstützten Z2M-Installationen sind noch nicht
praktisch abgenommen.

„Mitgelieferte Erweiterung installieren oder aktualisieren“ lädt ausschließlich
die mitgelieferte z2m-automations.mjs über MQTT hoch. Die Bestätigung weist auf
das Ersetzen einer bereits vorhandenen Datei dieses Namens hin. Nach der
Z2M-Upload-Antwort fragt das Modul den Empfänger aktiv ab. Erst dessen passende
Antwort bestätigt die erfolgreiche Installation. Eine vorhandene Datei oder
ein gespeicherter MQTT-Status allein genügen nicht.

Die Z2M-API schreibt die JavaScript-Datei vor dem Laden. Ein Ladefehler ist
deshalb kein Nachweis einer unveränderten früheren Datei. Das Modul führt
keine automatische Rücknahme auf eine vorherige JavaScript-Version aus.

Bei bereits installierter Erweiterung genügt „Erweiterungsstatus abfragen“.
Das Modul aktiviert kein externes JavaScript und löst keinen Z2M-Neustart aus.

## Einfache Automation per Klick erstellen

1. Neue mitgelieferte Erweiterung installieren bzw. aktualisieren und den
   Erweiterungsstatus abfragen.
2. „Geräte und gespeicherte Automationen laden“ bestätigen. Damit wird der lokale
   Entwurf neu geladen; die in Z2M gespeicherten Regeln werden noch nicht geändert.
3. Den Bereich „Durch ein Gerät ausgelöste Automationen“ öffnen und in der Auswahl
   „Neue Automation erstellen“ wählen und „Automation bearbeiten“ aufklappen. Die Bearbeitung öffnet sich direkt in diesem Bereich.
   Namen eingeben und unter „Wenn ein Gerät ein Ereignis meldet“ in der
   Auslöserliste ein Gerät und Ereignis hinzufügen, beispielsweise
   „Tasterereignis: Einfach drücken“. Weitere Auslöser sind optional.
   Für eine Uhrzeitregel stattdessen im Bereich „Zeitgesteuerte Automationen“
   „Neue Automation erstellen“ wählen, die Bearbeitung aufklappen und
   Uhrzeit/Zeitzone einstellen.
4. Bei Bedarf „Nur wenn … (optional)“ öffnen und Bedingungen hinzufügen.
   Unter „Dann diese Aktionen ausführen“ einen Schritt hinzufügen, Gerät und Aktion auswählen,
   beispielsweise „Zustand: Ein“, und die Wartezeit vor diesem Schritt eingeben.
5. „Automation zum Entwurf hinzufügen“ betätigen. Weitere einfache Regeln können
   vor der Übertragung ergänzt werden. Vorhandene gespeicherte Regeln bleiben
   im Entwurf vollständig erhalten.
6. „Entwurf in Z2M prüfen“ und anschließend „Geprüften Entwurf in Z2M speichern“
   bestätigen. Die YAML wird automatisch erzeugt; sie muss weder angezeigt noch
   bearbeitet werden. Die Liste zeigt Namen, erste Quelle, erstes Ziel und den
   gewünschten Aktivierungszustand der Regeln im Entwurf.
7. „Gespeicherte Automationen in Z2M aktivieren“ bestätigen.

Der erste Editor unterstützt diskrete Geräteereignisse und Geräteaktionen aus
den Z2M-Exposes (Enum und Binary): etwa Taster, Bewegung oder Ein-/Ausschalten.
Nicht jedes Gerät besitzt solche auswählbaren Eigenschaften. Maximal die ersten
256 Geräte und insgesamt 1024 Auswahlwerte werden geladen. Grundlegende binäre
Zustandswerte einschließlich einzelner Schaltkanäle erhalten über diese Geräte
hinweg Vorrang vor Sonderoptionen;
unpassende Exposes
werden ausgelassen. Geräte werden technisch anhand ihrer IEEE-Adresse ausgewählt;
die Adresse neben dem Namen unterscheidet gleichnamige Geräte. Gruppen, numerische
Geräteaktionen und gemischte Geräte-/Zeitauslöser bleiben der erweiterten
YAML-Ansicht vorbehalten. Bestehende Regeln außerhalb des Formularumfangs können
umbenannt, deaktiviert oder gelöscht werden; ihre zusätzlichen Schritte bleiben erhalten.

## Übersicht und Bearbeitung

Geräteausgelöste und zeitgesteuerte Automationen besitzen jeweils eine eigene
Übersicht und die Auswahl „Automation bearbeiten“. Die Auswahl lädt die Regel
in die Bearbeitung direkt im jeweiligen Bereich. Dort erscheinen nur die
passenden Auslöserfelder; bestehende Bedingungen werden aufgeklappt. Jeder
Bereich besitzt eigene Eingabefelder, eine Aktionsliste und Übernehmen-/Löschen-
Buttons. Die Auslöserart ergibt sich aus dem Bereich und wird nicht zusätzlich
ausgewählt. Es wird jeweils eine Regel bearbeitet; vor dem Wechsel Änderungen
in den lokalen Entwurf übernehmen. Beide Bearbeitungsüberschriften bleiben
sichtbar; das Öffnen aktiviert den jeweiligen Bereich. Ein zusätzlicher Neu-Button
ist nicht erforderlich.
Regeln mit mehreren oder anderen Auslösern bleiben mit ihrer sicheren
Metadatenbearbeitung unter „Weitere Automationen“ erreichbar. Unterstützte
Bearbeitung und der Schutz erweiterter Regeln gelten weiter.

„Prüfen und an Z2M übertragen“ enthält Prüfung, Speicherung und Aktivierung.
Aktivieren und Stoppen beziehen sich auf die gesamte gespeicherte Konfiguration.
Ausführungsstatus und Stopp stehen oben. MQTT-Einstellungen, Einrichtung/Diagnose
und der erweiterte YAML-Editor sind normalerweise eingeklappt. Die Bearbeitung
ändert zuerst den lokalen Entwurf; Z2M ändert sich erst beim Speichern.

## Mehrkanaltaster und Schaltkanäle

Einzelne Kanäle erscheinen bei Auslösern und Aktionen als „Zustand (Kanal L1)“,
„Zustand (Kanal L2)“ usw., jeweils mit Ein/Aus. Den passenden Kanal ausdrücklich
wählen. Der allgemeine Zustand bleibt davon getrennt; er ersetzt keinen Kanal.
Beim erneuten Auswählen einer Automation werden Kanal, Zustand und Mindestdauer
bzw. Wartezeit wieder geladen. Nach diesem Update die mitgelieferte Erweiterung
aktualisieren und anschließend Geräte und gespeicherte Automationen neu laden.

## Mehrere Geräteauslöser (ODER)

Im Gerätebereich enthält die Auslöserliste ein bis acht Einträge. Jeden Eintrag
über Hinzufügen anlegen und Gerät mit Ereignis/Zustand wählen. Bewegungsmelder,
Taster und andere angebotene Geräteereignisse können kombiniert werden. Sobald
einer der Einträge zutrifft, kann dieselbe Aktionsfolge starten. Die zusätzlichen
Bedingungen gelten für jeden Auslöser gemeinsam. Eine UND-Verknüpfung mehrerer
Ereignisse ist hier nicht vorgesehen.

„Mindestdauer des Zustands“ wird je Eintrag eingestellt: 0 reagiert sofort,
1 bis 86400 fordert einen entsprechend lange anliegenden Zustand. Für einen
kurzen Tastendruck normalerweise 0 verwenden. Einträge sind bearbeitbar und
löschbar; ihre Reihenfolge ist für ODER ohne Bedeutung. Vorhandene Einzelregeln
erscheinen als eine Zeile, unterstützte Mehrfachregeln mit allen Einträgen.

Danach in den Entwurf übernehmen, gemeinsam prüfen und speichern. Die Engine
verhindert parallele Läufe derselben Regel und besitzt einen Schutzabstand von
einer Sekunde zwischen Regelstarts. Bedingungen und Wartezeiten verändern diese
Regeln nicht. Gemischte Zeit-/Geräteauslöser und nicht darstellbare Vergleiche
bleiben in der erweiterten YAML bzw. der begrenzten Metadatenbearbeitung erhalten.

## Mehrere Aktionen per Klick

Die Liste „Aktionsfolge“ enthält bis zu 16 Schritte. Über „Hinzufügen“ Gerät und
Aktion auswählen; vorhandene Schritte über die Bearbeitung ändern oder löschen.
Die Reihenfolge durch Ziehen der Zeilen ändern. Sie wird von oben nach unten
ausgeführt. Geräte dürfen in mehreren Schritten vorkommen.

„Wartezeit vor diesem Schritt“ ist eine ganze Sekundenzahl von 0 bis 86400.
0 bedeutet sofort; bei späteren Schritten beginnt die Wartezeit nach der Übergabe
der vorherigen Aktion an Z2M. Beispiel: Licht ein mit 0 Sekunden, Licht aus mit
30 Sekunden. Eine Übergabe bestätigt noch keine tatsächliche Gerätewirkung.
Auch vor dem ersten Schritt darf eine Wartezeit stehen.

Danach „Automation zum Entwurf hinzufügen“ bzw. „Änderungen in den Entwurf
übernehmen“ betätigen und den Entwurf prüfen und speichern. Mindestens ein
gültiger Schritt ist erforderlich. Änderungen an der Liste alleine ändern weder
den Regelentwurf noch Z2M. Bei Auswahl einer vorhandenen Automation werden alle
unterstützten Schritte einschließlich ihrer Wartezeiten geladen. Einzelaktionen
erscheinen als eine Zeile. Die Wartezeit gehört zum Schritt und wandert beim
Umsortieren mit.

Alle Bedingungen werden vor jedem Schritt erneut geprüft. Ist eine Bedingung
nicht mehr erfüllt, endet die verbleibende Folge. „Automationen in Z2M stoppen“
verwirft ausstehende Wartezeiten; bereits übergebene Aktionen werden nicht rückgängig
gemacht. Die Ausführung der gespeicherten, aktivierten Folge benötigt kein Symcon.

## Zeitsteuerungen und Bedingungen per Klick

Zuerst die aktualisierte mitgelieferte Erweiterung installieren und die Geräte
erneut laden. Ihr Katalog liefert auch lesbare Messgrößen für Zahlenbedingungen.

- Unter „Auslöser“ zwischen „Geräteereignis“ und „Tägliche Uhrzeit“ wählen. Bei
  einer Uhrzeit den Zeitpunkt als HH:MM oder HH:MM:SS und die Zeitzone auswählen.
  Ohne Bedingungen läuft die Regel täglich. Verpasste Zeitpunkte werden nicht
  nachgeholt; bei der Zeitumstellung wird ein Zeitpunkt höchstens einmal am Tag
  ausgelöst. Geräteereignisse können eine Mindestdauer in Sekunden erhalten.
- „Nur in einem Zeitfenster“ erlaubt eine zusätzliche Begrenzung nach Uhrzeit,
  Zeitzone und Wochentagen. Der Beginn zählt mit, das Ende nicht. Ein Fenster
  Montag 22:00 bis 06:00 gilt bis Dienstagmorgen; maßgeblich ist der Starttag.
  Mindestens ein Tag muss gewählt sein. Gleiche Start-/Endzeiten sind ungültig.
- „Zusätzliche Gerätebedingung“ erlaubt einen Zustand oder Messwert. Gerät und
  Zustand bzw. Messgröße auswählen, dann Vergleich und gegebenenfalls Zahlenwert.
  Zustände unterstützen gleich/ungleich, Messwerte zusätzlich größer, kleiner,
  mindestens und höchstens. Die Einheit steht neben der Messgröße. Beispiel:
  täglich 07:15 Uhr, nur wenn die Temperatur kleiner als 18,5 °C ist.
- Bei Bedarf eine Wartezeit vor der Aktion angeben. Alle eingeschalteten
  Bedingungen müssen erfüllt sein; sie werden vor der Aktion erneut geprüft.

Zeitfenster und eine Gerätebedingung können gemeinsam verwendet werden. Eine
Gerätebedingung verwendet nur Werte, die Z2M seit der Aktivierung beobachtet hat.
Ein unbekannter Wert erfüllt auch „ungleich“ nicht. Es werden keine zusätzlichen
Geräteabfragen erzeugt.

Neue Regeln zum Entwurf hinzufügen, bestehende Regeln auswählen und Änderungen
in den Entwurf übernehmen. Zeiten, Wochentage, Bedingungen und Verzögerungen werden
beim Bearbeiten wieder vorbelegt. Danach wie gewohnt prüfen, speichern und bei
Bedarf aktivieren. Erstellen oder Bearbeiten führt noch keine Aktion aus.

## Vorhandene Automationen bearbeiten oder löschen

1. Geräte und gespeicherte Automationen laden. Unter „Automation bearbeiten“
   die gewünschte Regel auswählen. Die Nummer neben dem Namen unterscheidet
   gleichnamige Regeln; intern bleibt jede Regel über ihre feste ID zugeordnet.
2. Bei unterstützten Regeln werden Name, Aktivierung, Auslöser, Bedingungen und
   sämtliche Aktionsschritte vorbelegt. Änderungen mit „Änderungen in den Entwurf übernehmen“
   anwenden. „Regel aktiviert“ legt fest, ob die Regel bei der nächsten Aktivierung
   der gespeicherten Konfiguration mitläuft; es schaltet nicht sofort ein Gerät.
3. Alternativ „Ausgewählte Automation aus dem Entwurf löschen“ betätigen und
   bestätigen. Nur die ausgewählte Regel wird entfernt. Auch die letzte Regel
   darf gelöscht werden; es entsteht eine gültige leere Konfiguration.
4. Den geänderten Entwurf in Z2M prüfen und anschließend speichern. Erst diese
   Übertragung verändert Z2M. Eine geänderte Konfiguration ist danach gestoppt
   und muss bei Bedarf erneut ausdrücklich aktiviert werden.

Weitere lokale Änderungen können vor dem Übertragen gesammelt werden. Nach
einer erfolgreichen Übertragung den Editor neu laden, bevor weitere Regeln
bearbeitet werden. Einfache Regeln behalten beim Bearbeiten ihre ID und Position;
andere Regeln bleiben unverändert. Bei erweiterten Regeln oder inzwischen
fehlenden Auswahlwerten werden Geräte-/Aktionsfelder gesperrt. Name und
Aktivierungszustand bleiben änderbar; vorhandene Bedingungen und Schritte bleiben
vollständig erhalten. Löschen ist auch für diese Regeln möglich.

Zum erneuten Laden wird das Verwerfen des lokalen Entwurfs
ausdrücklich bestätigt. Manuelle YAML-Änderungen oder eine inzwischen geänderte
Speicherrevision werden nicht unbemerkt überschrieben. Eine veraltete Auswahl,
auch aus einem zweiten geöffneten Formular, wird abgewiesen. In diesem Fall
die Regel erneut auswählen bzw. den Editor nach Speicherung neu laden.

## Ausführung aktivieren und stoppen

Die Aktivierung bezieht sich auf die **gespeicherte** Revision, nicht auf einen
unveröffentlichten Entwurf. Das Modul fragt den aktuellen Zustand und die frische
Einmalkennung ab, sendet den Aktivierungsauftrag und prüft danach den tatsächlichen
Status erneut. Erst dann werden aktive Revision und Regelanzahl angezeigt.
Eine ältere Erweiterung wird mit einer verständlichen Update-Meldung erkannt.

„Automationen in Z2M stoppen“ verwirft ausstehende Auslöser und Verzögerungen.
Ein Stopp erhält Vorrang vor einem noch laufenden Modulauftrag. Bei aktiver
MQTT-Anbindung darf er auch bei noch unbekanntem Bridge-Onlinezustand versucht
werden. Ein Timeout bestätigt keinen Stopp: Dann bleibt der Ausführungsstatus
unbekannt und muss erneut abgefragt werden. Bereits gesendete Gerätebefehle lassen
sich nicht zurückrufen.

**Symcon ist nach erfolgreicher Aktivierung für die Ausführung nicht erforderlich.**
Geräteaktionen übergibt die Erweiterung direkt an Z2Ms interne Befehlsverarbeitung.
Die Ausführung benötigt keine Rückzustellung eigener MQTT-Veröffentlichungen.
Der Ausführungsstatus bestätigt die aktive Regel, nicht die physische Wirkung
jeder Aktion. Spätere Converter-/Zigbee-Fehler sind im Z2M-Protokoll nachzusehen.
Auch das Deaktivieren der MQTT-Anbindung oder eine Unterbrechung der Symcon-
Verbindung stoppt die Regeln in Z2M nicht automatisch. Erst der ausdrücklich an
Z2M gesendete Stopp beendet die Ausführung. Nach einem Z2M-/Erweiterungsneustart
oder dem Speichern einer geänderten Revision ist bewusst neu zu aktivieren.

## Erweiterte YAML-Ansicht

Den Bereich „Erweiterter YAML-Editor“ aufklappen, um die YAML direkt anzuzeigen
oder zu bearbeiten. Für die grafische Erstellung muss dieser Bereich nicht
geöffnet werden.

1. YAML in „YAML-Entwurf der Automationen“ einfügen. Ein
   [synthetisches Beispiel](../libs/z2m/examples/automations.yaml) und der
   [YAML-Vertrag](../docs/YAML_SCHEMA.md) beschreiben das Format.
2. Für eine neue Fassung die nächste Revision verwenden. Eine leere Installation
   beginnt mit Revision 1. Eine unveränderte Kopie der bereits gespeicherten
   Fassung kann nach erneuter Prüfung idempotent bestätigt werden.
3. „Entwurf in Z2M prüfen“ starten. Das Modul fragt zuerst den aktuellen Empfänger-
   und Speicherzustand ab und sendet dann die YAML zur dortigen Schema-Prüfung.
4. In der Vorschau Revision, Anzahl der Automationen, bisher gespeicherte
   Revision und SHA-256 kontrollieren.
5. „Geprüften Entwurf in Z2M speichern“ bestätigen. Das Modul prüft Sitzung und
   Revision erneut, bevor genau die geprüften Bytes übertragen werden.

Der zuletzt zur Prüfung oder Speicherung eingereichte Entwurf bleibt beim
erneuten Öffnen des Formulars erhalten. Reines Tippen ohne Bedienaktion wird
nicht gespeichert. Der Entwurf ist kein PHP-Skript und wird niemals ausgeführt.
Es wird auch keine bereits in Z2M gespeicherte YAML automatisch in den Editor geladen.

Textänderungen, Topic-/Gateway-Wechsel, ein Erweiterungsneustart oder eine
geänderte gespeicherte Revision machen die bisherige Prüffreigabe ungültig.
Bei Konflikten wird keine neue Revision automatisch erzeugt und nichts blind
überschrieben. Ein Empfängerspeicher in Fehler- oder Sicherungslesemodus blockiert
die Übertragung. Fehlermeldungen stehen im Formular und in „Letzter Erweiterungsfehler“.

Eine Zeitüberschreitung beweist nicht, dass Z2M nichts gespeichert hat. Vor erneutem
Übertragen den Erweiterungsstatus abfragen, Revision prüfen und den Entwurf bei
Bedarf erneut validieren. Das Modul wiederholt Installationen oder Speicheraufträge
nicht automatisch. „YAML gespeichert“ bestätigt Persistenz, keine Ausführung.

## Einstellungen

| Feld | Standard | Bedeutung |
| --- | --- | --- |
| MQTT-Anbindung aktivieren (Active) | Aus | Empfang und manuelle Anfragen erlauben |
| Zigbee2MQTT-Basistopic (BaseTopic) | zigbee2mqtt | Exaktes Topic der Installation, auch mehrstufig |
| Antwortzeitlimit (RequestTimeout) | 10 Sekunden | Wartezeit auf eine Antwort; 1 bis 120 Sekunden |

Basistopics dürfen keine MQTT-Platzhalter +/#, Steuerzeichen oder leeren
Pfadabschnitte enthalten. Führende und abschließende Schrägstriche sind ungültig.
Ein Wechsel des Topics oder MQTT-Gateways verwirft den bisherigen Statuscache
und eine noch offene Anfrage.

## Statusvariablen

Die Erweiterung kann ausdrücklich aktivierte Speicherrevisionen ausführen;
[Ausführungsregeln und Aktivierungsbefehle](../docs/RUNTIME.md). Speichern aktiviert nichts; eine
geänderte erfolgreich gespeicherte Revision stoppt die bisherige Ausführung.
`GetTransportState` übernimmt den gemeldeten Boolean `extension.executing`,
ohne damit eine konkrete Gerätewirkung zu bestätigen.

Alle Variablen sind ausschließlich lesbar und besitzen keine Schaltaktion.

| Ident | Typ | Bedeutung |
| --- | --- | --- |
| MQTTConnected | Boolean | Anbindung aktiviert, Konfiguration gültig und MQTT-Gateway aktiv |
| BridgeOnline | Boolean | Zusätzlich hat Z2M den Zustand online gemeldet |
| Z2MVersion | String | Gemeldete Z2M-Version; leer bis zur ersten gültigen Information |
| ExtensionInstalled | Boolean | z2m-automations.mjs in der empfangenen Erweiterungsliste gefunden |
| HealthStatus | String | Noch nicht geprüft, Antwort ausstehend, funktionsfähig, nicht funktionsfähig, Fehler, Zeitüberschreitung oder abgebrochen |
| ExtensionReady | Boolean | Empfängerprotokoll und Version durch eine frische passende Antwort bestätigt |
| StoredRevision | Integer | Zuletzt gemeldete Speicherrevision; 0 auch bei noch unbekanntem Zustand |
| OperationStatus | String | Fortschritt und Ergebnis der letzten Erweiterungsaktion |
| LastError | String | Feste verständliche Meldung zum letzten Erweiterungsfehler |
| ExecutionActive | Boolean | Ausführung durch einen frischen Antwortablauf bestätigt; false kann auch unbekannt bedeuten |
| ActiveRevision | Integer | Bestätigte aktive Revision; 0 bei gestopptem oder unbekanntem Zustand |
| EnabledAutomations | Integer | Bestätigte Anzahl aktivierter Regeln |
| ExecutionStatus | String | Maßgebliche Unterscheidung zwischen aktiv, gestoppt und unbekannt |
| RuntimeError | String | Feste Erklärung des zuletzt gemeldeten Engine-Fehlers |

Eine erkannte Erweiterungsdatei bestätigt noch nicht deren Betriebsbereitschaft.
Die beiden Online-Anzeigen unterscheiden die MQTT-Verbindung vom Zustand der
Z2M-Bridge. Nach Verbindungsabbruch ist eine neue Bridge-Statusmeldung nötig.
Eine Gesundheitsantwort setzt den Bridge-Onlinezustand nicht eigenständig.
StoredRevision allein bestätigt keinen eigenen Speicherauftrag: Auch eine
Statusmeldung kann diesen Wert aktualisieren. Maßgeblich ist das bestätigte
Ergebnis in OperationStatus.

## Instanzstatus

| Code | Bedeutung |
| --- | --- |
| 102 | MQTT verbunden und Z2M meldet online |
| 104 | Anbindung deaktiviert |
| 200 | Ungültiges Topic oder Antwortzeitlimit |
| 201 | Keine aktive kompatible MQTT-Verbindung |
| 202 | MQTT verbunden, Z2M offline oder noch kein Bridge-Status empfangen |

## Öffentliche Funktionen

- Z2MA_CheckHealth(InstanceID): Startet eine asynchrone Gesundheitsanfrage.
  Liefert die Transaktions-ID oder einen leeren String, wenn kein Versand möglich
  ist. Bei bereits offener Anfrage wird deren ID zurückgegeben, ohne erneut zu
  senden. Ein nicht leerer Rückgabewert bestätigt noch keine erfolgreiche Prüfung.
- Z2MA_GetTransportState(InstanceID): Liefert den Zustand als JSON-String mit
  mqttConnected, bridgeState (unknown/online/offline), bridgeOnline, version,
  externalJS (true/false/null), extensionInstalled, transaction und health.
  health verwendet idle/pending/healthy/unhealthy/error/timeout/cancelled.
  transaction bezeichnet die letzte Anfrage und ist anfangs leer.
  Zusätzlich: extension (Version, Bereitschaft und Speichermetadaten),
  extensionConfirmed, operation (kind, status, transaction), lastError als
  bereinigter Fehlercode und validation als Vorschau oder null.
  Interne Sitzungskennungen, YAML und JavaScript-Code werden nicht ausgegeben.
- Z2MA_InstallExtension(InstanceID): Installiert/aktualisiert die feste mitgelieferte
  Erweiterung und prüft anschließend die Empfängerbereitschaft.
- Z2MA_RequestExtensionStatus(InstanceID): Fragt den aktuellen Empfänger aktiv ab.
- Z2MA_ValidateYaml(InstanceID, Yaml): Prüft den UTF-8-String (maximal 128 KiB)
  asynchron in Z2M und erzeugt bei Erfolg die an diese Bytes gebundene Vorschau.
- Z2MA_StoreYaml(InstanceID, Yaml): Speichert ausschließlich einen erfolgreich
  geprüften identischen Entwurf nach erneuter Sitzungs-/Revisionsprüfung.

Die vier Erweiterungsaktionen liefern eine Aktions-ID oder einen leeren String,
wenn keine Anfrage gestartet werden konnte. Eine ID bestätigt den Beginn, noch
keinen Erfolg. Gleichartige Doppelklicks führen keinen zweiten Auftrag aus;
eine andere Aktion während eines laufenden Auftrags wird mit „beschäftigt“ abgewiesen.
- Z2MA_ExpireRequest(InstanceID): Timer-Einstieg zur Prüfung des Antwortzeitlimits;
  normalerweise kein manueller Aufruf erforderlich.

Es wird nur eine offene Gesundheitsanfrage pro Instanz geführt. Antworten ohne
passende Transaktions-ID und gespeicherte Retain-Antworten schließen sie nicht ab.
Pro Instanz ist zusätzlich zur Gesundheitsanfrage eine Erweiterungsaktion
gleichzeitig möglich. Jede Phase hat das konfigurierte Antwortzeitlimit.
Ein grafischer Regeleditor und die Ausführung der Automationen folgen später.
