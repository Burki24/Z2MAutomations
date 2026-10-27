# MQTT-Vertrag und Nachweise

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
