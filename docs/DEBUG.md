# Debug und MQTT-Diagnose

Im Symcon-Debugfenster die Instanz Z2MAutomations auswählen und anschließend
„Zigbee2MQTT-Verbindung prüfen“ ausführen. START/ENDE kennzeichnen öffentliche
Aufrufe, SENDEN die Übergabe an das Gateway, EMPFANG eingehende Modulnachrichten,
AKTION die Auswertung und FEHLER Ablehnungen oder Zeitlimits. Übergabe bedeutet
noch keine Bestätigung durch Z2M. Vorgang und Aufruf verbinden zusammengehörige
Meldungen; asynchrone Antworten erhalten einen eigenen Vorgang und werden über
einen gehashten Anfragebezug zugeordnet.

Die Kategorie enthält Modul, Ereignis, Funktion und Quellzeile. Die Nutzdaten
enthalten Instanz, Vorgang, Aufruf, Meldung und Bezug und sind auf 4096 Bytes
begrenzt. YAML, Erweiterungscode, gesamte MQTT-Bodies, Sitzungskennungen und
beliebige fremde Fehlermeldungen werden nicht ausgegeben. Der unveränderte
zentrale DebugHelper bereinigt zusätzlich bekannte Geheimnisse. Ein defekter
Debugkanal darf den Datenfluss nicht verändern.

MQTT Client und MQTT Server werden unterstützt. Der MQTT Client verbindet sich
über seinen Client Socket mit dem Broker, an dem auch Z2M angemeldet ist.
Das Gateway muss die Topics `<Basistopic>/bridge/#` und
`<Basistopic>/symcon/automations/#` abonnieren. Empfangsfilter im Modul ersetzen
keine Broker-Abonnements. Es werden keine gemeinsamen Gatewayeinstellungen geändert.

Status 201 bedeutet fehlende/inaktive kompatible MQTT-Anbindung. Status 202
bedeutet, dass Z2M offline ist oder noch kein Online-Nachweis empfangen wurde.
Eine frische, zur laufenden Anfrage gehörende Health-Antwort bestätigt jetzt
auch ohne vorheriges bridge/state die Erreichbarkeit. `healthy: false` bleibt
als eigener Gesundheitsfehler sichtbar. Retained oder fremde Antworten dürfen
diesen Nachweis nicht liefern. Eine spätere Offline-Meldung setzt ihn zurück.

Keine Live-Installation oder Prüfung am echten Broker im Rahmen der Offline-Tests.
Die Darstellung im nativen Debugfenster ist noch praktisch zu prüfen.
