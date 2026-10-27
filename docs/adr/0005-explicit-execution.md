# ADR 0005: Ausführung einer ausdrücklich aktivierten Speicherrevision

- Datum: 27.10.2026
- Status: Angenommen

Die hier ursprüngliche MQTT-Veröffentlichung von Geräteaktionen wird durch
[ADR 0007](0007-native-command-dispatch.md) ersetzt. Aktivierungs- und Speichervertrag
bleiben unverändert.

## Entscheidung

Die Erweiterung besitzt eine eigene Engine für das bestehende Schema 1.
`validate` und `store` aktivieren keine Regeln. Ein eigener `activate`-Auftrag
bindet die aktuell gespeicherte Revision an ihre SHA-256-Prüfsumme, die aktuelle
Erweiterungssitzung und eine kurzlebige Einmalkennung. Letztere wird nur in der
Antwort auf eine aktive Statusanfrage ausgegeben, nicht im retained Status.
Sie verfällt nach 30 Sekunden und wird bei jedem Aktivierungsversuch verbraucht.
Eine neuere Statusabfrage, erfolgreiche Speicherung oder Deaktivierung macht sie
ungültig. Das verhindert erneutes Aktivieren durch alte Aufträge derselben Sitzung.
Die Kennung ist keine Authentifizierung; MQTT-Zugriffsrechte bleiben erforderlich.

Die Geräteidentität bleibt die IEEE-Adresse. Empfang nutzt den nativen Z2M-Bus
`onPublishEntityState`, ausschließlich dessen frische `payload`-Eigenschaften;
das zusammengeführte MQTT-`message` ist keine Triggerquelle. Aktionen lösen die
aktuelle Geräte-/Gruppenbezeichnung erst bei der Übergabe auf und veröffentlichen
über Z2Ms MQTT-Schnittstelle einen regulären `/set`-Auftrag ohne Retain.
Damit bleibt die eigentliche Geräteumsetzung bei Z2Ms vorhandenen Convertern.

## Lebenszyklus

Nach einem Start ist die Engine inaktiv. Die YAML wird validiert geladen, aber
nicht automatisch aktiviert. `deactivate`, Stop und eine erfolgreich gespeicherte
geänderte Revision verwerfen ausstehende Trigger- und Aktionsverzögerungen.
Bytegleiche Speicherung und erneute Aktivierung derselben bereits aktiven Fassung
starten deren Timer nicht neu. Eine beschädigte oder nur aus Backup geladene
Konfiguration darf nicht aktiviert werden. Es gibt keine Geräteabfrage und keine
Startaktion bei Aktivierung.

## Grenzen

Eine MQTT-Übergabe bestätigt nicht die physische Gerätewirkung. Ein bereits
übergebener Befehl kann nicht zurückgerufen werden. Gleichzeitige Folgen einer
Regel werden verworfen, nicht nachgeholt. Weitere Grenzen und Zeitregeln stehen
in [RUNTIME.md](../RUNTIME.md). Neue Bedienaktionen im Symcon-Formular sind ein
eigener Integrationsschritt; diese Umsetzung erweitert den Empfänger und seine
Statuskompatibilität, nicht die bestehenden Speicherbuttons.
