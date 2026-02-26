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
- **API Benutzername** = `apiUsername` (optional)
- **API Passwort** = `apiPassword` (optional)

## Verhalten

Das Plugin hört auf `AfterContactUpdate` und prüft dann:

- `classId === sourceClassId`
- VAT ist in `vatNumber`/`taxIdNumber` vorhanden **oder** in verknüpften `accounts` / `addresses` (inkl. Address-Option `typeId = 1`)
- E-Mail endet **nicht** auf `ebayEmailDomain`
- Es wird nur gewechselt, wenn die aktuelle Klasse exakt der konfigurierten Quellklasse entspricht (`sourceClassId`).
- Der Wechsel läuft im Update-Event, damit die Registrierung selbst nicht blockiert oder mit E-Mail-Konflikten gestört wird.

Treffen alle Bedingungen zu, wird `classId` auf `targetClassId` gesetzt.

## Hinweis zum letzten Funktionsfix

Falls die Klasse trotz USt-IdNr. nicht gewechselt wurde, enthält dieses Plugin jetzt zwei wichtige Korrekturen:

- Das Klassen-Update nutzt die korrekte Signatur `updateContact(array $data, int $contactId)`.
- Die USt-IdNr.-Prüfung liest VAT nicht nur aus dem geladenen Kontakt, sondern zusätzlich auch aus dem `AfterContactUpdate`-Event (inkl. Optionen), falls Werte beim ersten Read noch nicht vollständig im Kontaktobjekt stehen.
- Zusätzlich wird rekursiv in verschachtelten Event-/Kontakt-Payloads nach VAT/USt-Feldern gesucht (z. B. Company/Address-Strukturen), falls die USt-IdNr. nicht direkt in `contact.vatNumber` liegt.
- Der Listener versucht den Kontakt zusätzlich mit Relations (`accounts`, `addresses`) nachzuladen, damit USt-Daten aus Firmen-/Adresskontexten zuverlässig erkannt werden.
- Collections aus Plenty (`accounts`, `addresses`, `options`) werden jetzt als iterierbare Daten behandelt (nicht nur als Arrays), damit VAT-Erkennung in realen B2BShop-Flows greift.
- Beim Nachladen des Kontakts werden die Relations `accounts`, `addresses` und `options` angefordert.
- Für B2BShop-/Custom-Registrierungen wird die Kontakt-ID notfalls rekursiv aus dem Event-Payload gelesen, wenn sie nicht direkt als `contactId` verfügbar ist.
- Event- und Kontaktwerte werden kompatibel ohne verbotene Funktionsaufrufe gelesen (Array + Objekt-Cast mit Fallback auf Property-Suffix), damit Allowed-Calls-Checks im Build nicht blockieren.

## Logging / Fehlersuche

Das Plugin schreibt jetzt Diagnose-Logs in Plenty (Logger-Kontext `CustomerRegistrationListener::handle`), u. a. für:

- Contact-ID konnte nicht ermittelt werden
- Kein VAT/USt-Wert gefunden
- eBay-Domain erkannt (Skip)
- Source/Zielklasse fehlerhaft konfiguriert
- Klasse passt nicht zur Quellklasse (Skip)
- Erfolgreiche Klassenänderung

So kann man im Plenty-Log schnell sehen, an welcher Bedingung der Wechsel stoppt.

## Manuelles Triggern per REST (für App-Integration)

Falls euer Registrierungsflow (z. B. PlentyLions B2BShop) die Kontakt-Events anders verarbeitet, könnt ihr den Klassenwechsel auch manuell per REST auslösen:

- **Route:** `POST /rest/b2b-class-edit/switch/{contactId}`
- Beispiel: `POST /rest/b2b-class-edit/switch/12345`

Die Route ruft intern dieselbe Listener-Logik auf und schreibt weiterhin die Diagnose-Logs. So kann eure App den Wechsel gezielt nach erfolgreicher Registrierung anstoßen.

Wenn `apiUsername`/`apiPassword` in der Plugin-Konfiguration gesetzt sind, müssen diese Werte im POST-Request mitgesendet werden:

- `apiUser`
- `apiPassword`
