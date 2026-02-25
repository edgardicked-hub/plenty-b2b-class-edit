# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf die konfigurierte eBay-Domain endet (Standard: `@members.ebay.com`).

## Wichtiger Fix für fehlende Backend-Konfiguration

In plentymarkets wird die Plugin-Konfiguration aus der Datei **`config.json` im Plugin-Root** geladen.

- Diese Datei war bisher nicht im Root vorhanden.
- Jetzt ist die Konfiguration unter `config.json` hinterlegt (inkl. korrekter `key`-Felder).
- Zusätzlich bleibt `config/config.json` als Kopie enthalten, damit bestehende Branches/Setups nicht brechen.

## Konfiguration im Backend

Pfad:

1. **Plugins » Plugin-Sets**
2. Plugin-Set öffnen
3. **B2BClassEdit » Konfiguration**
4. Werte speichern
5. Plugin-Set **erneut bereitstellen**

Verfügbare Felder:

- `sourceClassId` (Standard `4`)
- `targetClassId` (Standard `5`)
- `ebayEmailDomain` (Standard `@members.ebay.com`)

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `ebayEmailDomain`

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.
