# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf die konfigurierte eBay-Domain endet (Standard: `@members.ebay.com`).

## Wo finde ich die Plugin-Konfiguration?

Die Einstellungen findest du im plentymarkets Backend in deinem **Plugin-Set**:

1. **Plugins » Plugin-Set öffnen**
2. Plugin **B2BClassEdit** auswählen
3. Bereich **Konfiguration** öffnen
4. Werte speichern und Plugin-Set erneut bereitstellen

## Verfügbare Felder

- `sourceClassId` (Textfeld): Von welcher Kundenklasse geändert wird (Standard `4`)
- `targetClassId` (Textfeld): In welche Kundenklasse geändert wird (Standard `5`)
- `ebayEmailDomain` (Textfeld): Domain-Muster für eBay-Kunden (Standard `@members.ebay.com`)

> Wichtig: plentymarkets liest die Werte als Text ein. IDs bitte numerisch eintragen (z. B. `4` und `5`).

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `ebayEmailDomain`

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.

## Technischer Hinweis

Die Konfigurationswerte werden robust ausgelesen (mit und ohne Namespace-Präfix), damit die Werte in unterschiedlichen Plugin-Set-Kontexten korrekt greifen.
