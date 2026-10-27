# Z2MAutomations

Dieses Modul verbindet die geplante Automationsverwaltung mit Zigbee2MQTT über
die vorhandene MQTT-Anbindung von Symcon.

## Voraussetzungen und Einrichtung

IP-Symcon ab 9.0 mit PHP 8.5 sowie eine konfigurierte MQTT-Client- oder
MQTT-Server-Instanz, die mit derselben Z2M-Installation verbunden ist.

1. Instanz anlegen und als übergeordnete Verbindung den vorhandenen MQTT Client
   oder MQTT Server auswählen. Gemeinsame Nutzung mit anderen Modulen ist möglich.
2. Das Basistopic der Z2M-Installation eintragen.
3. Bei MQTT Client prüfen, dass das Basistopic mit /bridge/# oder ein umfassenderes
   Abonnement wie # empfangen wird. Die Gateway-Einstellungen werden nicht geändert.
4. „MQTT-Anbindung aktivieren“ einschalten und Änderungen übernehmen.
5. Bei Bedarf „Zigbee2MQTT-Verbindung prüfen“ betätigen. Das Ergebnis erscheint
   in der Statusvariable „Verbindungsprüfung“.

Broker, Zugangsdaten und TLS werden ausschließlich im vorhandenen Gateway und
seiner Socket-Verbindung eingerichtet. Die Library benötigt keine eigene Kopie.

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

Alle Variablen sind ausschließlich lesbar und besitzen keine Schaltaktion.

| Ident | Typ | Bedeutung |
| --- | --- | --- |
| MQTTConnected | Boolean | Anbindung aktiviert, Konfiguration gültig und MQTT-Gateway aktiv |
| BridgeOnline | Boolean | Zusätzlich hat Z2M den Zustand online gemeldet |
| Z2MVersion | String | Gemeldete Z2M-Version; leer bis zur ersten gültigen Information |
| ExtensionInstalled | Boolean | z2m-automations.mjs in der empfangenen Erweiterungsliste gefunden |
| HealthStatus | String | Noch nicht geprüft, Antwort ausstehend, funktionsfähig, nicht funktionsfähig, Fehler, Zeitüberschreitung oder abgebrochen |

Eine erkannte Erweiterungsdatei bestätigt noch nicht deren Betriebsbereitschaft.
Die beiden Online-Anzeigen unterscheiden die MQTT-Verbindung vom Zustand der
Z2M-Bridge. Nach Verbindungsabbruch ist eine neue Bridge-Statusmeldung nötig.
Eine Gesundheitsantwort setzt den Bridge-Onlinezustand nicht eigenständig.

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
- Z2MA_ExpireRequest(InstanceID): Timer-Einstieg zur Prüfung des Antwortzeitlimits;
  normalerweise kein manueller Aufruf erforderlich.

Es wird nur eine offene Gesundheitsanfrage pro Instanz geführt. Antworten ohne
passende Transaktions-ID und gespeicherte Retain-Antworten schließen sie nicht ab.
Regelerstellung, YAML-Übertragung und Erweiterungsinstallation folgen später.
