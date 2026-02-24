# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf die konfigurierte eBay-Domain endet (Standard: `@members.ebay.com`).

## Was wurde gefixt?

- **Backend-Konfiguration sichtbar**: `config/config.json` ist jetzt im plentymarkets-Config-Format aufgebaut.
- **Stabilere Kontaktverarbeitung**: Der Listener verarbeitet Kontaktdaten jetzt sowohl als Array als auch als Objekt. Dadurch greift die Logik auch dann, wenn `findContactById()` ein Objekt liefert.

## Backend-Konfiguration

Im Plugin-Backend kannst du folgende Werte setzen:

- `sourceClassId`: Von welcher Kundenklasse geändert wird (Standard `4`)
- `targetClassId`: In welche Kundenklasse geändert wird (Standard `5`)
- `ebayEmailDomain`: Domain-Muster für eBay-Kunden (Standard `@members.ebay.com`)

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `ebayEmailDomain`

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.

## Wichtig für Deployment

Ja: Wenn du die Änderungen bisher nur in einem Feature-Branch hast, musst du den Stand in den Branch bringen, aus dem dein Plugin gebaut/deployed wird (oft `main` oder dein Release-Branch), **dann Plugin neu bauen/ausrollen und in plentymarkets Plugin-Set erneut bereitstellen**.
