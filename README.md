# B2BClassEdit (plentymarkets Plugin)

Dieses Plugin ändert bei neu registrierten Kunden automatisch die Kundenklasse, wenn:

1. eine **VAT / USt-IdNr.** vorhanden ist, und
2. die E-Mail **nicht** auf die konfigurierte eBay-Domain endet (Standard: `@members.ebay.com`).

## Wichtiger Fix für fehlende Backend-Konfiguration

In plentymarkets wird die Plugin-Konfiguration aus der Datei **`config.json` im Plugin-Root** geladen.

- Diese Datei war bisher nicht im Root vorhanden.
- Jetzt ist die Konfiguration unter `config.json` hinterlegt (mit `tab` + `label`, damit die Felder im Backend klar beschriftet sind).
- Zusätzlich bleibt `config/config.json` als Kopie enthalten, damit bestehende Branches/Setups nicht brechen.

## Konfiguration im Backend

Pfad:

1. **Plugins » Plugin-Sets**
2. Plugin-Set öffnen
3. **B2BClassEdit » Konfiguration**
4. Werte speichern
5. Plugin-Set **erneut bereitstellen**

Verfügbare Felder:

- **Kundenklasse Quelle (von)** = `sourceClassId` (Standard `4`)
- **Kundenklasse Ziel (nach)** = `targetClassId` (Standard `5`)
- **eBay E-Mail-Domain (Ausschluss)** = `ebayEmailDomain` (Standard `@members.ebay.com`)

## Verhalten

Das Plugin hört auf `AfterContactCreate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber` oder in den Kontaktoptionen vorhanden
- E-Mail endet **nicht** auf `ebayEmailDomain`
- Es wird nur gewechselt, wenn die aktuelle Klasse exakt der konfigurierten Quellklasse entspricht (`sourceClassId`).

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.

## Hinweis zum letzten Funktionsfix

Falls die Klasse trotz USt-IdNr. nicht gewechselt wurde, enthält dieses Plugin jetzt zwei wichtige Korrekturen:

- Das Klassen-Update nutzt die korrekte Signatur `updateContact(array $data, int $contactId)`.
- Die USt-IdNr.-Prüfung liest VAT nicht nur aus dem geladenen Kontakt, sondern zusätzlich auch aus dem `AfterContactCreate`-Event (inkl. Optionen), falls Werte beim ersten Read noch nicht vollständig im Kontaktobjekt stehen.
- Zusätzlich wird rekursiv in verschachtelten Event-/Kontakt-Payloads nach VAT/USt-Feldern gesucht (z. B. Company/Address-Strukturen), falls die USt-IdNr. nicht direkt in `contact.vatNumber` liegt.
- Event- und Kontaktwerte werden kompatibel ohne verbotene Funktionsaufrufe gelesen (Array + Objekt-Cast mit Fallback auf Property-Suffix), damit Allowed-Calls-Checks im Build nicht blockieren.
