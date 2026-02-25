# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf die konfigurierte eBay-Domain endet (Standard: `@members.ebay.com`).

## Konfiguration

Wenn in plentymarkets bisher „keine Konfigurationsfelder verfügbar“ angezeigt wurde, nutzt dieses Plugin jetzt ein **minimal-kompatibles Config-Schema** mit 3 Textfeldern:

- `sourceClassId` (Standard `4`)
- `targetClassId` (Standard `5`)
- `ebayEmailDomain` (Standard `@members.ebay.com`)

Pfad im Backend:

1. **Plugins » Plugin-Set öffnen**
2. Plugin **B2BClassEdit**
3. **Konfiguration**
4. Werte speichern und Plugin-Set erneut bereitstellen

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `ebayEmailDomain`

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.
