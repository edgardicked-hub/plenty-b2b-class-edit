# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse von **4** auf **5**, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. der Kunde **nicht** von einem gesperrten Referrer (z. B. eBay) stammt.

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === 4`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- `referrerId` ist **nicht** in `blockedReferrerIds`

Treffen alle Bedingungen zu, wird `classId` auf `5` gesetzt.

## Konfiguration

In `config/config.json` kann die Liste gesperrter Referrer gepflegt werden:

```json
{
  "blockedReferrerIds": [2, 11]
}
```

> Hinweis: Bitte die Referrer-IDs auf eure plentymarkets-Umgebung abstimmen (eBay-Kanäle können je nach Setup variieren).
