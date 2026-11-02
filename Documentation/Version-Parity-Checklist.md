# v11/v14-Abgleich

Diese Liste wird bei Änderungen an gemeinsam gepflegten Funktionen verwendet.

## Cards

- FlexForm-Feld, Standardwert und `displayCond` in beiden Versionen abgleichen.
- Fluid-Template und `CardImage`-Partial auf identische Bildmodi prüfen.
- CSS auf die jeweilige Markup-Struktur begrenzen: v11 verwendet `.card-link`, v14 `.rescue-card-item`.
- Neue sichtbare Texte in beiden `locallang_db.xlf` ergänzen.

## Einsatz-Detailansicht

- Bootstrap- und Foundation-Ausgabe kontrollieren.
- Hover, Fokus und `prefers-reduced-motion` im Browser prüfen.
- Detail-Karten der eingesetzten Feuerwehren auf roten linken Rand prüfen.

## Prüfung vor Übergabe

- FlexForm- und XLF-Dateien als XML validieren.
- JavaScript mit `node --check` prüfen, sofern Node verfügbar ist.
- TYPO3-Systemcache nach FlexForm-, TypoScript- oder Sprachänderungen leeren.
