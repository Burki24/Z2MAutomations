# ADR 0008: Native Symcon-Ereignisse kontrolliert importieren

## Entscheidung

Der Import übersetzt ausschließlich bekannte strukturierte Ereignis- und
Aktionsverträge in deaktivierte lokale Regeln. Er analysiert und startet keinen
PHP-Code. Originalereignisse bleiben unangetastet. Quellen und Ziele werden über
Variablen-IDs, native Instanztypen, eigene MQTT-ConnectionID, Basistopic und bestätigte Z2M-Kennungen zugeordnet.
Eigene Aktionsskripte, Bedingungen, Begrenzungen und ungesicherte Skalierungen
werden im ersten Umfang vollständig abgewiesen. Vorschau und erneute Prüfung
schützen vor geänderten Ereignissen oder Zuordnungen; die bestehende
Entwurfsprüfung verhindert das Überschreiben abweichender YAML.

Gruppen werden als bestätigte Editorziele ergänzt. Für Ein-/Aus-Umschalten enthält
Schema 1 eine eigene `toggle`-Variante mit `property` statt `values`. Die Engine
liest unmittelbar vor der Aktion `state.get(entity)` des Hosts und sendet den
entgegengesetzten Ein-/Aus-Wert über die vorhandene native Befehlsverarbeitung.
Fehlende Host-Fähigkeit verhindert Aktivierung, fehlender Zielzustand die Aktion.

## Begründung und Grenzen

Ein pauschaler Gruppen-TOGGLE-Befehl kann bei gemischten Mitgliederzuständen
andere Ergebnisse liefern als die Umkehr eines aggregierten Gruppenstatus.
Die ausdrückliche Variante erhält diese Unterscheidung und benötigt kein Symcon
zur Laufzeit. Sie verwendet den in Z2M bekannten Zustand, dessen Aktualität nicht
mit einer physisch bestätigten Geräteabfrage gleichgesetzt wird.

Vor Aktivierung muss der Anwender das Originalereignis deaktivieren, um
Doppelausführung zu vermeiden. Die allgemeinen Engine-Grenzen, insbesondere
Sperrzeit und keine parallelen Läufe derselben Regel, gelten auch für Imports.
Import ist eine bewusste Migration; er garantiert keine identische Häufigkeit.

Die Änderung bleibt librarylokal. Gemeinsame Helper werden unverändert genutzt.
Offline-Verträge, echte Formularcallbacks und synthetische Z2M-Laufzeit sind geprüft;
eine Installation oder reale Geräteschaltung ist damit nicht belegt.

## Schnittstellenquellen

- [Symcon: IPS_GetEvent](https://www.symcon.de/en/service/documentation/command-reference/management-events/ips-getevent/)
- [Symcon: IPS_GetActions](https://www.symcon.de/en/service/documentation/command-reference/management-scripts/actions/ips-getactions/)
- [Z2M: Set-Schnittstelle für Geräte und Gruppen](https://www.zigbee2mqtt.io/guide/usage/mqtt_topics_and_messages.html)
- [Z2M: Zustands-API](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/state.ts)

Geprüft am 28.10.2026. Die Quellen definieren Schnittstellen; Implementierung und
Tests dieses Projekts sind eigenständig.
