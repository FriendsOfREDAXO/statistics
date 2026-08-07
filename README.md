# Analytics Addon für REDAXO CMS

Dieses Addon stellt im REDAXO CMS eine serverseitige Besucherzählung bereit und visualisiert die erfassten Metriken im Backend mit ECharts und DataTables.

## Features

Erfasst und visualisiert werden unter anderem diese Metriken:

- Tag des Besuches mit Datum und Wochentag
- Besuchszeit
- Browser
- Gerätetyp
- Betriebssystem
- Gerätemarke und Gerätemodell
- Bots und Crawler
- Referrer
- Anzahl besuchter Seiten in einer Sitzung
- Besuchsdauer in einer Sitzung
- Ausstiegsseiten

Das Addon arbeitet ohne clientseitige Cookies.

Die IP-Adresse des Besuchers wird nicht roh gespeichert, sondern gemeinsam mit dem User-Agent gehasht:

`hash = sha1(ipAdresseClient + userAgentClient)`

Der User-Agent selbst wird nicht auf dem Server gespeichert. Damit lässt sich der Hash nicht direkt einer IP-Adresse zuordnen. Die Hashes werden genutzt, um wiederholte Seitenaufrufe desselben Besuchers nicht mehrfach in die Statistik einfließen zu lassen.

Zur weiteren Datensparsamkeit kann der mitgelieferte Cronjob genutzt werden. Er sollte idealerweise täglich um `00:05` ausgeführt werden und entfernt nicht mehr benötigte Hashes des Vortags.

### Medien-Tracking

Medienaufrufe können auf zwei Arten erfasst werden:

1. Alle Medienaufrufe werden global mitgeloggt.
2. Nur ausgewählte Medien werden über einen Media-Manager-Effekt erfasst.

### Events

Über einen API-Request können Frontend- oder Backend-Ereignisse erfasst werden, zum Beispiel Link-Klicks oder Formularversand.

## Installation

1. Repository herunterladen.
2. Im Ordner `redaxo/src/addons` entpacken.
3. Den Ordner in `statistics` umbenennen.

Alternativ kann das Addon über den REDAXO-Installer installiert werden.

## Architektur

Das Addon besteht im Kern aus drei Bereichen:

1. Erfassung von Seitenaufrufen, Medienaufrufen und Events
2. Aggregation der Rohdaten in Statistik-Tabellen
3. Backend-Auswertung mit ECharts und DataTables

Wichtige Dateien und Verantwortlichkeiten:

- `boot.php`: Registrierung von Assets, Hooks und Tracking-Verhalten
- `install.php`: Datenbankschema und Performance-relevante Indizes
- `pages/stats.php`: Backend-Startseite mit Overview, Chart-Containern und Lazy-Loading-Metadaten
- `assets/statistics.js`: Initialisierung von DateFilter, DataTables, Charts und Lazy Loading
- `lib/ChartData.php`: Hauptaggregationen für Tages-, Monats-, Jahrescharts und Heatmap
- `lib/StatsChartConfig.php`: wiederverwendbare Chart-Konfigurationen für zentrale JS-Initialisierung
- `lib/StatsDashboard.php`: Komposition der stats-Startseite
- `lib/StatsMainChartSection.php`: Renderer für den Hauptchart-Bereich mit Tabs und Collapse-Containern
- `lib/StatsLazySection.php`: Renderer für die unteren Lazy-Placeholder der Startseite
- `lib/ListData.php`: Tabellen unter den Hauptcharts
- `lib/StatsLazyBlockRenderer.php`: serverseitiger Renderer für nachgeladene Statistikblöcke
- `lib/rex_api_statistics_lazy_block.php`: Backend-Endpoint für Lazy-Loading-Blöcke

## Performance

Die größten Performance-Gewinne der aktuellen Codebasis stammen aus diesen Maßnahmen:

1. Datenbankindizes für die tatsächlich genutzten Filter-, Join- und Group-By-Pfade
2. Entfernen von N+1-Abfragen in der Chart-Datenaufbereitung
3. Wiederverwendung derselben Aggregation für Chart, Tabelle und Detailansicht
4. Lazy Loading teurer Backend-Blöcke statt vollständigem Sofort-Rendern

### Wichtige Indexpfade

Besonders wichtig sind Indizes für diese Abfragemuster:

- `date BETWEEN :start AND :end`
- Gruppierungen nach `date`, `month` und `year`
- URL-, Referrer- und Status-Auswertungen
- Domain-Filter für Tages- und Besucherstatistiken

Für sehr lange Felder wie URL und Referrer werden Prefix-Indizes verwendet. Der Grund ist die MySQL-/MariaDB-Key-Length-Grenze bei `utf8mb4`. REDAXOs Schema-Helper kann diese Prefix-Längen nicht direkt ausdrücken, deshalb werden die betroffenen Indizes in `install.php` per SQL ergänzt.

### Optimierte Hotspots

Folgende Bereiche wurden bereits gezielt auf Serverlast reduziert:

- Hauptcharts in `lib/ChartData.php`
- Seiten-, Referrer-, Medien- und Event-Auswertungen
- Detailansichten für URL, Referrer, Medien und Events
- Session-Statistiken wie Seiten pro Sitzung, Besuchsdauer und Ausstiegsseiten
- `pagestats_data`-Auswertungen wie Browser, Gerätetyp, OS, Brand, Model, Hour, Weekday und Country
- Overview-Summen auf der Startseite

### Lazy Loading auf der Statistik-Startseite

Die Startseite `page=statistics/stats` ist bewusst zweistufig aufgebaut.

Beim ersten Seitenaufbau werden synchron geladen:

- Datumsfilter
- Overview-Kennzahlen
- Tageschart
- Heatmap

Erst bei Sichtbarkeit oder Benutzeraktion werden nachgeladen:

- Monats- und Jahreschart beim Öffnen des jeweiligen Tabs
- Geräte- und Client-Statistiken
- erweiterte Session-Statistiken
- Bot-Tabelle
- Tabellen unter den Hauptcharts beim Aufklappen der Collapse-Bereiche

Dadurch bleibt die Seite sofort benutzbar, während schwere Sektionen nur dann Serverzeit verbrauchen, wenn sie tatsächlich geöffnet oder in den Viewport gescrollt werden.

## Entwicklungshinweise

### .tools (Maintainer-Werkzeuge)

Der Ordner `.tools` enthält Hilfsskripte für Wartung und Release-Prozess. Diese Skripte sind für Maintainer gedacht und werden nicht im regulären Addon-Betrieb benötigt.

Aktuell enthalten:

- `.tools/release.php`
    - Unterstützt den Release-Flow, indem Version/Datum im Changelog vorbereitet und die Versionsnummer in `package.yml` angepasst wird.
    - Typischer Aufruf: `php .tools/release.php 3.6.0`

- `.tools/update-frontend-assets.sh`
    - Aktualisiert die vendorten Frontend-Dateien in `assets/` (DataTables und ECharts) auf die neueste Version der freigegebenen Major-Linien.
    - Typischer Aufruf: `bash .tools/update-frontend-assets.sh`
    - Optional lassen sich Zielversionen setzen: `DT_VERSION=1.13.11 ECHARTS_VERSION=5.6.1 bash .tools/update-frontend-assets.sh`

Hinweis:

- Der Ordner `.tools` ist per `installer_ignore` und `export-ignore` für Release-Archive ausgeschlossen.

### Backend-Startseite erweitern

Wenn neue Blöcke auf `pages/stats.php` ergänzt werden, sollte zuerst entschieden werden, ob sie synchron oder lazy geladen werden müssen.

Faustregel:

- Overview und zentrale Primärcharts: synchron
- große Tabellen, Sekundärcharts und optionale Detailblöcke: lazy

Neue Lazy-Blöcke sollten über `StatsLazyBlockRenderer` und `rex_api_statistics_lazy_block` angebunden werden, statt zusätzliche Inline-Abfragen direkt in `pages/stats.php` einzubauen.

### Tabellen und Charts

- DataTables werden zentral in `assets/statistics.js` initialisiert.
- Nachgeladene HTML-Blöcke müssen ihre Tabellen nicht selbst initialisieren.
- ECharts-Optionen für Lazy-Blöcke werden serverseitig erzeugt und als JSON ausgeliefert.
- Auch reguläre Unterseiten können Chart-Konfigurationen als JSON-Script aus PHP ausgeben und durch `assets/statistics.js` zentral initialisieren lassen.
- Die Startseite liefert nur noch eine kleine JSON-Konfiguration aus PHP; die Chart-Initialisierung läuft vollständig im zentralen JS.

### Detailseiten

Chart und Tabelle sollten nach Möglichkeit immer denselben Datenbestand wiederverwenden. Zusätzliche SQL-Abfragen nur für die zweite Darstellung sind in diesem Addon meist ein Performance-Regression-Muster.

## Testing und Validierung

Dieses Addon hat derzeit keine dedizierte Test-Suite. Änderungen sollten mindestens so validiert werden:

1. Backend-Seite `statistics/stats` mit und ohne Datumsfilter öffnen
2. Monats- und Jahreschart per Tab-Wechsel prüfen
3. Lazy-Blöcke beim Scrollen prüfen
4. Collapse-Tabellen unter Tages-, Monats- und Jahreschart öffnen
5. Seiten-, Referrer-, Medien- und Event-Details testen
6. Installation und Update gegen bestehende Datenbank prüfen

Besonders nach Änderungen an `install.php`, `ChartData.php`, `ListData.php`, `assets/statistics.js` und `pages/stats.php` sollte das Verhalten im Backend einmal vollständig geprüft werden.

## Beispiele

### Frontend Counter

Falls im Frontend ein Besucher-Counter ausgegeben werden soll, kann zum Beispiel folgende Modul-Ausgabe verwendet werden:

```php
<?php

use AndiLeni\Statistics\VisitorCounter;

?>

<p>Besucher: <code><?php echo VisitorCounter::getText() ?></code></p>
```

Der ausgegebene Text kann anschließend beliebig gestaltet werden.

### Backend Event loggen

```php
<?php

use AndiLeni\Statistics\Event;

Event::log('my_event_name');
```

### Download-Counter

1. Im Media Manager einen neuen Medientyp mit dem Namen `log` anlegen.
2. Zu diesem den Effekt `Datei in Statistik loggen` hinzufügen.
3. Ein Modul anlegen.

Eingabe:

```text
<label>Downloads:</label>
REX_MEDIALIST[id="1" widget="1"]
```

Ausgabe:

```php
<div class="container">
    <h2>Downloads:</h2>
    <table class="table">
        <tr>
            <th>Name</th>
            <th>Link</th>
        </tr>

        <?php
        foreach (explode(',', 'REX_MEDIALIST[1]') as $img) {
            echo '<tr>';
            echo '<td>' . $img . '</td>';
            echo '<td><a href="' . rex_media_manager::getUrl('log', $img) . '">Download</a></td>';
            echo '</tr>';
        }
        ?>
    </table>
</div>
```

1. In den Einstellungen das Tracking aktivieren.
2. Das Beispiel erzeugt dann eine Tabelle, deren Download-Links in der Statistik erfasst werden.

Preview:

![Beispiel Download-Counter](https://raw.githubusercontent.com/andileni/statistics/main/preview/6.png)

### Kampagnen-Tracking

Ziel: Das Klicken eines Links im Frontend soll erfasst werden.

1. Ein Modul anlegen.

Eingabe:

```html
<label>Kampagnen-Name:</label>
<input type="text" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]">
```

Ausgabe:

```html
<a class="btn btn-primary" onclick="myFunction()" href="http://example.com/">Link</a>

<script>
function myFunction() {
    fetch('/?rex-api-call=stats&name=REX_VALUE[1]');
}
</script>
```

## Preview

### Startseite des Addons mit den wichtigsten Metriken

![Preview Startseite](https://raw.githubusercontent.com/andileni/statistics/main/preview/1.png)

### Seite mit Details über eine URL

![Preview URL-Details](https://raw.githubusercontent.com/andileni/statistics/main/preview/2.png)

### Statistiken über Medienaufrufe

![Preview Medien 1](https://raw.githubusercontent.com/andileni/statistics/main/preview/5.png)

![Preview Medien 2](https://raw.githubusercontent.com/andileni/statistics/main/preview/7.png)

### Verweise

![Preview Referrer](https://raw.githubusercontent.com/andileni/statistics/main/preview/8.png)

### API und Kampagnen

![Preview API](https://raw.githubusercontent.com/andileni/statistics/main/preview/9.png)

### Frühere Integration in yakamara/dashboard

![Preview Dashboard](https://raw.githubusercontent.com/andileni/statistics/main/preview/4.png)
