# ADR 0002: Native Symcon-MQTT-Verbindung wiederverwenden

- Datum: 27.10.2026
- Status: Angenommen

## Kontext

Die Grundanbindung soll vorhandene MQTT-Verbindungen mitnutzen und andere
Symcon-Module nicht beeinflussen. Die bestehende Zigbee2MQTT-Gerätelibrary nutzt
bereits die nativen MQTT-Datenfluss-IDs. Ihre librarylokalen MQTT-Helper wurden
auf Verträge geprüft; sie sind keine zentral verteilten Helper und werden nicht
kopiert. Das neue Projekt bleibt unabhängig von dieser Library.

## Entscheidung

Z2MAutomations ist direktes Kind eines nativen MQTT Client oder MQTT Server.
GetCompatibleParents bietet beide Typen mit connect an. Kein eigenes
ConnectParent/RequireParent und keine automatische Neuanlage einer Verbindung.
GetConfigurationForParent wird nicht überschrieben: Einstellungen und
Subscriptions eines geteilten Gateways bleiben erhalten.

Gemeinsame Helper für DataFlow, ParentConnection und VariablePresentation werden
über das kanonische Bundle-Verfahren unverändert eingebunden. Topic-Validierung,
Z2M-Auswertung und Gesundheitsanfragen bleiben librarylokal.

Die erste Ausbaustufe empfängt nur Bridge-Status, ausgewählte Bridge-Informationen,
Erweiterungsnamen und Antworten auf health_check. Sie bietet keinen beliebigen
Publish-Aufruf und keine Installation, Freischaltung, Neustarts oder Schaltbefehle.

## Folgen

Der Benutzer muss beim MQTT Client ein passendes Abonnement sicherstellen.
Der Empfangsfilter des Moduls ersetzt kein Broker-Abonnement.
Mehrere Module können dieselbe Verbindung teilen. Eine Instanz bindet ihren
Cache und die Anfrage an Parent-ID, Basistopic, Aktivierung und Zeitlimit.
Eine Verbindung oder ein Topic wird niemals anhand eines Anzeigenamens zugeordnet.

Die Anbindung ist standardmäßig deaktiviert. Nach Aktivierung erfolgt kein
automatischer Versand. Gesundheitsanfragen laufen asynchron, mit einer offenen
Transaktion, einem Timer und einem eigenen Semaphore pro Instanz. Das Senden
erfolgt außerhalb der Sperre, damit eintreffende Antworten nicht blockieren.
