# ADR 0007: Geräteaktionen direkt innerhalb Z2Ms übergeben

## Entscheidung

Die Engine löst stabile Geräte-/Gruppen-IDs unmittelbar vor jeder Aktion auf
und übergibt die reguläre Set-Nachricht mit vollständigem Basistopic über
`eventBus.emitMQTTMessage` an Z2Ms vorhandene Befehlsverarbeitung. Der native
Converter bleibt für Zigbee und Gerätezustände zuständig. MQTT bleibt der
Verwaltungsweg für Installation, YAML, Aktivierung, Stopp und Status.

Dies ersetzt die MQTT-Veröffentlichung der Geräteaktionen aus ADR 0005.
Es gibt keinen parallelen MQTT-Fallback, der Aktionen doppelt auslösen könnte.
Ohne native Host-Schnittstelle verweigert die Engine die Aktivierung.

## Begründung und Praxisnachweis

Auf der freigegebenen Testinstallation kam eine eigene Veröffentlichung auch
nach nachgewiesenem Neustart und mit `skipReceive=false` nicht zum Z2M-Empfänger
zurück. Ein begrenzter Echo-Test bestätigte die fehlende Rückzustellung auf dem
vorhandenen MQTT-Weg; daraus folgt keine allgemeine Aussage über andere Broker.
Eine direkte native Befehlsübergabe schaltete dasselbe Testgerät erfolgreich.
Die aktualisierte gespeicherte Geräteautomation wurde anschließend praktisch
ausgelöst und nachweislich ausgeführt; Aktivierung und Stopp erfolgten auch
über die nativen Symcon-Formularbuttons.

## Grenzen

Die EventBus-Übergabe kehrt ohne Gerätebestätigung zurück. Asynchrone Fehler
der Converter oder Zigbee-Kommunikation sind im Z2M-Protokoll zu prüfen.
Lokale Übergabefehler, unbekannte Ziele und Mengenbegrenzungen bleiben im
Engine-Status erkennbar. Ein Stopp kann bereits übergebene Befehle nicht widerrufen.
Offline-Tests prüfen native Übergabe, Basistopic, fehlende Host-API und den
Verzicht auf Set-Veröffentlichungen; eine synthetische Übergabe bestätigt keine
physische Gerätewirkung.

Schnittstellenquellen: [EventBus](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/eventBus.ts)
und [native Publish-Verarbeitung](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/extension/publish.ts).
