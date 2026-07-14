# Changelog

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
