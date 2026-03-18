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
- **API Base-URL** = `apiBaseUrl` (optional, z. B. `https://deinshop.tld`)
- **E-Mailvorlage ID für Freischaltung** = `emailTemplateId` (optional, Plenty-Mailvorlagen-ID; `0` = kein Versand)

## Verhalten

Das Plugin hört auf `AfterContactCreate`, `AfterContactUpdate` und zusätzlich `AfterAccountAuthentication` und prüft dann:

- `classId === sourceClassId`
- VAT/USt-IdNr. wird bevorzugt aus `accounts.taxIdNumber`, dann `addresses.taxIdNumber`, dann `addresses.options` mit `typeId = 1` gelesen
- E-Mail endet **nicht** auf `ebayEmailDomain`

Treffen alle Bedingungen zu, wird `classId` von `sourceClassId` auf `targetClassId` gesetzt.

Wenn zusätzlich `emailTemplateId > 0` konfiguriert ist, bereitet das Plugin nach erfolgreicher Klassenänderung eine Plenty-Mailvorlage für die ermittelte Kontakt-E-Mail vor. Der eigentliche Versand wird dann auf das Plenty-Event `PluginSendMail` verschoben, damit der reguläre Registrierungs-Mailflow nicht blockiert oder beeinflusst wird. Dafür werden an `EmailTemplatesSendServiceContract::sendEmail()` die Kontakt-bezogenen Daten `contactId`, `receiverEmail`, `lang` und – falls vorhanden – `plentyId` übergeben; zusätzlich versucht das Plugin die Plenty-Empfängerstruktur vorab per `getRecipient()` aufzulösen und als `receivers` mitzugeben.

## Hinweis zum letzten Funktionsfix

Falls die Klasse trotz USt-IdNr. nicht gewechselt wurde, enthält dieses Plugin jetzt zwei wichtige Korrekturen:

- Das Klassen-Update nutzt die korrekte Signatur `updateContact(array $data, int $contactId)`.
- Die USt-IdNr.-Prüfung arbeitet primär auf dem Event-Kontakt (`getContact()` bzw. `getAccountContact()`) und lädt den Kontakt zusätzlich mit `accounts`, `addresses`, `addresses.options` und `options` nach, damit VAT-Felder zuverlässig verfügbar sind.
- Zusätzlich wird rekursiv in verschachtelten Event-/Kontakt-Payloads nach VAT/USt-Feldern gesucht (z. B. Company/Address-Strukturen), falls die USt-IdNr. nicht direkt in `contact.vatNumber` liegt.
- Der Listener versucht den Kontakt zusätzlich mit Relations (`accounts`, `addresses`) nachzuladen, damit USt-Daten aus Firmen-/Adresskontexten zuverlässig erkannt werden.
- Collections aus Plenty (`accounts`, `addresses`, `options`) werden jetzt als iterierbare Daten behandelt (nicht nur als Arrays), damit VAT-Erkennung in realen B2BShop-Flows greift.
- Beim Nachladen des Kontakts werden die Relations `accounts`, `addresses` und `options` angefordert.
- Für B2BShop-/Custom-Registrierungen wird die Kontakt-ID notfalls rekursiv aus dem Event-Payload gelesen, wenn sie nicht direkt als `contactId` verfügbar ist.
- Model-Werte werden zuerst über `toArray()` normalisiert und iterierbare Collections werden sauber als Arrays gelesen.

## Logging / Fehlersuche

Das Plugin schreibt jetzt Diagnose-Logs in Plenty (Logger-Kontext `CustomerRegistrationListener::handle`), u. a. für:

- Event-Klasse (Create/Update)
- Contact-ID konnte nicht ermittelt werden
- Kein VAT/USt-Wert gefunden
- eBay-Domain erkannt (Skip)
- Source/Zielklasse fehlerhaft konfiguriert
- Klasse passt nicht zur Quellklasse (Skip)
- Gefundene VAT (maskiert)
- Erfolgreiche Klassenänderung
- Versand der Freischaltungs-Mail erfolgreich / fehlgeschlagen / übersprungen

So kann man im Plenty-Log schnell sehen, an welcher Bedingung der Wechsel stoppt.

## Manuelles Triggern per REST (für App-Integration)

Falls euer Registrierungsflow (z. B. PlentyLions B2BShop) die Kontakt-Events anders verarbeitet, könnt ihr den Klassenwechsel auch manuell per REST auslösen:

- **Route:** `POST /rest/b2b-class-edit/switch/{contactId}`
- Beispiel: `POST /rest/b2b-class-edit/switch/12345`

Die Route ruft intern dieselbe Listener-Logik auf und schreibt weiterhin die Diagnose-Logs. So kann eure App den Wechsel gezielt nach erfolgreicher Registrierung anstoßen.

Warum bisher keine URL im Plugin nötig war:
- Die REST-Route ist **intern** im Plenty-System registriert (`/rest/b2b-class-edit/switch/{contactId}`).
- Viele Plugins brauchen eine URL, weil sie als API-**Client** zu einem externen Dienst sprechen.

Damit es für euch trotzdem klar konfigurierbar ist, gibt es jetzt optional `apiBaseUrl`.
Damit könnt ihr in der Doku/Integration eine vollständige Ziel-URL bilden:
- `{apiBaseUrl}/rest/b2b-class-edit/switch/{contactId}`

Wenn `apiUsername`/`apiPassword` in der Plugin-Konfiguration gesetzt sind, müssen diese Werte im POST-Request mitgesendet werden:

- `apiUser`
- `apiPassword`

## Stabilitäts-Fixes (Registrierung hängt nicht mehr)

- `handle()` läuft jetzt vollständig in `try/catch`, damit Fehler den Registrierungsprozess nicht blockieren.
- Reentrancy-Guard pro `contactId` verhindert Event-Loop-Rekursion bei `updateContact()` im Event-Kontext.
- Kontakt wird nur einmal geladen (direkt mit Relations `accounts`, `addresses`, `addresses.options`, `options`).
- `updateContact()` ist separat abgesichert; bei Fehlern wird geloggt und sauber beendet.
