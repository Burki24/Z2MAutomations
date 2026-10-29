# ADR 0009: Aktionen auf einem zweiten Z2M-System

Status: lokal implementiert, Praxisabnahme offen.

## Entscheidung

Eine Regel gehört zum Z2M-System ihrer Auslöser. Geräteauslöser und Bedingungen
bleiben lokal; Aktionen dürfen ein ausdrücklich ausgewähltes zweites System
ansprechen. Für die Gegenrichtung wird eine Regel im anderen Automationsmodul
angelegt. Beide Systeme bleiben eigenständige Z2M-Installationen mit
unterschiedlichen Basistopics. Sie dürfen einen Broker und denselben nativen
Symcon-MQTT-Client gemeinsam nutzen. Gateway und Basistopic bilden zusammen
die Zuordnung; weder gleiche Gruppen-IDs noch IEEE-Adressen heben die Topicgrenze auf.

Die Module wählen einander über `PeerModuleID`. Der zweite Katalog kommt aus
dem lesenden `GetActionCatalog` des ausgewählten Moduls, ohne rekursive Exporte.
Er enthält nur lokale, nach nativen Symcon-Schaltrechten gefilterte Ziele.
Beim Verwenden werden Bindung, Gateway, Basistopic und Schaltrecht erneut geprüft.
Quellen bleiben an das eigene Gateway und Basistopic gebunden und werden nicht
mit Quellen anderer Z2M-Systeme zusammengeführt.
Gruppen und IEEE-Adressen erhalten im Editor einen systemgebundenen Schlüssel.

Der additive Schema-1-Vertrag verwendet `mesh_peers` als Empfangsfreigabe und
`target.system` für entfernte Aktionen. Systemidentität ist das eindeutige
Basistopic, nicht die Symcon-ID, IP-Adresse oder ein Gerätename. Der Klick-Editor
übernimmt die Partnerfreigabe in den lokalen Entwurf. Einstellungen allein
schreiben nichts nach Z2M. Beide Dokumente müssen gespeichert und ausdrücklich
aktiviert werden; eine empfangende Engine darf null eigene Regeln enthalten.
Stopp sperrt auch eingehende Aktionen. Neustarts starten keine Engine automatisch.

## Transport

Bei getrennten Brokern transportiert eine außerhalb der Library konfigurierte
Mosquitto-Bridge nur `z2ma/mesh/#`. Auf einem gemeinsamen Broker ist keine Bridge
erforderlich. Die Erweiterung nutzt Z2Ms bestehendes MQTT-Objekt mit absolutem
Topic-Präfix (`baseTopic: 'z2ma'`), explizitem Abonnement und `skipReceive: false`.
Z2M ergänzt immer einen Slash zwischen Präfix und Topic; ein leeres Präfix
würde deshalb fälschlich `/z2ma/mesh/...` statt `z2ma/mesh/...` erzeugen.
Es wird kein zusätzlicher MQTT-Client oder Broker im Modul implementiert.
Bridge-Benutzer und jeder Z2M-Benutzer benötigen entsprechende Brokerrechte.

Der Empfängerkanal ist `z2ma/mesh/<SHA256-des-Basistopics>/<Phase>`.
Alle Nachrichten sind JSON mit `mesh_version: 1`, Quellsystem und zufälliger
Transaktionskennung. `prepare` bindet einen SHA-256-Fingerabdruck der Aktion
an eine frische vier Sekunden gültige Einmalkennung. `challenge` beantwortet die
Anfrage; `execute` trägt Kennung und vollständige Aktion; `result` bestätigt
die native Befehlsübergabe oder deren Ablehnung. Vier Sekunden Antwortzeit gelten
pro Austausch. Die Kennung wird vor jeder Ausführung verbraucht, ist an Quelle,
Aktionsinhalt und Aktivierungsgeneration gebunden und liegt nur im Speicher.

Eine Kennung ist keine Authentifizierung. Die Brokerrechte bilden die
Vertrauensgrenze; Clients mit Schreibrecht im Mesh-Bereich sind vertrauenswürdig
zu behandeln. Nachrichten tragen keine frei wählbaren Zieltopics und können
keinen JavaScript- oder PHP-Code ausführen. Der Empfänger validiert das eigene
Aktionsschema und löst das lokale Ziel unmittelbar vor der nativen Übergabe auf.
Umschalten liest den aktuellen Zustand im Ziel-Z2M, auch bei Gruppen.

## Fehlergrenzen

Kein Retain, QoS 0, keine eigene Warteschlange oder automatische Wiederholung.
Ein nicht verbundener Z2M-MQTT-Client sendet keine neue Mesh-Anfrage. Noch verspätet
zugestellte Execute-Nachrichten benötigen eine unverbrauchte, nicht abgelaufene
Empfängerkennung. Stopp, geänderte Speicherung und Neustart verwerfen Freigaben.
Explizites Retain wird abgewiesen; weil Z2Ms Eventbus Retain nicht allgemein
liefert, ist die Einmalkennung der zusätzliche Schutz gegen alte Befehle.

Bei verlorener Bestätigung kann ein Gerät bereits geschaltet haben. Es wird
keine genau-einmalige Ausführung bei beliebigen Transportfehlern und keine
physische Gerätewirkung behauptet. Bereits übergebene Befehle können durch
einen Stopp nicht zurückgerufen werden. Der nächste Aktionsschritt wartet auf
die bestätigte Übergabe im Zielsystem. Ablehnung oder Timeout bricht die Folge ab.
Lokale und empfangene Aktionen teilen das lokale Mengenlimit; maximal 16
ausgehende Anfragen und 64 kurzlebige Eingangsfreigaben bleiben im Speicher.

## Nachweise und offene Praxis

Synthetische Zwei-System-Tests verwenden das erzeugte Erweiterungsartefakt,
getrennte Speicherverzeichnisse und native Befehlsübergaben. Sie prüfen
Gruppenumschaltung, doppelte/alte/veränderte Nachrichten, Stopp, Ablauf,
fehlende Ziele und Verbindungsverlust. PHP-/JS-Integration prüft Auswahl,
Vorbelegung, Import, deaktivierte Speicherung und entzogene Schaltrechte.
Eine reale MQTT-/Broker-/Zigbee-Abnahme dieses neuen Codes steht aus.

Schnittstellenquellen, keine Implementierungsvorlagen:
[Z2M MQTT](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/mqtt.ts),
[Z2M Eventbus](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/eventBus.ts),
[Mosquitto Bridge](https://mosquitto.org/man/mosquitto-conf-5.html).
