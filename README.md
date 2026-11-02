# rescue_reports – TYPO3-Extension für Feuerwehr-Einsatzberichte

Detaillierte Einsatzberichte für Feuerwehren und BOS. Die Extension stellt Frontend-Plugins für Einsatzlisten, Statistiken, ein Sidebar-Widget und einen RSS-Feed bereit.

**Extension-Key:** `rescue_reports`  
**TYPO3:** 11.5+  
**PHP:** 7.4 – 8.2  
**Lizenz:** GPL-2.0-or-later

---

## Installation & Ersteinrichtung

Nach der Installation wird empfohlen, den **Upgrade Wizard** in der TYPO3-Backend-Systemwartung auszuführen. Dieser richtet folgendes ein:

- Einen Speicherordner für die Extension-Datensätze
- Gängige deutsche BOS-Organisationen (Behörden und Organisationen mit Sicherheitsaufgaben)
- Typische Fahrzeugtypen

Der Wizard ist optional; alle Datensätze lassen sich auch manuell anlegen.

---

## Plugins

### 1. Einsatzliste (`tx_rescuereports_eventlist`)

Hauptansicht mit Listenansicht und Detailansicht der Einsätze.

**Aktionen:** `list`, `show`

#### FlexForm-Einstellungen

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Design** | Template-Variante: Bootstrap oder Foundation | Bootstrap |
| **Standard-Ortsfeuerwehr** | Vorausgewählte Station beim Seitenaufruf | – (keine) |
| **Auswahl der Ortsfeuerwehr im Frontend anzeigen** | Stationsfilter sichtbar schalten | Ja |
| **Anzahl angezeigter Einsätze** | 0 = unbegrenzt | 0 |
| **Datum von / bis** | Feste Datumsbereichseinschränkung | – |
| **Suche aktivieren** | Volltextsuche (Titel, Beschreibung, Ort, Typ, Nummer) | Nein |
| **Seite für Einsatz-Detailansicht** | Zielseite für „Zum Einsatz"-Link | – |
| **Datumsfilter im Frontend anzeigen** | Datumseingabe für Besucher | Nein |
| **Jahresfilter anzeigen** | Jahresauswahl im Frontend | Ja |
| **Jahresfilter: Standardauswahl beim ersten Aufruf** | „Alle Jahre" oder „Aktuelles Jahr" | Alle Jahre |
| **Statistik anzeigen** | Tortendiagramm-Statistik einblenden | Nein |
| **Statistik: Anzahl angezeigter Jahre** | 0 = alle Jahre | 0 |
| **Position der Statistik** | Oberhalb oder unterhalb der Einsatzliste | Unterhalb |
| **Kartenansicht anzeigen** | OpenStreetMap-Karte einblenden | Nein |
| **Kartenhöhe in Pixeln** | | 400 |
| **Standard-Zoomstufe (1–19)** | | 11 |
| **Position der Karte** | Oberhalb oder unterhalb der Einsatzliste | Unterhalb |

#### Jahresfilter-Verhalten

- Ist „Alle Jahre" gewählt, werden Einsätze jahresweise gruppiert mit Sprungmarken-Navigation und optionaler Inline-Statistik je Jahresgruppe.
- Ist „Aktuelles Jahr" gewählt, wird die Liste beim ersten Aufruf automatisch auf das laufende Jahr gefiltert; der Besucher kann per Jahresauswahl wechseln.
- **„Jahresfilter anzeigen"** steuert nur die Sichtbarkeit der Jahresauswahl im Frontend – der Datenbereich und die Gruppierung sind davon unabhängig.

#### Statistik

Die Statistik basiert auf dem Tortendiagramm-Partial (`Statistics/PieChart.html`) und wird sowohl als Block (einzelnes Jahr) als auch inline über jeder Jahresgruppe (alle Jahre) gerendert. Beim Hover über ein Tortenstück erscheint ein CSS-Tooltip mit Kategoriename, zugehörigen Einsatzarten und Anzahl/Prozentsatz. Die Position (ober-/unterhalb) wird über `statisticsPosition` gesteuert.

---

### 2. Statistik (`tx_rescuereports_statistics`)

Eigenständige Statistikseite mit Jahres- und Monatsdiagrammen.

**Aktion:** `statistics`

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Station** | Filterung auf eine Ortsfeuerwehr | – (alle) |
| **Stationsauswahl im Frontend anzeigen** | | Ja |
| **Anzahl angezeigter Jahre** | 0 = alle | 0 |
| **Monatsvergleich anzeigen** | Monatliches Balkendiagramm | Ja |

---

### 3. Sidebar-Widget (`tx_rescuereports_sidebar`)

Kompaktes Widget für die Seitenleiste – zeigt die letzten N Einsätze.

**Aktion:** `list` (gecacht)

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Widget-Überschrift** | Freitext | „Letzte Einsätze" |
| **Standard-Ortsfeuerwehr** | 0 = alle | – (alle) |
| **Anzahl angezeigter Einsätze** | | 5 |
| **Design** | Bootstrap oder Foundation | Bootstrap |
| **Seite für Einsatz-Detailansicht** | | – |
| **Seite der vollständigen Einsatzliste** | Ziel des „Alle Einsätze"-Links | – |

---

### 4. RSS-Feed (`tx_rescuereports_rss`)

RSS 2.0-Feed der neuesten Einsätze.

**Aktion:** `rss` (gecacht)  
**Seitentyp:** `typeNum = 100`

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Feed-Titel** | Freitext | – |
| **Station** | Filterung auf eine Ortsfeuerwehr | – (alle) |
| **Anzahl der Einträge** | | 20 |
| **Seite für Einsatz-Detailansicht** | | – |

---

## Template-Varianten

| Wert | Beschreibung | Verwendung |
|---|---|---|
| `bootstrap` | Bootstrap 5 | Einsatzliste, Detailansicht |
| `foundation` | Foundation | Einsatzliste, Detailansicht |
| `sidebar-bootstrap` | Bootstrap 5 | Sidebar-Widget |
| `sidebar-foundation` | Foundation | Sidebar-Widget |

> Bestehende Plugin-Instanzen mit alten Werten (`standard`, `newdesign`, `sidebar`, `newdesignsidebar`) werden automatisch auf die entsprechenden neuen Werte gemappt.

---

## Bildergalerie & Lightbox

### Bootstrap-Template

Die Detailansicht verwendet einen **Bootstrap-Carousel** als Vorschau-Slider. Ein Klick auf ein Bild öffnet die Vollansicht über **GLightbox** (kein jQuery, kein Bootstrap-Modal). Navigation per Mausklick, Tastatur und Touch/Swipe.

GLightbox wird automatisch über jsDelivr-CDN geladen:
```
https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css
https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js
```

Für Produktionsumgebungen ohne CDN-Zugriff können die Dateien lokal abgelegt und der Pfad in `EventController::showAction()` angepasst werden.

### Foundation-Template

Die Detailansicht verwendet **Owl Carousel** (jQuery-basiert) als Slider. Ein Klick auf ein Bild öffnet GLightbox (identische Lightbox wie Bootstrap-Template). Voraussetzung: jQuery und Owl Carousel müssen im TYPO3-Seitenlayout geladen sein.

---

## Karte (OpenStreetMap)

Erfordert Koordinaten (`lat`/`lng`) im Einsatzdatensatz. Die Karte wird via **Leaflet.js 1.9.4** (CDN) eingebunden. Das Partial `Map/EventList.html` zeigt alle sichtbaren Einsätze als Marker.

Position (ober-/unterhalb der Liste) und Höhe/Zoomstufe sind über FlexForm konfigurierbar.

---

## Domänenmodell (Überblick)

| Tabelle | Beschreibung |
|---|---|
| `tx_rescuereports_domain_model_event` | Einsätze (Titel, Beschreibung, Ort, Koordinaten, Datum, Bilder, Fahrzeuge) |
| `tx_rescuereports_domain_model_station` | Ortsfeuerwehren (Name, Kürzel, Fahrzeuge) |
| `tx_rescuereports_domain_model_brigade` | Feuerwehren / BOS-Organisationen |
| `tx_rescuereports_domain_model_car` | Fahrzeugtypen (Name, Organisation) |
| `tx_rescuereports_domain_model_vehicle` | Fahrzeuge (Name, Bild, Verlinkung) |
| `tx_rescuereports_domain_model_category` | Einsatzkategorien (Titel, Farbe für Statistik) |
| `tx_rescuereports_domain_model_type` | Einsatzarten (Titel, Kategorie) |
| `tx_rescuereports_domain_model_snippet` | Textbausteine für das Backend |

---

## Volltextsuche

Die Suche durchsucht folgende Felder: Titel, Beschreibung, Einsatzort, Einsatzart-Bezeichnung, Einsatznummer. Optional kombinierbar mit Stationsfilter und Datumsbereich.

---

## Technische Hinweise

- **Einsatznummern** werden dynamisch per Station und Jahr berechnet (laufende Nummer `001`, `002`, …) und können stationsspezifische Präfixe tragen.
- **Slug-Routing** für sprechende URLs der Detailansicht ist vorbereitet.
- **RSS-Feed** wird über einen eigenen `typeNum = 100` ausgeliefert; Content-Type `application/rss+xml`.
- **TypoScript-Einbindung** über `Configuration/TypoScript/setup.typoscript`.
