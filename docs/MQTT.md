# MQTT-Vertrag und Nachweise

## Verbindung zwischen zwei Brokern

Die eigene Erweiterung ab diesem Stand transportiert entfernte Aktionen über
`z2ma/mesh/#`; siehe [ADR 0009](adr/0009-cross-system-actions-over-mqtt-bridge.md).
Bei getrennten Brokern muss die Broker-Bridge diesen Bereich in beide Richtungen
übertragen. Bei einem gemeinsamen Broker entfällt die Bridge. Die beiden
Automationsmodule dürfen auch denselben Symcon-MQTT-Client nutzen; dieser muss
die Verwaltungs-Topics beider Basistopics abonniert haben. Die
bestehenden gerätebezogenen Basistopics werden nicht miteinander gespiegelt.
Jeder **Z2M-Benutzer** benötigt zusätzlich zu seinem bisherigen Bereich:

```text
topic readwrite z2ma/mesh/#
```

Diese Zeile gehört in den vorhandenen Abschnitt `user z2m` des jeweiligen
Brokers. Die Berechtigungen des Bridge-Benutzers sind davon unabhängig.
Symcons MQTT-Clients benötigen für die Regelverwaltung keine Mesh-Berechtigung;
sie kommunizieren weiterhin nur über ihre eigenen Basistopics.
Keine Passwörter, echten Adressen oder lokalen Konfigurationen in der Library.
Berechtigungen und Praxisabnahme werden auf ausdrücklichen Auftrag separat
durchgeführt; der neue Code wurde noch nicht auf den Z2M-Systemen installiert.

Diese Datei beschreibt den Symcon-Transport einschließlich der Installation und
des [Empfängervertrags der Z2M-Erweiterung](EXTENSION_PROTOCOL.md).

## Symcon-Verbindung

Native Eltern: MQTT Client {F7A0DD2E-7684-95C0-64C2-D2A9DC47577B} und
MQTT Server {C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}.
TX-DataID: {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}.
RX-DataID: {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}.

PUBLISH verwendet PacketType 3, QualityOfService 0, Retain false, Topic und
Payload. Wegen IPSModuleStrict ist Payload HEX-kodiert, während Topic ein
JSON-String bleibt. Kein heuristischer Fallback auf Klartext oder UTF-8-Payloads.

## Empfang

Nur folgende exakte Topics unter BaseTopic werden verarbeitet:

| Suffix | Auswertung |
| --- | --- |
| bridge/state | JSON-Objekt mit state online/offline oder entsprechender alter Klartext-Payload |
| bridge/info | version und config.advanced.enable_external_js |
| bridge/extensions | Nur Erkennung des eigenen Namens z2m-automations.mjs |
| bridge/response/health_check | status, transaction und data.healthy |
| bridge/response/extension/save | Korrelierte Bestätigung des festen Erweiterungsuploads |
| symcon/automations/status | Beobachteter Empfängerzustand; keine Schreibfreigabe |
| symcon/automations/response/status | Frische Empfänger-/Speicherbestätigung |
| symcon/automations/response/validate | Schema-Prüfung mit exakter Entwurfs-Prüfsumme |
| symcon/automations/response/store | Bestätigung von Revision, Prüfsumme und Anzahl |

Der Empfangsfilter unterstützt JSON mit oder ohne maskierte Schrägstriche und
Unicode. Der Handler prüft Topic und DataID unabhängig vom Filter erneut.
Payload maximal 1 MiB, äußeres Paket maximal 2 MiB plus 8 KiB, JSON-Tiefe 32.
Fehlerhafte Daten werden verworfen; keine Rohpayloads in Debug oder Cache.
Vollständige Bridge-Konfiguration und Erweiterungsquellcode werden nicht gespeichert.

## Gesundheitsanfrage

Topic: BaseTopic/bridge/request/health_check.
Payload: JSON-Objekt mit zufälliger transaction als String. Z2M spiegelt diese ID
laut offizieller Request-/Response-API in der Antwort.
Keine Retain-Veröffentlichung, keine Wiederholungen, keine blockierende Warteschleife.
Ein zweiter Aufruf während einer offenen Anfrage liefert dieselbe ID.

Nur eine passende, frische Antwort beendet die Anfrage. health ist healthy,
unhealthy oder error; andernfalls bleibt pending bis timeout. Der Timer prüft die
Deadline; ReceiveData prüft sie zusätzlich vor der Antwortauswertung.
Ein MQTT-/Topic-Wechsel, Deaktivieren oder Offline-Meldung beendet eine offene
Anfrage als cancelled. Beim Kernelstart wird der volatile Cache verworfen.

## Erweiterungsworkflow

Pro Instanz läuft zusätzlich höchstens ein Workflow für install/status/validate/store.
Jede Phase hat eine eigene Wire-Transaktion und Deadline; die öffentliche
Aktions-ID bleibt über alle Phasen gleich. Der vorhandene Timer wählt die nächste
Deadline beider Kanäle. Folgerequests werden erst nach Freigabe der Semaphore
versendet. Eine sofortige Antwort aus dem Parent kann den Zustand nicht überholen.

Install sendet ausschließlich das lokale feste Einzeldatei-Artefakt und startet
nach der offiziellen Bestätigung eine status-Anfrage. Bei bekannter vorheriger
Sitzung muss der neu installierte Empfänger eine andere Sitzung melden.
validate fragt ebenfalls zuerst status ab und prüft dann die exakten YAML-Bytes.
store benötigt die so erzeugte Prüffreigabe und einen erneut passenden status.
Der Empfänger selbst setzt expected_revision weiterhin als letzte Konfliktsperre durch.

Das lokale Formular speichert nur den zuletzt eingereichten begrenzten Entwurf
als String-Attribut. Der volatile Workflow enthält während der Übertragung den
Entwurf; GetTransportState gibt ihn und interne Sitzungskennungen nicht aus.
Native Uploadfehler und Erweiterungsfehler werden auf feste lokalisierte Codes
abgebildet. Ein Timeout ist ein unbekanntes Ergebnis, keine Zusicherung, dass
kein Schreibzugriff stattgefunden hat. Keine automatischen Wiederholungen.

## Quellenprüfung am 27.10.2026

- [Symcon MQTT](https://www.symcon.de/de/llms/modules/mqtt.md)
- [GetCompatibleParents](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module/getcompatibleparents.md)
- [GetConfigurationForParent](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module/getconfigurationforparent.md)
- [IPSModuleStrict und HEX](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module.md)
- [Symcon-MQTT-Datenfluss, Herstellerbeitrag](https://community.symcon.de/t/ip-symcon-goes-mqtt/49180)
- [Z2M MQTT-API](https://www.zigbee2mqtt.io/guide/usage/mqtt_topics_and_messages.html)

Lokaler Vertragsvergleich: ../Zigbee2MQTT/Bridge/module.json und
../Zigbee2MQTT/libs/MQTTHelper.php. Keine Implementierungsübernahme.

## Testgrenzen

tests/mqtt.php nutzt die echten offiziellen Symcon-Stubs, den produktiven
Modulcode und synthetische MQTT-Eltern. Nur die Uhr wird für reproduzierbare
Zeitlimits ersetzt. Stubs verteilen keine Kernel-Nachrichten automatisch;
IM_CHANGESTATUS und FM_CONNECT werden im Test ausdrücklich zugestellt.

Die Offline-Tests bestätigen Kodierung, Routing, Korrelation, Zeitlimits,
Fehlerfälle und Zustandswechsel. Native MQTT-Gateways, Broker-Subscriptions,
Konsolenformular und eine echte Z2M-Installation benötigen noch Praxisnachweise.

tests/module-extension.php betreibt zusätzlich das produktive PHP-Modul gegen
die echte JavaScript-Erweiterung in einem Node-Teilprozess. tests/extension-peer.mjs
ersetzt nur den nativen MQTT-/extension-save-Rahmen, installiert ausschließlich
das erwartete eigene Artefakt in einem temporären synthetischen Datenordner und
nutzt dessen echte Schema- und Speicherlogik. Ein solcher Test ersetzt weder den
nativen Broker noch Z2M selbst.
