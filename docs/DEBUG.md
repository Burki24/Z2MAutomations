# Debug und MQTT-Diagnose

## Aktionen im zweiten Z2M-System

`GetActionCatalog` verwendet den zentralen START-/ENDE-Ablauf und meldet die
Anzahl schaltbarer Ziele. Fehlende Partnerdaten werden mit dem nächsten
Bedienungsschritt erklärt. Variablenbindungen werden erneut geprüft; keine
Kataloginhalte, Gerätenamen oder Aktionswerte werden zusätzlich protokolliert.
Die Erweiterung meldet Freigabeanforderung, Empfang der einmalig freigegebenen
Aktion, native Übergabe und deren Bestätigung. Fehler nennen feste Codes und
keine Netzwerk-/Exception-Inhalte. Kennungen, Tokens und Fingerabdrücke werden
nicht geloggt. Bestätigte Übergabe und Gerätewirkung bleiben sprachlich getrennt.

## Wertänderung als Import-Auslöser

Die native Auslöserdiagnose zählt `NurBeiWertänderung`. Die Z2M-Runtime erklärt
passende, aber unveränderte Meldungen mit „Wert unverändert; Auslöser verlangt
eine Wertänderung“. Regelkennung und Auslösernummer bleiben nachvollziehbar;
Gerätewerte und Namen werden nicht zusätzlich protokolliert.


## Import-Gerätedaten laden

`LoadImportDevices` verwendet den kanonischen START-/ENDE-Ablauf. Deutsche
Meldungen erklären fehlende Bestätigung, Anfrage am eigenen Gateway/Topic,
Abschluss mit erneuter Importprüfung oder einen festen Fehlergrund. Die
Gerätenamen und Instanz-IDs aus der Übersicht bleiben außerhalb dieser zusätzlichen
Ausgaben. Das Absenden einer Anfrage wird nicht als Importfreigabe ausgegeben.


## Suchbereich der Ereignissuche

Die Suche nennt eigenes Gateway, konfiguriertes Basistopic, Anzahl geprüfter
Ereignisse und zugeordneter Quellen. Der Formularaufbau meldet die Anzahl lokal
gefundener Topics am eigenen Gateway. Fremde Variablen werden vor dem Lesen von
Instanzkonfigurationen ausgeschlossen; ihre Namen und Inhalte werden nicht geloggt.


## Ereignisimport

`ScanEventImports`, `PreviewEventImport` und `ImportSymconEvent` verwenden den
kanonischen START-/ENDE-Ablauf. Deutsche Meldungen nennen geprüfte bzw.
zugeordnete Anzahlen, die Importfähigkeit und feste Ablehnungsgründe. Ereignisnamen,
Auslösewerte, Skriptinhalte und Vorschautokens werden nicht protokolliert.
Die Übernahme bestätigt ausdrücklich die deaktivierte lokale Regel und die
unveränderten Originale. Der Katalog-Debug zählt zusätzlich bestätigte Gruppen.
Die Z2M-Laufzeit meldet, wenn ein Umschaltziel aus dem Cache ermittelt wurde;
`state_unavailable` erklärt fehlende Zustände ohne Gerätedaten im Protokoll.


## Automatische Statusprüfung

`RefreshStatus` ist der öffentliche Timer-Einstieg mit nativem Debug-Ablauf.
Die deutschen Ausgaben erklären Anlass, Zusammenfassung bzw. Auslassen,
Start der Health-Anfrage und die anschließende Erweiterungsabfrage.
Transportfehler und Antwortzeitüberschreitungen nutzen die vorhandene Diagnose.
YAML-Inhalte und Transaktionskennungen werden dabei nicht zusätzlich ausgegeben.

Beim Laden des Gerätekatalogs meldet `Kanalzustände` die Anzahl expliziter
Schaltkanal-Eigenschaften über die berücksichtigten Geräte. Mehrfaches Auftreten
als Auslöser und Aktion wird je Gerät nur einmal gezählt. Namen, IEEE-Adressen
und Kanalinhalte werden nicht an die Debug-Ausgabe übergeben. Die Regelauswahl
bestätigt weiterhin `FelderVorbelegt`, ohne die Regel zu protokollieren.

`InspectEditorTriggers` meldet Hinzufügen/Bearbeiten/Löschen der ODER-Auslöserliste.
Beim Löschen beschreibt `AuslöserVorLöschen` nur den nativen Vorbestand. Ungültige
Listen erhalten einen festen deutschen Hinweis. SaveScheduledAutomation nennt
Auslöseranzahl und ODER-Verknüpfung. Die öffentlichen Abläufe verwenden zentrale
START-/ENDE-Ausgaben; Gerätenamen, Auswahlwerte und Listeninhalte bleiben geschützt.

Regelauswahl meldet `FelderVorbelegt` und `EmpfängerBestätigt` getrennt. Ein
gültiger lokaler Entwurf kann nach Verlust der flüchtigen Z2M-Bestätigung
weiter zur Bearbeitungsansicht geladen werden. Änderungen bleiben bis zur
Bestätigung gesperrt. Namen, Uhrzeiten und Bedingungswerte werden nicht geloggt.

`OpenAutomationEditor` erklärt das Öffnen bzw. Zuklappen des aktiven Bereichs
und erhält dabei dessen Eingaben. Ein Wechsel delegiert an die instrumentierte
Regelauswahl. Falsche Bereiche werden sicher abgewiesen; Debug nennt nur feste
Bereichsnamen. Der öffentliche Einstieg besitzt zentrale START-/ENDE-Ausgaben.

Die direkt eingebetteten Geräte-/Zeitbearbeitungen nennen bei Geräteauswahl,
Aktionslisten und Entwurfsübernahme ihren festen deutschen Automationsbereich.
Optionale Bereichsparameter schützen Formularaktionen vor verspäteten Aufrufen
aus einem inzwischen verlassenen Bereich; diese werden als FEHLER erklärt und
ändern weder den Entwurf noch Z2M. Öffentliche Einstiege verwenden unverändert
den zentralen Start-/Ende-Ablauf. Namen, Gerätekennungen und Regeln bleiben geschützt.

Der kompatible Aufruf `ShowYamlEditor` klappt jetzt den YAML-Bereich auf oder zu
und protokolliert diesen Zustand. Der Editor selbst bleibt innerhalb des Bereichs
verfügbar. Formularaktionen prüfen den aktuellen YAML-Inhalt unabhängig vom
Aufklappzustand; manuelle Änderungen werden beim grafischen Bearbeiten geschützt.

`SelectAutomation` protokolliert beim Öffnen der Bearbeitung den festen deutschen
Bereich „Geräteausgelöst“, „Zeitgesteuert“ oder „Weitere Auslöser“ sowie die
Unterscheidung zwischen neuer und bestehender Regel. `ConfigureEditorSchedule`
nennt den gewählten Bereich und die optionalen Bedingungsarten. Beide verwenden
den zentralen Start-/Ende-Ablauf; Auswahl, Namen, Zeitwerte und Regeln bleiben
geschützt. Bereichswechsel bearbeiten nur den lokalen Editor und senden nichts
an Z2M. Ungültige Bereiche und veraltete Entwürfe werden nachvollziehbar abgelehnt.

`InspectEditorActions` erklärt Hinzufügen, Bearbeiten, Löschen und Umordnen der
Aktionsliste. Beim nativen Löschen enthält der Callback noch den Vorbestand:
Die Meldung kennzeichnet deshalb ausschließlich `AktionenVorLöschen` und gibt
keine alten Schritte als verbleibende Folge aus. Erst beim Übernehmen wird die
verbleibende Liste geprüft und mit ihren Schritten protokolliert.
Andere gültige Listen melden Anzahl, Schrittnummer und Wartezeit in Sekunden;
unvollständige Listen erhalten einen festen deutschen Korrekturhinweis. Es werden
keine Gerätekennungen, Namen, Auswahlwerte oder Listeninhalte ausgegeben. Die
Formularliste wird erst durch `SaveScheduledAutomation` in den lokalen Entwurf
übernommen; auch dieser Ablauf meldet die vorbereiteten Schritte. Alle öffentlichen
Einstiege verwenden den zentralen DebugFlowHelper mit Start/Ende, auch bei Ablehnung.

`ConfigureEditorSchedule` protokolliert die gewählte Auslöser-/Bedingungsart und
das Ein-/Ausschalten des Zeitfensters. `SaveScheduledAutomation` protokolliert
Start/Ende, feste Ablehnungsgründe, Auslöserart und Anzahl der Bedingungen.
Regelnamen, Gerätekennungen, Zeitpläne, Vergleichswerte und Options-JSON bleiben
aus dem Debug ausgeschlossen. Die eigentliche Auswertung läuft unverändert in
Z2M und verwendet dessen bereits instrumentierte Engine.

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

## Getrennte Eigenschaftsauswahl

`GetEditorItemForm` protokolliert das Öffnen und die erfolgreiche Vorbelegung des
Zeilendialogs. `ConfigureEditorItem` meldet Geräte-/Eigenschafts-/Wertwechsel und
ob die Auswahl zulässig ist. `SelectEditorConditionProperty` erklärt das
Zurücksetzen der abhängigen Wertauswahl. Katalogempfang meldet Geräteanzahl,
lesbare Eigenschaften und anhand Symcon geprüfte schaltbare Eigenschaften.
Keine Variablennamen, IEEE-Adressen, Regelwerte oder Auswahltokens im Debug.
Alle öffentlichen Einstiege verwenden zentrale Start-/Ende-Abläufe.

Die Installation protokolliert zusätzlich die Übergabe der Popup-Rückmeldung
mit Erfolgskennzeichen und festem Fehlercode. Fremde Fehlermeldungen gelangen
weder ins Popup noch ins Debug. Ein Darstellungsfehler verändert das
Installationsergebnis nicht.

Ist die Freigabe für externes JavaScript unbekannt, erklärt eine Fachmeldung,
dass der Auftrag über die offizielle Z2M-Schnittstelle geprüft wird. Unbekannt
ist kein Nachweis für eine deaktivierte Einstellung. Ein ausdrücklich gemeldetes
false blockiert weiterhin vor dem Versand.

## Messwertvergleiche und Bedingungslisten

InspectEditorTriggers meldet die Häufigkeit der gewählten Vergleichsoperatoren.
InspectEditorConditions erklärt Hinzufügen, Bearbeiten und den Vorbestand beim
Löschen; alle Bedingungen gelten gemeinsam (UND). SaveScheduledAutomation meldet
die gesamte Bedingungsanzahl und die UND-Verknüpfung. SelectAutomation meldet die
Anzahl vorbelegter Bedingungen. Keine Gerätenamen, IEEE-Adressen oder Werte
werden dafür protokolliert. Neue öffentliche Listenaktionen folgen denselben
zentralen Start-/Ende-Abläufen wie die bestehenden Editoraktionen.
