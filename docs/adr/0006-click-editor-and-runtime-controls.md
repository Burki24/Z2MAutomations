# ADR 0006: Klick-Editor und bestätigte Ausführungssteuerung

- Datum: 27.10.2026
- Status: Angenommen

Symcon ist Ersteller und Verwalter, keine Laufzeit für die Automationen.
Aktivieren und Stoppen verwenden die vorhandene Erweiterungsschnittstelle.
Nach der frischen Statusabfrage sendet das Modul den an Sitzung und Speicherstand
gebundenen Befehl und prüft dessen Ergebnis durch eine weitere Statusabfrage.
Tokens werden nicht in normalisierten Zustand, Attributcache oder Debug übernommen.
Bei Timeout oder Widerspruch bleibt der Ausführungszustand unbekannt. Stopp hat
Vorrang vor anderen noch laufenden Modulaufträgen.

Ein begrenzter erster Klick-Editor bietet Geräteereignisse und Aktionen aus
diskreten Z2M-Exposes an. Die Z2M-Erweiterung liefert den Katalog und die bereits
validierte gespeicherte Konfiguration. Symcon ergänzt neue Regeln lokal und erzeugt
JSON als gültige YAML, ohne einen zweiten YAML-Parser einzuführen. Bereits gespeicherte
Regeln werden strukturell vollständig erhalten. Name, IEEE-ID und ausgewählte
skalare Werte haben getrennte Rollen; ein Anzeigename ist nie Geräteidentität.

Der Editor schreibt nichts automatisch nach Z2M. Laden ersetzt nur nach Bestätigung
den lokalen Entwurf. Hinzufügen, Prüfen, Speichern und Aktivieren bleiben getrennt.
Geänderte Quellrevision oder manuelle Expertentexte dürfen nicht unbemerkt
überschrieben werden. Die ergänzten einfachen Regeln sind für die spätere
Aktivierung markiert, schalten aber noch nicht beim Erstellen oder Speichern.

Die einfache Oberfläche benötigt keine YAML-Kenntnisse. Bearbeiten/Löschen aller
Regeltypen, Bedingungen, Zeitregeln, numerische Grenzen und Gruppen im Klick-Editor
sind spätere Erweiterungen; der Expertentext bleibt verfügbar. Der gemeinsame
DebugFlowHelper besitzt die PHP-Abläufe, Fachlogik und Katalog bleiben librarylokal.
