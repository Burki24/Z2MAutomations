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

## YAML prüfen und speichern

1. YAML in „YAML-Entwurf der Automationen“ einfügen. Ein
   [synthetisches Beispiel](../libs/z2m/examples/automations.yaml) und der
   [YAML-Vertrag](../docs/YAML_SCHEMA.md) beschreiben das Format.
2. Für eine neue Fassung die nächste Revision verwenden. Eine leere Installation
   beginnt mit Revision 1. Eine unveränderte Kopie der bereits gespeicherten
   Fassung kann nach erneuter Prüfung idempotent bestätigt werden.
3. „YAML in Z2M prüfen“ starten. Das Modul fragt zuerst den aktuellen Empfänger-
   und Speicherzustand ab und sendet dann die YAML zur dortigen Schema-Prüfung.
4. In der Vorschau Revision, Anzahl der Automationen, bisher gespeicherte
   Revision und SHA-256 kontrollieren.
5. „Geprüfte YAML in Z2M speichern“ bestätigen. Das Modul prüft Sitzung und
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
[Ausführungsregeln und Aktivierungsbefehle](../docs/RUNTIME.md). Dafür gibt es im
Modul noch keine Aktivierungs-/Stoppbuttons. Speichern aktiviert nichts; eine
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
