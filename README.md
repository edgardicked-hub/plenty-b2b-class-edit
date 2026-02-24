# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf die konfigurierte eBay-Domain endet (Standard: `@members.ebay.com`).

## Backend-Konfiguration

Im Plugin-Backend kannst du folgende Werte setzen:

- `sourceClassId`: Von welcher Kundenklasse geändert wird (Standard `4`)
- `targetClassId`: In welche Kundenklasse geändert wird (Standard `5`)
- `ebayEmailDomain`: Domain-Muster für eBay-Kunden (Standard `@members.ebay.com`)

Aktueller Konfigurations-Default (`config/config.json`):

```json
{
  "sourceClassId": 4,
  "targetClassId": 5,
  "ebayEmailDomain": "@members.ebay.com"
}
```

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `ebayEmailDomain`

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.

## Hinweis zu Dropdown-Kundenklassen

Die eigentliche Umstellung von Quell-/Zielklasse ist jetzt konfigurierbar. Wenn du zusätzlich ein echtes dynamisches Dropdown mit allen Kundenklassen im Backend möchtest, brauchen wir als nächsten Schritt ein kleines eigenes Backend-UI-Modul (weil das Standard-Config-JSON in der Regel nur statische Eingabefelder abbildet).
