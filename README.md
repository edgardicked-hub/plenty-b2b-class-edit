# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse von **4** auf **5**, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf `@members.ebay.com` endet.

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === 4`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `@members.ebay.com`

Treffen alle Bedingungen zu, wird `classId` auf `5` gesetzt.

## Hinweise

- eBay-Kunden werden über das E-Mail-Muster `@members.ebay.com` ausgeschlossen.
- Die Ermittlung der E-Mail erfolgt über `contact.email`, `contact.privateEmail`, Event-Daten und als Fallback über Kontaktoptionen.
