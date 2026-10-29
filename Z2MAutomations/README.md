# Z2MAutomations

## Ein Gerät auf dem zweiten Z2M-System schalten

Jedes Z2M-System erhält sein eigenes Automationsmodul am zugehörigen MQTT-Client.
Unter **Aktionen zwischen zwei Z2M-Systemen** wählen Sie in beiden Modulen das
jeweils andere Modul. Die Basistopics müssen verschieden sein. Anschließend
die Gerätedaten in beiden Modulen laden und das Formular erneut öffnen.

Bei den Aktionen erscheinen die schaltbaren Geräte und Gruppen des zweiten
Systems mit dessen Basistopic vor dem Namen. Gerät, Kanal und Wert werden wie
bei lokalen Aktionen ausgewählt. Vorhandene Regeln lassen sich damit bearbeiten
und passende Symcon-Ereignisse als deaktivierte Entwürfe importieren. Ein
Geräteauslöser und seine Bedingungen gehören weiterhin zum eigenen System.

Beide Entwürfe prüfen, speichern und aktivieren. Die empfangende Seite muss
auch dann aktiviert werden, wenn sie keine eigene eingeschaltete Regel besitzt.
Die Statusvariable **Aktionen vom zweiten Z2M-System freigegeben** zeigt die
bestätigte Empfangsbereitschaft nach einer Statusabfrage. **Stoppen** auf der
empfangenden Seite sperrt auch Aktionen des anderen Systems. Bereits gesendete
Gerätebefehle lassen sich dadurch nicht zurückholen.

Voraussetzung ist eine eingerichtete Broker-Verbindung für `z2ma/mesh/#`.
Der Benutzer, mit dem sich **Z2M selbst** anmeldet, benötigt auf seinem Broker
zusätzlich Lese- und Schreibrecht für diesen Bereich. Der Bridge-Benutzer allein
genügt nicht. Das Modul ändert keine Brokerberechtigungen und benötigt keine
Brokerpasswörter. Details für die Einrichtung stehen in [MQTT](../docs/MQTT.md).

Die Regel läuft nach erfolgreicher Einrichtung ohne Symcon. Bei fehlender
Gegenstelle wird die Aktionsfolge abgebrochen und nicht automatisch nachgeholt.
Eine Bestätigung bedeutet, dass das Ziel-Z2M den Gerätebefehl übernommen hat;
die tatsächliche Gerätewirkung ist damit noch nicht bestätigt.

## Entwurf, Speichern und Starten

Ein **Entwurf** sind die Automationen, die Sie gerade in Symcon bearbeiten.
**Prüfen** kontrolliert, ob Z2M diesen Entwurf verwenden kann. **Speichern** sendet
ihn an Z2M und ersetzt dort die bisher gespeicherten Automationen. Bei geändertem
Inhalt wird die bisherige Ausführung gestoppt. Erst **Aktivieren** startet alle
in diesem gespeicherten Stand eingeschalteten Regeln. **Stoppen** beendet die
Ausführung, ohne die gespeicherten Regeln zu löschen.

Die Meldungen zeigen, ob ein Vorgang erfolgreich, fehlgeschlagen oder noch offen
ist. Wenn eine Aktion erforderlich ist, nennt die Meldung den nächsten Schritt.
„Noch nicht geprüft“ oder „Noch nicht bestätigt“ bedeutet nicht automatisch,
dass die Verbindung gestört ist. Mit **Erweiterungsstatus abfragen** lässt sich
der aktuelle Stand aus Z2M laden.

## MQTT-Gateway und Suchbereich

Für zwei MQTT-Server werden zwei getrennte Automationsinstanzen verwendet.
Jede Instanz nutzt ausschließlich ihre eigene übergeordnete MQTT-Verbindung
und das unter **Zigbee2MQTT-Basistopic** konfigurierte Topic (Groß-/Kleinschreibung
beachten). Gleichlautende Topics, IEEE-Kennungen oder Gruppen-IDs an anderen
Gateways werden nicht vermischt.

Unter **An diesem MQTT-Gateway gefundene Basistopics** lassen sich Topics aus
vorhandenen Z2M-Geräte-, Gruppen- und Bridge-Instanzen auswählen. Die Auswahl
befüllt nur das Basistopic-Feld; eine manuelle Eingabe bleibt möglich. Danach
**Änderungen übernehmen** und für den Import den Z2M-Katalog neu laden.
Die Auswahl ändert weder das Gateway noch Konfigurationen anderer Instanzen.
Die Vorschläge werden beim Öffnen des Formulars aus der lokalen Installation
ermittelt; es wird kein MQTT-Netzwerkscan durchgeführt.

**Vorhandene Ereignisse prüfen** findet passende Symcon-Ereignisse anhand ihrer
Quellvariablen unabhängig vom Ablageort im Objektbaum. Auch ohne geladenen
Z2M-Katalog werden diese Ereignisse angezeigt. Ob sie importierbar sind, wird
anschließend gegen den Katalog desselben Gateways und Topics geprüft. Nach einem
Gateway- oder Topic-Wechsel werden bisherige Suchergebnisse und Freigaben nicht
weiter angeboten. Es sind keine konkreten Instanz-IDs im Modul hinterlegt.


## Vorhandene Symcon-Ereignisse importieren

Die Prüfung unterscheidet zwischen dem Gerät und der einzelnen Variable:
Ein Gerät kann richtig verbunden sein, obwohl eine seiner Variablen noch nicht
importiert werden kann. In diesem Fall nennt die Meldung die betroffene Variable.
Die **auslösende Variable** wird überwacht; die **zu schaltende Variable** ist das
Ziel der Aktion. Das Ereignis kann im Objektbaum beim Ziel liegen und trotzdem
eine Variable eines anderen Geräts überwachen.

Ein leerer Ereignisname verhindert den Import nicht. Das Modul vergibt dann
„Importierte Automation“ mit der Ereignisnummer als Namen. Dieser lässt sich
anschließend im Entwurf ändern; das ursprüngliche Ereignis bleibt unverändert.

Die Übersicht zeigt Ereignisname, **auslösendes Gerät** und dessen
**Symcon-Instanz-ID**. Die Liste bietet Platz für 15 sichtbare Einträge;
weitere Einträge sind durch Scrollen erreichbar. Auch in der Ereignisauswahl stehen diese Angaben, damit
sich gleichnamige Ereignisse unterscheiden lassen.

Die Importprüfung nennt das Ergebnis ausdrücklich: **Importierbar** oder
**Nicht importierbar**, jeweils mit einer Erklärung.

Fehlt die Gerätezuordnung aus Z2M für die gewählte Verbindung, steht in der
Übersicht **Noch nicht prüfbar: Gerätedaten fehlen**. Die Meldung darunter verweist direkt
auf **Gerätedaten für den Import laden**. Dieser Button lädt Geräte, Gruppen und
gespeicherte Regeln über die Erweiterung. Nach der Antwort wird die Übersicht
aktualisiert und das gewählte Ereignis erneut geprüft. Dafür ist kein erneutes
Öffnen des Formulars notwendig. Ein unterstütztes Ereignis lässt sich danach
übernehmen; andernfalls erscheint der konkrete Ablehnungsgrund.

Beim Nachladen über **Gerätedaten für den Import laden** bleiben vorhandene
Entwürfe in Symcon erhalten. Erst wenn es noch keinen Entwurf gibt, werden die
in Z2M gespeicherten Regeln als Ausgangspunkt geladen. Der separate Button
**Geräte und gespeicherte Automationen laden** ersetzt weiterhin den Entwurf
nach Bestätigung. Bei zwischenzeitlichen Änderungen in Z2M oder unübernommenen
Änderungen im YAML-Editor wird das Nachladen abgebrochen und der Entwurf erhalten.
Während der Antwort aus Z2M bleiben Laden und Übernahme gesperrt. Ladefehler
werden verständlich angezeigt und bleiben beim erneuten Öffnen sichtbar.


1. Modul und mitgelieferte Z2M-Erweiterung auf denselben aktuellen Stand bringen.
2. **Geräte und gespeicherte Automationen laden**. Das ersetzt den lokalen Entwurf;
   vorherige Änderungen deshalb zuerst übernehmen bzw. speichern.
3. **Symcon-Ereignisse importieren** öffnen und **Vorhandene Ereignisse prüfen** wählen.
4. Ein Ereignis auswählen und die Vorschau mit Auslöser, Ziel und Aktion prüfen.
5. **Ereignis in den Entwurf übernehmen** wählen. Die deaktivierte Regel öffnet
   sich vorbefüllt im Bereich der geräteausgelösten Automationen und lässt sich
   dort ohne YAML bearbeiten. Anschließend gemeinsam prüfen und speichern.

Der Import unterstützt ausgelöste Ereignisse **bei bestimmtem Wert** mit oder
ohne **mehrmalige Auslösung bei wiederholt erfüllter Bedingung**, ohne zusätzliche
Bedingungen oder Ausführungsbegrenzung. Unterstützt sind native feste boolesche
bzw. unmittelbar zuordenbare Text-/Auswahlwerte sowie **Wert umschalten** für
Ein-/Aus-Zustände. Zahlenwerte mit möglicher Symcon-Skalierung, Prozent-/Farbaktionen,
PHP-Code, Ablaufpläne und weitere Aktionsarten werden mit Begründung abgewiesen.
Es wird keine teilweise vereinfachte Regel erzeugt.

Aktionen direkt unter einer Variablen verwenden diese als Ziel. Auch die native
Instanzaktion **Statusvariable schalten** wird unterstützt: Die Statusvariable
wird über ihren Ident ausgewählt und ihre enthaltene feste Wert-/Umschaltaktion
übernommen. Das Ereignis muss kein ausdrücklich gespeichertes TARGET enthalten.

Ist die wiederholte Ausführung in Symcon ausgeschaltet, erhält der Auslöser im
Klick-Editor die Einstellung **Nur bei Wertänderung**. Eine gleiche passende
Meldung startet dann keinen zweiten Lauf. Die Einstellung lässt sich im
Auslöserdialog ändern. Beim Aktivieren verwendet Z2M seinen vorhandenen
Quellzustand als Ausgangspunkt. Für nicht gespeicherte Ereigniswerte (etwa
Tastendrücke) zählt die erste frische Meldung als Änderung; danach wird der
letzte beobachtete Wert verglichen. Ein-/Ausführung und Sperrzeit gelten weiter.

Die Zuordnung verwendet die referenzierten Variablen-IDs, den Instanztyp, die
Gateway-Verbindung, das konfigurierte Basistopic und die Z2M-Geräte-/Gruppenkennung. Namen sind nur Anzeige.
Aktionsziele benötigen eine native schaltbare Variable ohne eigene Aktionsskripte
und eine passende schreibbare Katalogeigenschaft. Sensoren bleiben lesbare Quellen.
Gruppen sind als Aktionsziele für ihren Ein-/Aus-Zustand verfügbar.

**Wert umschalten** verwendet unmittelbar vor der Aktion den in Z2M bekannten
Zustand der Gruppe bzw. des Gerätekanals und sendet den entgegengesetzten
Ein-/Aus-Wert. Bei unbekanntem Zustand wird der Schritt mit Fehler abgebrochen.
Es wird kein möglicherweise veralteter Symcon-Wert übernommen und kein pauschaler
TOGGLE-Befehl an alle Gruppenmitglieder gesendet.

Das ursprüngliche Symcon-Ereignis wird weder geändert noch deaktiviert.
**Vor Aktivierung der importierten Regel in Z2M das Originalereignis deaktivieren**,
damit beide Systeme die Aktion nicht doppelt ausführen. Die allgemeinen
Laufzeitgrenzen gelten weiter, insbesondere eine Sekunde Sperrzeit pro Regel und
keine parallelen Läufe derselben Regel. Das ist keine Garantie identischer
Ausführungshäufigkeit wie in Symcon.

Die Vorschau wird vor der Übernahme erneut geprüft. Geänderte Ereignisse,
Zuordnungen oder ungespeicherte YAML-Änderungen werden nicht stillschweigend
übernommen. Ein bereits importiertes Ereignis erzeugt keine zweite Regel.
Die Suche berücksichtigt höchstens 4096 Ereignisse und zeigt maximal 256 passende
Quellen aus dem geladenen Katalog; eine erreichte Grenze wird angezeigt.


## Automatische Statusprüfung

Nach Anwenden der Einstellungen bzw. einem Modulupdate und beim Öffnen des
Formulars prüft das aktive Modul einmalig die Erreichbarkeit von Zigbee2MQTT.
Eine passende positive Health-Antwort startet anschließend die Abfrage des
Erweiterungsstatus. Die Statusanzeige aktualisiert sich anhand der Antworten.

Die Prüfung startet verzögert nach etwa einer Sekunde und benötigt eine
verbundene MQTT-Schnittstelle. Aufrufe innerhalb von zehn Sekunden werden
zusammengefasst. Laufende Aufträge haben Vorrang; Fehler oder Zeitüberschreitungen
führen nicht zu einer automatischen Wiederholung. Entwürfe werden nicht verändert;
Installation, Übertragung und Aktivierung bleiben ausdrückliche Bedienaktionen.
Die manuellen Prüfbuttons stehen weiterhin zur Verfügung.

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

Z2M muss online melden. Eine durch bridge/info bestätigte Deaktivierung von externem JavaScript blockiert die Installation; fehlende Metadaten werden durch den offiziellen Installationsablauf geklärt.
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
- Z2MA_InstallExtension(InstanceID, Notify = false): Installiert/aktualisiert die feste mitgelieferte
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

Der Installationsbutton zeigt nach Abschluss ein natives Popup mit Erfolg oder
bereinigter Fehlerbeschreibung. Erfolg setzt sowohl die Upload-Bestätigung als
auch die anschließende Empfängerprüfung voraus. Zeitüberschreitungen und bereits
vor dem Versand erkannte Fehler erscheinen ebenfalls im Popup. Programmatische
InstallExtension-Aufrufe bleiben standardmäßig ohne Popup; Notify=true aktiviert
es für ein geöffnetes Formular.

Fehlen nach einem Modulupdate noch bridge/info-Metadaten, darf die offizielle
Z2M-Schnittstelle den Installationsauftrag prüfen. Eine ausdrücklich gemeldete
Deaktivierung blockiert weiterhin vor dem Versand. Die Library aktiviert externes
JavaScript nicht selbst; Erfolg benötigt immer die bestätigte Empfängerbereitschaft.

## Messwertauslöser und mehrere Bedingungen

Beim Hinzufügen oder Bearbeiten eines Geräteauslösers stehen Gerät,
Eigenschaft/Kanal, Vergleich, Wert und Mindestdauer getrennt bereit.
Für Messwerte sind „Ist gleich“, „Ist ungleich“, „Ist größer als“,
„Ist mindestens“, „Ist kleiner als“ und „Ist höchstens“ möglich. Für Zustände
und Ereignisse stehen Gleichheit und Ungleichheit zur Verfügung.
Beispiel: Helligkeit ist seit 30 Sekunden kleiner als 50 Lux.
Mehrere Auslöser sind weiterhin ODER-verknüpft.

Unter „Nur wenn …“ können in beiden Automationsbereichen mehrere Geräte- und
Messwertbedingungen hinzugefügt, bearbeitet und gelöscht werden. Alle Zeilen
und das optionale Zeitfenster müssen gemeinsam erfüllt sein (UND). Insgesamt
sind höchstens 16 Bedingungen einschließlich Zeitfenster möglich. Eine leere
Gerätebedingungsliste fügt keine Einschränkung hinzu. Das Zeitfenster kann
unabhängig davon eingeschaltet bleiben.

Vorhandene Regeln laden ihre Vergleiche und sämtliche unterstützten Bedingungen
wieder in die Zeilen. Änderungen werden erst in den lokalen Entwurf übernommen,
anschließend geprüft und nach Bestätigung gespeichert. Nicht darstellbare
Regelstrukturen bleiben gegen ein versehentliches Ersetzen geschützt.
Unbekannte Gerätewerte erfüllen eine Bedingung nicht; Z2M verwendet die seit der
Aktivierung beobachteten Werte. Gerätewerte müssen weiterhin lesbar sein;
Aktionsziele benötigen unverändert eine schaltbare Symcon-Variable und Z2M-Schreibrecht.
Ein numerischer Vergleichswert darf zwischen Meldeschritten oder außerhalb des
Sensorbereichs liegen. Das erweitert nicht die erlaubten Werte einer Aktion.
