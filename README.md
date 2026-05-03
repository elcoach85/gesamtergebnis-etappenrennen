# gesamtergebnis-etappenrennen

Dieses Wordpress Plugin erzeugt aus Tagesergebnissen nach den Wettkampfbestimmungen Straße von German Cycling eine Gesamtwertung.

## Werkzeuge-Menue in WordPress

Das Plugin fuegt unter **Werkzeuge** zwei Eintraege hinzu:

- `RaceDays Ingest` startet `lib/RaceDaysResults-php/02_Tool/main_ingest.py`
- `RaceDays Resultsfiles` startet `lib/RaceDaysResults-php/02_Tool/make_resultsfiles.py`

Beide Aktionen sind nur fuer Benutzer mit der Capability `manage_options` verfuegbar.

## Logging

Beim Start und nach Ende eines Skriptlaufs schreibt das Plugin ein Log nach:

- `logs/gesamtergebnis.log`

Das Log enthaelt unter anderem:

- gestartetes Skript
- ausgefuehrten Command
- Laufzeit in Millisekunden
- Exit-Code
- gekuerztes `stdout` und `stderr`

## Python-Binary konfigurieren

Standardmaessig verwendet das Plugin `python` aus dem System-Pfad. Falls ein anderer Interpreter verwendet werden soll, gibt es zwei Optionen:

- Umgebungsvariable `WP_GEG_PYTHON_BIN` setzen
- WordPress-Filter `geg_python_binary` verwenden
