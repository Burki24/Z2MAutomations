# Debug und MQTT-Diagnose

Regelauswahl, Bearbeiten und Löschen (`SelectAutomation`, `UpdateAutomation`,
`DeleteAutomation`) verwenden ebenfalls zentrale Start-/Ende-Abläufe. Debug
enthält nur feste Fehlercodes, Änderungsart und Regelanzahl; keine Namen,
Geräte-IDs, Auswahltokens oder YAML. Die Offline-Integration prüft Ablehnungen
veralteter Auswahlen und den Erhalt erweiterter Regeln über den echten Empfänger.

Die JavaScript-Engine protokolliert Aktivierung, Stopp und Aktionsfolgen über den
nativen Z2M-Logger mit sicheren deutschen Meldungen. Die PHP-Helper gelten für
das Symcon-Modul, nicht als JavaScript-Laufzeitabhängigkeit. Einzelheiten und
die geprüften Geheimnisschutz-/Loggerfehlerfälle stehen in [RUNTIME.md](RUNTIME.md).

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
zentrale DebugFlowHelper (mit DebugHelper) besitzt den Ablauf und bereinigt
zusätzlich bekannte Geheimnisse. ModuleDiagnostics ist nur noch ein dünner
Adapter zur bestehenden Fachinstrumentierung, kein eigener Ablaufzähler. Ein defekter
Debugkanal darf den Datenfluss nicht verändern.

Aktivierung, Stopp, Editor-Laden, Geräteauswahl, Entwurfserzeugung und dessen
Prüfung/Speicherung besitzen START/ENDE über den zentralen Helper. Tokens und
Editor-/YAML-Inhalte werden vor der Debugschnittstelle ausgeschlossen. Die
PHP-/JavaScript-Integration prüft Erfolg, Ablehnung, Timeout, verschachtelte
Aufrufe, Geheimnisschutz und einen absichtlich defekten Debugkanal.

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

Der native Empfangsfilter umfasst den vollständigen JSON-Umschlag (`.*….*`).
Deaktivierte oder ungültige Konfigurationen verwenden `^$`, das keine gültige
JSON-Nachricht annimmt. Lookarounds wie `(?!)` sind in der eingesetzten nativen
Regex-Engine nicht unterstützt. Die Offline-Prüfung deckt vollständige
Umschläge und JSON-Escaping ab; sie ersetzt keinen Test der nativen Engine.
