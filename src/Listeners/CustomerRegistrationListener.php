<?php

namespace B2BClassEdit\Listeners;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Plugin\ConfigRepository;

class CustomerRegistrationListener
{
    const DEFAULT_SOURCE_CLASS_ID = 4;
    const DEFAULT_TARGET_CLASS_ID = 5;

    /** @var ContactRepositoryContract */
    private $contactRepository;

    /** @var ConfigRepository */
    private $config;

    public function __construct(ContactRepositoryContract $contactRepository, ConfigRepository $config)
    {
        $this->contactRepository = $contactRepository;
        $this->config = $config;
    }

    public function handle($event)
    {
        $contactId = $this->extractContactId($event);

        if ($contactId === null) {
            return;
        }

        $contact = $this->contactRepository->findContactById($contactId);

        if (!$this->isValidContactPayload($contact)) {
            return;
        }

        if (!$this->hasVatTaxId($contact, $event)) {
            return;
        }

        if ($this->isEbayCustomer($contact, $event)) {
            return;
        }

        $sourceClassId = $this->getSourceClassId();
        $targetClassId = $this->getTargetClassId();

        if ($sourceClassId === $targetClassId) {
            return;
        }

        $currentClassId = (int) $this->readValue($contact, 'classId', 0);

        if ($currentClassId === $targetClassId) {
            return;
        }

        if ($currentClassId !== $sourceClassId) {
            return;
        }

        // plentymarkets ContactRepositoryContract erwartet: updateContact(array $data, int $contactId)
        $this->contactRepository->updateContact([
            'classId' => $targetClassId,
        ], $contactId);
    }

    private function getSourceClassId()
    {
        return (int) $this->readConfigValue('sourceClassId', self::DEFAULT_SOURCE_CLASS_ID);
    }

    private function getTargetClassId()
    {
        return (int) $this->readConfigValue('targetClassId', self::DEFAULT_TARGET_CLASS_ID);
    }


    private function readConfigValue($key, $default = null)
    {
        $value = $this->config->get('B2BClassEdit.' . $key, null);

        if ($value === null || $value === '') {
            $value = $this->config->get($key, $default);
        }

        return $value;
    }

    private function extractContactId($event)
    {
        $eventContactId = $this->readValue($event, 'contactId');

        if ($eventContactId) {
            return (int) $eventContactId;
        }

        $eventContact = $this->readValue($event, 'contact');

        if ($this->isValidContactPayload($eventContact)) {
            $contactId = $this->readValue($eventContact, 'id');

            if ($contactId) {
                return (int) $contactId;
            }
        }

        return null;
    }

    private function hasVatTaxId($contact, $event)
    {
        if ($this->hasVatTaxIdInContact($contact)) {
            return true;
        }

        $eventContact = $this->readValue($event, 'contact');

        if ($this->isValidContactPayload($eventContact) && $this->hasVatTaxIdInContact($eventContact)) {
            return true;
        }

        // Fallback: manche Registrierungsstrecken liefern die USt-IdNr. in verschachtelten
        // Company/Address-Strukturen. Darum zusätzlich rekursiv nach VAT-Feldern suchen.
        if ($this->hasVatTaxIdInPayload($contact, 0) || $this->hasVatTaxIdInPayload($eventContact, 0) || $this->hasVatTaxIdInPayload($event, 0)) {
            return true;
        }

        return false;
    }

    private function hasVatTaxIdInContact($contact)
    {
        $vatNumber = trim((string) $this->readValue($contact, 'vatNumber', ''));

        if ($vatNumber !== '') {
            return true;
        }

        $taxIdNumber = trim((string) $this->readValue($contact, 'taxIdNumber', ''));

        if ($taxIdNumber !== '') {
            return true;
        }

        $options = $this->readValue($contact, 'options');

        if (!is_array($options)) {
            return false;
        }

        foreach ($options as $option) {
            $value = trim((string) $this->readValue($option, 'value', ''));

            if ($value === '') {
                continue;
            }

            $typeId = (int) $this->readValue($option, 'typeId', -1);
            $subTypeId = (int) $this->readValue($option, 'subTypeId', -1);
            $type = strtolower(trim((string) $this->readValue($option, 'type', '')));
            $subType = strtolower(trim((string) $this->readValue($option, 'subType', '')));

            if ($typeId === 6 || $subTypeId === 6) {
                return true;
            }

            if ($this->containsAny($type, array('vat', 'ust', 'tax')) || $this->containsAny($subType, array('vat', 'ust', 'tax'))) {
                return true;
            }
        }

        return false;
    }

    private function hasVatTaxIdInPayload($payload, $depth)
    {
        if ($depth > 6 || $payload === null) {
            return false;
        }

        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                if ($this->isVatKeyWithValue($key, $value)) {
                    return true;
                }

                if (is_array($value) || is_object($value)) {
                    if ($this->hasVatTaxIdInPayload($value, $depth + 1)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if (is_object($payload)) {
            return $this->hasVatTaxIdInPayload((array) $payload, $depth + 1);
        }

        return false;
    }

    private function isVatKeyWithValue($key, $value)
    {
        if (!is_string($key)) {
            return false;
        }

        $normalizedKey = strtolower($key);

        if (!$this->containsAny($normalizedKey, array('vat', 'ust', 'taxid'))) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value !== '';
        }

        return false;
    }

    private function isEbayCustomer($contact, $event)
    {
        $email = strtolower($this->resolveEmail($contact, $event));

        if ($email === '' || strpos($email, '@') === false) {
            return false;
        }

        $ebayDomain = strtolower((string) $this->readConfigValue('ebayEmailDomain', '@members.ebay.com'));

        if ($ebayDomain === '' || strpos($ebayDomain, '@') !== 0) {
            $ebayDomain = '@members.ebay.com';
        }

        return $this->endsWith($email, $ebayDomain);
    }

    private function resolveEmail($contact, $event)
    {
        $contactEmail = trim((string) $this->readValue($contact, 'email', ''));

        if ($contactEmail !== '') {
            return $contactEmail;
        }

        $privateEmail = trim((string) $this->readValue($contact, 'privateEmail', ''));

        if ($privateEmail !== '') {
            return $privateEmail;
        }

        $eventContact = $this->readValue($event, 'contact');

        if ($this->isValidContactPayload($eventContact)) {
            $eventEmail = trim((string) $this->readValue($eventContact, 'email', ''));

            if ($eventEmail !== '') {
                return $eventEmail;
            }
        }

        $options = $this->readValue($contact, 'options');

        if (!is_array($options)) {
            return '';
        }

        foreach ($options as $option) {
            $value = trim((string) $this->readValue($option, 'value', ''));

            if ($value !== '' && strpos($value, '@') !== false) {
                return $value;
            }
        }

        return '';
    }

    private function isValidContactPayload($contact)
    {
        return is_array($contact) || is_object($contact);
    }

    private function readValue($source, $key, $default = null)
    {
        if (is_array($source)) {
            return array_key_exists($key, $source) ? $source[$key] : $default;
        }

        if (is_object($source)) {
            $objectValues = (array) $source;

            if (array_key_exists($key, $objectValues)) {
                return $objectValues[$key];
            }

            foreach ($objectValues as $objectKey => $value) {
                if (!is_string($objectKey)) {
                    continue;
                }

                // private/protected properties are matched by property-name suffix
                if (substr($objectKey, -strlen($key)) === $key) {
                    return $value;
                }
            }
        }

        return $default;
    }


    private function containsAny($value, $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function endsWith($value, $suffix)
    {
        if ($suffix === '') {
            return true;
        }

        $valueLength = strlen($value);
        $suffixLength = strlen($suffix);

        if ($suffixLength > $valueLength) {
            return false;
        }

        return substr($value, -$suffixLength) === $suffix;
    }
}
