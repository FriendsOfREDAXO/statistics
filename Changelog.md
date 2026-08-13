# Changelog

## [3.7.2] - 13.08.2026

-   Mehrere XSS-Sicherheitslücken im Backend geschlossen: getrackte URLs werden in JSON-Script-Blöcken sicher kodiert und URL-/Domain-Ausgaben kontextgerecht escaped
-   Weitere Backend-XSS-Risiken in Datumsfilter-Attributen und dynamischen Detailüberschriften behoben; dafür werden die REDAXO-Core-Escaper zentral verwendet
-   HTML-Ausgaben im Add-on vollständig auf den REDAXO-Core-Escaper `rex_escape()` vereinheitlicht
-   Zustandsändernde Backend-Formulare und Aktionslinks gegen CSRF abgesichert: Wartungsaktionen, GeoIP-Update, Seiten-Favoriten, URL-Ignorierung sowie Event- und Medienlöschungen; REDAXO-generierte Konfigurationsformulare verwenden weiterhin ihren integrierten CSRF-Schutz
-   Mindestanforderung auf PHP 8.4 angehoben; Composer-Plattform und CI prüfen jetzt ausschließlich PHP 8.4 oder höher

## [3.7.1] - 12.08.2026

-   Fehler in der Wartungsaktion „Nicht-200-URLs bereinigen“ korrigiert: 200er-Status werden jetzt robust per Präfixprüfung (`200%`) behandelt und nicht mehr versehentlich mitgelöscht
-   Statusfilter in Seitenauswertung und Chart-Daten vereinheitlicht: 200er-Status (z. B. `200` und `200 OK`) werden konsistent per Präfix erkannt, statt auf einen einzelnen exakten Status-String festgelegt zu sein
-   README erweitert um eine transparente Liste typischer Störanfragen-Muster sowie Hinweise auf konfigurierbare Zusatzfilter

## [3.7.0] - 07.08.2026

-   Datenbankschema für Deployments gehärtet: nicht reproduzierbare Prefix-Indizes auf langen URL-/Referer-Spalten durch portable Hash-Spalten und reguläre REDAXO-Indizes ersetzt
-   Bestehende Installationen werden beim Update/Reinstall vollständig migriert; URL- und Referer-Hashes werden nachgetragen und alte Prefix-Indizes entfernt, ohne Langwerte zu kürzen
-   URL-/Referer-Abfragen, Status-Joins und Wartungsläufe verwenden die neuen Hash-Indizes mit zusätzlichem Vergleich des Originalwerts zur Kollisionsabsicherung
-   Grundsortierung der Seitenaufrufe im Backend auf „Aufrufe“ absteigend korrigiert
-   README um Hinweise zu Backup, Wartungsfenster und zusätzlichem Ressourcenbedarf beim Update großer Statistikbestände ergänzt
-   Automatischen Frontend-Asset-Workflow repariert: npm-Versionslisten werden robust auf stabile Major-Versionen gefiltert, ungültige Versionsvorgaben abgewiesen und GitHub Actions auf Node.js 24 aktualisiert
-   Fehler in der Wartungsaktion „Nicht-200-URLs bereinigen“ behoben: 200er-Status werden nicht mehr fälschlich als Nicht-200 behandelt

## [3.6.0] - 07.08.2026

-   Unbenutzte Abhängigkeiten und Frontend-Assets bereinigt: `exceljs.min.js` und `jspdf.umd.min.js` entfernt sowie die nicht mehr benötigten Composer-Abhängigkeiten `phpoffice/phpspreadsheet` und `matomo/referrer-spam-blacklist` aus dem Addon entfernt
-   Release-Paketierung ergänzt: Entwicklungsordner `.tools` und `.github` werden über `installer_ignore` sowie per `.gitattributes` (`export-ignore`) aus Release-Archiven ausgeschlossen
-   Frontend-Vendor-Assets aktualisiert (DataTables auf 1.13.11, ECharts auf 5.6.0) und automatischen GitHub-Workflow für regelmäßige Asset-Update-PRs ergänzt (`.github/workflows/update-frontend-assets.yml`)
-   Tracking-SQL gehärtet und vereinheitlicht: bisherige String-SQL mit `addslashes` in `Visit` auf parametrisierte Upsert-Queries umgestellt, wiederholte Counter-Upserts in `Visit`/`EventRequest` über interne Helper konsolidiert und den `pagestats_data`-Write-Path per Bulk-Upsert reduziert
-   Data-Aggregationen für Browser/Brand/Browsertype/OS/Model/Country/Hour/Weekday zusammengeführt: statt mehrerer Einzelabfragen je Klasse werden die Typen zentral über eine gemeinsame Query geladen und intern wiederverwendet
-   REDAXO-Härtung ergänzt: direkte `$_SERVER`-Zugriffe in `Visit`/`EventRequest` durch REDAXO-Serverzugriff ersetzt (u. a. Client-Hints und `HTTP_VIA`)
-   Wartungs- und Analyseballast reduziert: veraltete `psalm.xml` entfernt und README um eine Maintainer-Sektion für `.tools` erweitert

## [3.5.2] - 04.08.2026

-   Strukturübersicht entfernt
-   Google-Kampagnen eigener Schalter
-   UI-Feinschliff im Backend: eckiger Flat-Style ohne Rundungen sowie verbesserte Darkmode-Darstellung im Bereich „Aufrufe nach Wochentagen“

## [3.5.1] - 04.08.2026

-   Darkmode auf der Seite „Seitenaufrufe“ weiter stabilisiert: helle Restflächen in Filter-/Kartenbereichen entfernt und Theme-Variablen inklusive Light/Dark/Auto konsistent nachgezogen
-   Favoriten-Markierung in der Seitenaufrufe-Tabelle von hartem Inline-Style auf CSS-Klasse umgestellt, damit die Hervorhebung in Darkmode korrekt dargestellt wird
-   Wartungs-Cronjob gegen Lock-Konflikte gehärtet: erweiterter Retry bei SQL-Locks (1205/1213), betroffene Lösch-Batches werden bei andauerndem Lock nicht mehr als harter Fehler abgebrochen, sondern als vertagt behandelt
-   Neuer Cronjob-Parameter „Tracking während Wartung pausieren“ ergänzt (standardmäßig aktiv) inklusive sicherem Restore des vorherigen Runtime-Status nach dem Lauf
-   Default für neue Installationen angepasst: Es werden standardmäßig nur noch 200er-Aufrufe erfasst (`statistics_rec_onlyok = true`)
-   PHP-8.3-Fatal im Zusammenspiel mehrerer AddOn-Vendors behoben (CacheItem-/PSR-Signaturkonflikt): DeviceDetector-Cachepfad in `Visit` und `EventRequest` auf internen `StaticCache` umgestellt
-   Vendor-Abhängigkeit bereinigt: direkte Abhängigkeit `symfony/cache` aus dem AddOn entfernt, um erneute Signaturkonflikte durch gemischte PSR-Cache-Versionen zu vermeiden

## [3.5.0] - 04.08.2026

-   Neue Unterseite „Reports“ ergänzt: Bei installiertem AddOn `pdfout` lassen sich Wochen-, Monats- und Jahresberichte als PDF erzeugen
-   Neuer PDF-Report-Generator mit übersichtlichem Layout und Kernkennzahlen (Aufrufe, Besucher, Seiten pro Sitzung, aktive Tage)
-   Report-Inhalte erweitert um „Top 20 Seiten“ und „Top 20 Referer“ für den gewählten Zeitraum
-   Tabellen-Optimierung in den Einstellungen für große Datenbanken entschärft: `OPTIMIZE TABLE` läuft nun in kleinen Batches mit Fortschrittsanzeige und manuellem Weiterlauf statt als potenziell timeout-anfälliger Komplettlauf
-   Vorhandenen Wartungs-Cronjob erweitert: optionale Tabellen-Optimierung arbeitet jetzt ebenfalls batchweise über mehrere Cron-Läufe mit persistentem Cursor und konfigurierbarer Batchgröße pro Lauf
-   Batch-Verarbeitung auf weitere Wartungsaktionen ausgeweitet (u. a. Hashes, Gesamtdaten, Bots, Referer, Media, API, alte Daten und Störanfragen) inklusive „Nächsten Batch“-Weiterlauf, damit auch große Löschläufe ohne Request-Timeout abgearbeitet werden können
-   Doppelten Confirm-Dialog bei Wartungsaktionen behoben: `data-confirm` wird nicht mehr gleichzeitig auf Formular und Button gesetzt, wodurch der Alert nur noch einmal erscheint
-   Nachgeschärfte Copilot-Reviewpunkte umgesetzt: Timeout-Budget-Flag meldet nur noch bei echtem Restbestand „weitere Batches nötig“, und die Störer-Pattern-Logik wurde als gemeinsame Helper-Quelle für Settings und Wartungs-Cronjob zentralisiert
-   Reinstall/Install für Bestandsdaten robuster gemacht: Deduplizierung für Altbestände vor Primary-Key-Sicherung erweitert und PK-Prüfungen MariaDB-kompatibel überarbeitet
-   Reinstall-Performance verbessert: aufwändige Deduplizierungsroutinen laufen nur noch bei tatsächlichem Bedarf (Schema-/Duplikat-Guards)
-   Lock-Timeouts beim Schreiben von URL-Statusdaten entschärft: Retry-Mechanismus bei MySQL-Lockkonflikten (u. a. Fehlercode 1205/1213), damit Frontend-Requests nicht durch Statistikschreibzugriffe abbrechen
-   Statistik-Erfassung kann jetzt explizit pausiert werden: neuer manueller Schalter in den Einstellungen sowie technischer Runtime-Pause-Mechanismus während Install/Update/Uninstall
-   Reports für Redakteure mit Addon-Recht freigeschaltet und explizit über `statistics[]` abgesichert
-   PDF-Report „Tagesverlauf“ skaliert jetzt auf den tatsächlich angezeigten Ausschnitt, wodurch das Balkenverhältnis der letzten Tage deutlich realistischer dargestellt wird
-   Geo-Download aus Install/Reinstall entfernt: Geo-Datenbank-Aktualisierung erfolgt optional über die Settings-Seite; Install/Reinstall zeigt dazu nun einen Hinweis an

## [3.4.0] - 17.07.2026

-   Tracking-Identität umfassend überarbeitet: stateless Erkennung als Standard, optionaler Session-Modus, gekürzte/anonymisierte IP-Anteile, konfigurierbare Token-Rotation sowie klare Hinweise zu Vor- und Nachteilen
-   Datenschutz und Laufzeitverhalten verbessert: kein Tracking-Cookie im Standardpfad, reduziertes Session-Locking und robustere Deduplizierung von Besuchen/Besuchern
-   Einstellungsseite strukturell modernisiert: getrennte Panels für Tracking, Filter, Darstellung, Media und API mit sauber getrennten Formular-Speicherpfaden
-   UX für Wartung deutlich verbessert: klare Trennung von Löschaktionen und Wartungsaufgaben, neutrales Button-Design für Wartung, präzisere Confirm-Mechanik und bessere Zuordnung über Scope-Hinweise
-   Rücksprung nach Speichern verbessert: Formulare führen wieder zum zuletzt bearbeiteten Panel statt an den Seitenanfang
-   Geo-Bereich erweitert: Statusanzeige der Geo-Datenbank inkl. „geladen/nicht geladen“, letzte Aktualisierung und Dateigröße; Fehlschläge beim Update werden korrekt als Fehler ausgegeben
-   Copilot-Reviewpunkte vollständig adressiert und Threads aufgelöst (u. a. Confirm-Bindung, Panel-Rücksprunglogik, Fehlerunterdrückung entfernt)
-   Codebasis technisch gehärtet: Typbereinigung, robustere Date/Array-Fallbacks, präzisere PHPDoc-Array-Shapes, abgesicherter `gzdecode()`-Pfad beim `ip2geo`-Download sowie sauberere Fragment-/Help-Verarbeitung
-   Vollständiger RexStan-Lauf für `redaxo/src/addons/statistics` im `coreweb`-Container erfolgreich abgeschlossen: keine verbleibenden Fehler
-   Restliche harte UI-Texte in Charts/Filtern/Tabs auf i18n umgestellt (inkl. Tooltip-Formattern, Serienlabels, ARIA-Texten und Kampagnen-Fallbacks), damit Issue #109 vollständig abgeschlossen ist
-   Neue Seite: Strukturübersicht, mit Export nach csv und XLSX sowie grafische Sitemap SVG 

## [3.3.0] - 14.07.2026

-   Neue Unterseite `Google-Kampagnen` zur Auflösung und Gruppierung von Kampagnen-Parametern aus Ziel-URLs (u. a. `gad_campaignid`, `gclid`, `gbraid`, `wbraid`, `utm_*`)
-   Kampagnenansicht redakteursfreundlich erweitert: klare Statusmeldung, KPI-Überblick, Filter „Nur Google Ads (mit Kampagnen-ID)“ und Direktlink zu Google Ads
-   Seitenaufrufe um Favoriten-/Watchlist-Funktion erweitert: URLs können per Stern markiert, farblich hervorgehoben und priorisiert angezeigt werden (inkl. Filter „Nur Favoriten")
-   Seitenaufrufe-Tabelle fachlich erweitert und präzisiert: getrennte Anzeige für Aufrufe (Visits) und Besucher je URL (eindeutige Sessions)
-   Rollout-Verhalten für „Besucher je URL" präzisiert: bei Neuinstallation standardmäßig aktiv, bei bestehenden Installationen zunächst deaktiviert und per Einstellung aktivierbar
-   Tracking-Filter deutlich erweitert: mehr Probe-/Scanner-/CMS-Requests und verdächtige Dateiendungen werden bereits beim Erfassen ignoriert
-   Wartungsbereich ausgebaut und in „Statistikwartung“ überführt, inklusive Anzeige des aktuellen Speicherverbrauchs pro Statistik-Tabelle
-   Neue Wartungswerkzeuge in den Einstellungen: gezielte Rohdaten-Bereinigung nach Aufbewahrungsdauer sowie Tabellen-Optimierung (`OPTIMIZE TABLE`) zur realen Speicherfreigabe
-   Löschroutinen für große Datenbestände gehärtet: Chunked Deletes, Retry bei Lock-Timeouts (SQLSTATE 1205) und Teilbereinigungs-Hinweise
-   Neuer Cronjob-Typ „Statistikwartung (Rohdaten bereinigen)“ für automatische Rohdaten-Retention und optionale Tabellen-Optimierung
-   Statistik-Startseite weiter für große Datenmengen stabilisiert: mehr Lazy-/On-Demand-Laden in Analysebereichen, inklusive „Aufrufe nach Uhrzeiten“
-   ECharts-Einbindung priorisiert: Bei installiertem `echarts` AddOn wird dessen Vendor-Asset bevorzugt (lokaler Fallback bleibt erhalten)
-   Overview verbessert um „Stand jetzt“-Zeitstempel zur klaren Einordnung der aktuellen Tageswerte
-   Besuchsdauer-Auswertung ergänzt um klaren Hinweis zur Interpretation von „0 Sekunden“ (möglicher Absprung oder Einzelaufruf)

## [3.2.4] - 14.07.2026

-   Statistiken-Startseite für Safari und große Datenmengen weiter entlastet: Initialisierung von Charts und DataTables wird stärker gestaffelt und lazy geladen
-   Analysebereich auf konsequentes On-Demand-Laden umgestellt: Verhaltensdaten (Seiten pro Sitzung, Besuchsdauer, Ausstiegsseiten, Länder) werden erst beim Öffnen des jeweiligen Panels geladen
-   Bereich "Aufrufe nach Uhrzeiten" auf On-Demand umgestellt und Darstellung verbreitert, damit der Chart die verfügbare Breite besser nutzt
-   ECharts-Einbindung priorisiert: Wenn das AddOn `echarts` installiert ist, wird dessen Vendor-Asset bevorzugt verwendet (lokaler Fallback bleibt erhalten)
-   Tracking robuster gegen Probe-/Spam-Aufrufe gemacht: typische WordPress- und Scanner-Requests (z. B. `wp-login.php`, `xmlrpc.php`, `apple-touch-icon`) werden bereits beim Erfassen ignoriert
-   Neue Wartungsaktionen in den Einstellungen ergänzt: gezielte Bereinigung von Störanfragen sowie Löschung alter Statistikdaten per Aufbewahrungsdauer (Tage)
-   Analyse-Karten-Textausgabe korrigiert, damit "Geräte & Browser" korrekt gerendert wird (kein sichtbares `&amp;`)

## [3.2.3] - 07.06.2026

-   Dashboard-Overview erweitert um KPI-Leiste für die letzten 7 Tage (Besuche, Besucher, Top-Artikel, Seiten pro Sitzung)
-   Neue klickbare Analyse-Karten auf der Statistik-Startseite, die vorhandene Lazy-Blöcke gezielt laden und anspringen
-   Restliche harte Labels aus `fragments/overview.php` in Sprachdateien ausgelagert

## [3.2.2] - 07.06.2026

-   404/Non-200-Aufrufe werden bei aktivierter Option "Nur 200er Aufrufe erfassen" nicht mehr als Seitenaufrufe gespeichert ([#121](https://github.com/FriendsOfREDAXO/statistics/issues/121))
-   search_it-Indexierungsaufrufe werden wieder zuverlässig ignoriert, auch wenn URL-Parameter aus Statistik-URLs entfernt werden ([#114](https://github.com/FriendsOfREDAXO/statistics/issues/114))

## [3.2.1] - 07.06.2026

-   Datumsbereich bleibt beim Wechsel zwischen Statistik-Tabs erhalten ([#88](https://github.com/FriendsOfREDAXO/statistics/issues/88))
-   Weitere harte UI-Texte in Sprachdateien ausgelagert ([#109](https://github.com/FriendsOfREDAXO/statistics/issues/109))

## [3.2.0] - 29.03.2026

-   Performance improvements with lazy loading

## [3.1.0] - 13.01.2024

-   update vendors
-   remove vectorface/whip as dependency und use symfony shipped alternative
-   add CrawlerDetect library to detect more crawlers
-   check ip adress of visitor for bot

## [3.0.1] - 28.07.2023

-   fix in install.php for ip2geo database download

## [3.0.0] - 28.07.2023

-   the addon now uses namespaces, so you have to check for errors after an update, if methods of this addon are used in your own code
-   charts now have a darkmode
-   plugins are now directly integrated into the main addon
-   renamed "Kampagnen" to "Event" to better express what this feature is doing
-   removed yrewrite as a dependency
-   added statistics for visited pages per session
-   added statistics for visitduration
-   added statistics for visitors country
-   "Seitenaufrufe" now shows http status code
-   filter settings are saved between page navigations
-   added setting to track only pages with a 200 response code which results in much more accurate statistics
-   added a cronjob to automatically remove unused user-hashes. Usefull to comply with the GDPR "Datensparsamkeit" rule
-   some visits dont use the users ip any more and instead use a session token
-   removed integration for dashboard addon

## [2.7.0] - 10.05.2023

-   page requests coming from users logged into the backend can now be discarded in order not to distort the statistics
    [#104](https://github.com/AndiLeni/statistics/issues/104), [#93](https://github.com/AndiLeni/statistics/issues/93)

## [2.6.1] - 07.04.2023

-   update device detector to 6.1.1, also fixes [#103](https://github.com/AndiLeni/statistics/issues/103)
-   re-enable client hints after dd-update

## [2.6.0] - 31.03.2023

-   add file based caching for device detector

## [2.5.0] - 29.03.2023

-   fix deprecation warning in datefilter / [#102](https://github.com/AndiLeni/statistics/issues/102)
-   set domain to "undefined" when rex_yrewrite::getHost() is null / [#101](https://github.com/AndiLeni/statistics/issues/101)
-   entries in pagestats_visitors_per_day were not deleted / [#94](https://github.com/AndiLeni/statistics/issues/94)
-   chart captions are scrollable and should now take less space / [#98](https://github.com/AndiLeni/statistics/issues/98)
-   toolbox for charts can now be hidden (default: hidden). if needed enable manually in addon settings
-   datefilter preset "this year" replaced with "last 12 month" / [#100](https://github.com/AndiLeni/statistics/issues/100)

-   many small ui improvements

-   fix issue with spyc.php

## [2.5.0-alpha2] - 03.03.2023

### Changed

see 2.5

## [2.5.0-alpha1] - 02.03.2023

### Changed

see 2.5

## [2.4.0] - 09.12.2022

### Changed

-   fix release version number

## [2.3.0] - 07.12.2022

### Changed

-   update matomo/device-detector from version 4 to version 6

## [2.2.2] - 17.11.2022

### Changed

-   fixed missing statistics if the "ignore path" feature was used

### Notes:

Dieser Fix behebt leider nur zukünftige Fehler, nicht aber eine existierende fehlerhafte Config.

Dies kann aber schnell mit einem Blick in die Datenbank festgestellt werden:

-   in `rex_config` nach `statistics_ignored_paths` suchen
-   wenn der Text mit `\r\n` oder `\n` beginnt diese Zeichen entfernen.

## [2.2.1] - 01.11.2022

### Added

### Changed

-   code refactored (typetints and return-types added)
-   fix hash updating if requests appear nearly simultaneously / #89
-   fix media plugin

### Removed

### Vendor Updates

### Notes:

Durch das hinzufügen von return-types und return-types wird nun mindestens eine PHP Version >= 7.4 benötigt.

## [2.2.0] - 30.03.2022

### Added

-   charts for visits and visitors are now available in "daily", "monthly" and "yearly" / #86

### Changed

### Removed

### Vendor Updates

## [2.1.0] - 25.03.2022

### Added

-   new heatmap chart for "visits per day"
-   setting "Fasse alle Domains zusammen" which combines all domains into a single chart when detailed distinction is not required

### Changed

-   replaced plotly with echarts (reducing js size from 3,5MB (plotly) to 1MB (echarts))
-   code cleanup, some data generation was moved from the statistic pages to classes to make the templates less bloated
-   filter_date_helper now uses DateTimeImmutable for more logical handling

### Removed

-   plotly js asset
-   setting "statistics_chart_padding_bottom"

### Vendor Updates

-   using echarts 5.3.1

## [2.0.1] - 23.03.2022

### Added

### Changed

-   fix sql query for todays count of visits / #85
-   code cleanups (für das chart der besucher und aufrufe werden nun nicht mehr JS-variablen mit php generiert)

### Removed

### Vendor Updates

## [2.0.0] - 13.03.2022

### Added

### Changed

-   fix chart javascript generation (#81, #83)

### Removed

### Vendor Updates

## [2.0.0-beta.15] - 19.12.2021

### Added

### Changed

-   pages: domain selector now gets domains from db

### Removed

### Vendor Updates

## [2.0.0-beta.14] - 18.12.2021

### Added

### Changed

-   fix js code generation

### Removed

### Vendor Updates

## [2.0.0-beta.13] - 18.12.2021

### Added

-   statistics can be filtered by domain
-   overview for statistics

### Changed

-   sortable lists now sort dates correctly

### Removed

### Vendor Updates

## [2.0.0-beta.12] - 12.12.2021

### Added

### Changed

-   fix update script

### Removed

### Vendor Updates

### Notes

Dieses Update beinhaltet auch die Änderungen aus den Betas von 2.0.0-beta.11 und 2.0.0-beta.10.
In diesesn war allerdings die update.php fehlerhaft, weswegen die Referer-Daten nicht korrekt migriert wurden.

## [2.0.0-beta.11] - 11.12.2021

### Added

-   statistics for visitors per day / #56

### Changed

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.10] - 11.12.2021

### Added

### Changed

-   pagstats-referer table changed / #70

### Removed

### Vendor Updates

### Notes

Dieses Update ändert die tabellenstruktur, es sollte nicht übersprungen werden

## [2.0.0-beta.9] - 09.12.2021

### Added

### Changed

-   fix incorrect presentation of hours / #71
-   date is not any longer inserted in pagstats_data since it is not required there / #69
-   device model is now escaped properly / #74
-   fixes fore tables and general optical improvements / #73

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.8] - 09.11.2021

### Added

### Changed

-   fix css interfering with redaxo's backend css

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.7] - 03.11.2021

### Added

### Changed

-   fix js error for datefilter quickselect
-   datefilter is applied instantly
-   fix more js errors, datatables was throwing an error when table was empty

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.6] - 01.11.2021

### Added

### Changed

-   fix datefilter quickselect, month is now calculated correctly

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.5] - 31.10.2021

### Added

### Changed

-   add quickselects to datefilter fragment

### Removed

-   `stats_pagedetails.php\get_browser()`
-   `stats_pagedetails.php\get_browsertype()`
-   `stats_pagedetails.php\get_os()`

### Vendor Updates

### Notes

## [2.0.0-beta.4] - 21.10.2021

### Added

### Changed

-   escape data before inserted in db / #62
-   fix data deletion / #63
-   fix dashboard integration

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.3] - 14.10.2021

### Added

### Changed

-   escape data during migration

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.2] - 14.10.2021

### Added

### Changed

-   remove unecessary table columns

### Removed

### Vendor Updates

### Notes

## [2.0.0-beta.1] - 14.10.2021

### Added

-   table `pagestats_data`
-   table `pagestats_visits_per_day`
-   table `pagestats_visits_per_url`

### Changed

-   visits are now saved directly in a more separated way to achieve better performance on pages with a hight number of visits per day
-   browserdata is not any more separated by date

### Removed

-   table `pagestats_dump`

### Vendor Updates

### Notes

Auf Website mit vielen Besuchen pro Tag kam es im Backend zu extremen Ladezeiten um die Daten aufzubereiten.
Um dem Vorzubeugen werden Besuche nun in passendere Tabellenstrukturen gespeichert um eine Auswertung zu beschleunigen.

Beim Upgrade werden die Daten aus der Tabelle pagestats_dump ausgewertet und auf die neuen Tabellen verteilt.

> **Hinweis:** Dieser Migrationsvorgang kann je nach Tabellengröße länger dauern, bitte sicherstellen, dass die PHP Laufzeit ausreichend ist.

## [1.0.0-rc.3] - 06.10.2021

### Added

-   add "id" column as primary key to all tables to increase performance

### Changed

### Removed

### Vendor Updates

### Notes

## [1.0.0-rc.2] - 04.10.2021

### Added

-   pagedetails panel headings

### Changed

-   fix for paginations / #57

### Removed

### Vendor Updates

### Notes

## [1.0.0-rc.1] - 30.09.2021

### Added

-   permissions

### Changed

-   change some database fields to "text" type
-   change stats layout
-   change table search to case-insensitive
-   fix date filtering
-   adjust setting names

### Removed

-   `plugins\api\lib\stats_campaign_details.php\get_page_total()`
-   `plugins\media\lib\stats_media_details.php\get_page_total()`

### Vendor Updates

### Notes

## [dev-0.0.3] - 16.09.2021

### Added

-   add setting to optionally ignore url parameters
-   ignore `.css.map` and `.js.map` files for logging

### Changed

### Removed

### Vendor Updates

### Notes

## [dev-0.0.2] - 16.09.2021

### Breaking changes

### Added

### Changed

-   fix integration in dashboard addon
-   fix search input overflow / #50
-   remove dump() on backend page

### Removed

### Vendor Updates

### Notes
