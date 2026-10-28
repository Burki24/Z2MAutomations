# Automationen ausführen

Die Erweiterung kann gespeicherte Schema-1-Automationen ausführen. Sie lädt die
YAML beim Start, bleibt zunächst aber inaktiv. Speichern, Validieren und
Aktivieren sind getrennte Vorgänge. Die Symcon-Oberfläche bietet dafür eigene
Aktivierungs-/Stoppbuttons mit erneuter Statusbestätigung sowie einen ersten
Klick-Editor; siehe [Bedienung](../Z2MAutomations/README.md). Die folgenden
Erweiterungsbefehle beschreiben den dabei verwendeten Transportvertrag.

## Aktivieren und stoppen

1. YAML über das Modul prüfen und speichern.
2. Eine frische `request/status`-Anfrage senden. Die korrelierte Antwort enthält
   `session`, `stored_revision`, `checksum` und `activation_token`.
3. Innerhalb von 30 Sekunden an `<Basistopic>/symcon/automations/request/activate`
   folgendes JSON ohne Retain senden; die Platzhalter sind aus genau dieser
   Statusantwort zu übernehmen:

```json
{
    "protocol_version": 1,
    "transaction": "synthetic-activate-1",
    "session": "CURRENT_SESSION",
    "expected_revision": 1,
    "checksum": "EXACT_STORED_SHA256",
    "activation_token": "FRESH_ONE_USE_TOKEN"
}
```

Die passende `response/activate` enthält `status: ok`, `executing: true`,
`active_revision` und `active_checksum`. Es werden nur Regeln mit `enabled: true`
berücksichtigt. Quellen, Geräteziele und Gruppen müssen in Z2M vorhanden sein.
Aktivierung selbst sendet keinen Schaltbefehl und wertet keinen alten Zustand aus.
Auch eine leere Liste kann aktiviert sein; `enabled_count` zeigt die Regelanzahl.

`request/deactivate` verlangt nur `protocol_version`, `transaction` und `session`.
Die passende Antwort bestätigt `executing: false`. Die Sitzung wird geprüft;
explizites `retain: true` wird abgelehnt. Der native Z2M-Eventbus reicht das
Retain-Bit nicht allgemein weiter. Deshalb erfordert Aktivierung zusätzlich die
frische Einmalkennung; alte retained Statusmeldungen enthalten sie nicht.
Jede aktive Statusabfrage ersetzt die vorherige Kennung, jeder Aktivierungsversuch
verbraucht sie. Nach verlorener Antwort zuerst den Zustand abfragen statt blind
denselben Auftrag zu wiederholen. Die Kennung ist keine Zugriffskontrolle.

Zusätzliche Fehler: `activation_expired`, `checksum_mismatch`, `unknown_entity`,
`runtime_unavailable`. Sie ändern keine YAML-Datei. Beschädigte Dateien und
`recovered_read_only` sperren die Aktivierung.

## Ausführungsregeln

- Trigger einer Regel sind alternativ (ODER), Bedingungen gemeinsam (UND).
- Geräte-Trigger verwenden nur die im Ereignis enthaltene `payload`-Eigenschaft
  eines bekannten Geräts. Ein Wert nur im zusammengeführten `message` löst nichts
  aus. Gruppenereignisse, `publishCached` und `lastSeenChanged` sind keine Trigger.
- `eq`/`ne` vergleichen streng typgleich; keine Umwandlung von `"true"` oder
  numerischen Strings. Größenvergleiche verlangen endliche Zahlen. Fehlende
  oder komplexe Vergleichswerte erfüllen auch `ne` nicht.
- Ohne `for_seconds` ist jeder frische passende Bericht ein Ereignis. Wiederholte
  Tastendrücke mit demselben Aktionswert bleiben damit möglich. Pro Regel gilt
  mindestens eine Sekunde Abstand; während einer laufenden Folge wird kein
  weiterer Lauf vorgemerkt.
- `for_seconds` misst eine durchgehend passende Bedingung ab dem ersten neuen
  Bericht. Weitere passende Berichte setzen den Timer nicht zurück. Ein falscher
  Wert verwirft ihn. Pro zusammenhängender Wahrphase wird höchstens einmal
  ausgelöst; zum erneuten Scharfschalten ist ein falscher Bericht erforderlich.
- Gerätebedingungen verwenden nur seit Aktivierung beobachtete skalare Werte.
  Ein unbekannter Zustand erfüllt die Bedingung nicht. Es gibt keinen heimlichen
  `/get`-Auftrag, keine erfundene Verfügbarkeit und keine zugesicherte Wertfrische.
- Zeittrigger vergleichen einmal pro Sekunde die explizite IANA-Zeitzone.
  Pro lokalem Datum wird jeder Zeittrigger höchstens einmal ausgelöst. Eine bei
  der Herbstumstellung doppelte Uhrzeit läuft einmal; übersprungene Uhrzeiten,
  verpasste Sekunden und Zeiträume eines Stillstands werden nicht nachgeholt.
- Zeitfenster schließen `after` ein und `before` aus. Bei Fenstern über Mitternacht
  bezieht sich `weekdays` auf den Tag, an dem das Fenster begann.
- Aktionen laufen in YAML-Reihenfolge. `delay_seconds` ist die Wartezeit vor der
  jeweiligen Aktion, relativ zum Ende der vorigen Übergabe. Bedingungen werden
  unmittelbar vor jeder Aktion erneut geprüft. Ein fehlendes Ziel oder ein
  Fehler bricht die restliche Folge ab; es gibt keinen automatischen Retry.
- Identität bleibt IEEE-Adresse oder numerische Gruppen-ID. Umbenannte Ziele
  werden zum Sendezeitpunkt neu aufgelöst. Gruppen werden nicht neu angelegt.
  Eigenschaftswerte setzt der native Z2M-Converter um; eine vollständige Prüfung
  gegen alle Geräte-Exposes ist nicht Teil der Engine.
- Set-Aktionen gehen über `eventBus.emitMQTTMessage` direkt an Z2Ms native
  Befehlsverarbeitung, mit vollständigem Basistopic und regulärer `/set`-Nachricht.
  Es wird kein Set-Befehl über den Broker zurückgeschleift. Fehlt diese Host-API,
  wird die Aktivierung mit `runtime_unavailable` abgelehnt.

## Status, Grenzen und Neustart

Der Status ergänzt `runtime_version: 1`, `executing`, `active_revision`,
`active_checksum`, `enabled_count` und `runtime_error`. Fehler einer Folge halten
andere Regeln nicht an. `runtime_error` enthält den letzten Laufzeitfehler bis
zur nächsten erfolgreichen neuen Aktivierung. `action_failed`, `action_timeout`
und `rate_limited` beschreiben Übergabefehler, keine Gerätebestätigung.

Es gelten maximal 16 gleichzeitig laufende Regeln, 20 Set-Übergaben pro Sekunde
über alle Regeln und fünf Sekunden Wartezeit auf die Rückgabe der Befehlsübergabe.
Überzählige Läufe oder Aktionen werden verworfen. Die native EventBus-Übergabe
kehrt unmittelbar zurück; sie wartet nicht auf Converter oder Zigbee-Antworten.
Spätere Converter-/Gerätefehler stehen im Z2M-Protokoll und werden nicht als
bestätigte `runtime_error`-Gerätefehler ausgegeben. Der Übergabe-Timeout bedeutet
nicht, dass ein bereits gestarteter Auftrag zurückgenommen wurde.
Ein Stopp verhindert weitere Übergaben, kann aber keinen schon gesendeten
Zigbee-Befehl zurückrufen. Trigger, Timer und Wiederholungen werden nicht auf Platte
geschrieben. Nach Neustart, Erweiterungsupdate oder geänderter gespeicherter YAML
ist eine ausdrückliche erneute Aktivierung erforderlich. Das schützt bestehende
Konfigurationen vor unvermittelter Ausführung beim Update.

Die Engine schreibt sichere deutsche Ereignismeldungen in Z2Ms Debugkanal:
Aktivierung, Stopp, Beginn/Ende einer Folge, Übergabe und feste Fehlercodes.
Keine YAML, Aktionswerte, Sitzungs-/Aktivierungskennungen oder fremden
Exception-Texte. Ein defekter Logger unterbricht die Ausführung nicht.

## Nachweise und nächster Praxistest

`tests/runtime.test.mjs` prüft das erzeugte Einzeldatei-Artefakt mit synthetischen
Geräten, nativer Eventbus-Form, virtueller Zeit und echtem temporärem YAML-Speicher.
`tests/extension.test.mjs` und die PHP-/JavaScript-Integration bleiben erhalten.
Ein neuer Live-Upload und ein Schaltversuch sind noch nicht erfolgt.

Für den ersten Praxisversuch [simple-device.yaml](../libs/z2m/examples/simple-device.yaml)
verwenden: zwei bewusst ausgewählte reale Geräte eintragen, die nächste Revision
verwenden, prüfen, speichern und erst anschließend ausdrücklich aktivieren.

Geprüfte native Schnittstellen als Informationsquelle, ohne Übernahme von Code:
[Z2M EventBus](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/eventBus.ts),
[Controller-Ereignis](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/controller.ts),
[native Set-Verarbeitung](https://github.com/Koenkk/zigbee2mqtt/blob/master/lib/extension/publish.ts).

## Umschalten

Eine `toggle`-Aktion liest unmittelbar vor ihrer Übergabe den aktuellen
Z2M-Cachezustand ihrer `property` (state bzw. einzelner state-Kanal).
ON wird zu OFF, OFF zu ON. Das gilt auch für den Aggregatzustand einer Gruppe;
alle Mitglieder erhalten denselben Zielwert. Ohne bekannten Zustand endet die
Aktionsfolge mit `state_unavailable`. Die Laufzeit wartet weiterhin nicht auf
eine physische Gerätebestätigung. Ein abweichender oder veralteter Symcon-Wert
wird nicht zur Berechnung verwendet.
