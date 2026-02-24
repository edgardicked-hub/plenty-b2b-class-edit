<?php

namespace B2BClassEdit\Listeners;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Events\AfterContactCreate;
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

    public function handle(AfterContactCreate $event)
    {
        $contactId = $this->extractContactId($event);

        if ($contactId === null) {
            return;
        }

        $contact = $this->contactRepository->findContactById($contactId);

        if (!$this->isValidContactPayload($contact)) {
            return;
        }

        if (!$this->hasVatTaxId($contact)) {
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

        if ($currentClassId !== $sourceClassId) {
            return;
        }

        $this->contactRepository->updateContact($contactId, [
            'classId' => $targetClassId,
        ]);
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

    private function extractContactId(AfterContactCreate $event)
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

    private function hasVatTaxId($contact)
    {
        $vatNumber = trim((string) $this->readValue($contact, 'vatNumber', ''));

        if ($vatNumber !== '') {
            return true;
        }

        $options = $this->readValue($contact, 'options');

        if (!is_array($options)) {
            return false;
        }

        foreach ($options as $option) {
            $isVatOption = (int) $this->readValue($option, 'typeId', -1) === 6;
            $value = trim((string) $this->readValue($option, 'value', ''));

            if ($isVatOption && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function isEbayCustomer($contact, AfterContactCreate $event)
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

    private function resolveEmail($contact, AfterContactCreate $event)
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

            return array_key_exists($key, $objectValues) ? $objectValues[$key] : $default;
        }

        return $default;
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
