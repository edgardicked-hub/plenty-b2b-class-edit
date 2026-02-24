<?php

namespace B2BClassEdit\Listeners;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Events\AfterContactCreate;
use Plenty\Plugin\ConfigRepository;

class CustomerRegistrationListener
{
    private const DEFAULT_SOURCE_CLASS_ID = 4;
    private const DEFAULT_TARGET_CLASS_ID = 5;

    public function __construct(
        private readonly ContactRepositoryContract $contactRepository,
        private readonly ConfigRepository $config
    ) {
    }

    public function handle(AfterContactCreate $event): void
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

    private function getSourceClassId(): int
    {
        return (int) $this->config->get('B2BClassEdit.sourceClassId', self::DEFAULT_SOURCE_CLASS_ID);
    }

    private function getTargetClassId(): int
    {
        return (int) $this->config->get('B2BClassEdit.targetClassId', self::DEFAULT_TARGET_CLASS_ID);
    }

    private function extractContactId(AfterContactCreate $event): ?int
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

    private function hasVatTaxId(mixed $contact): bool
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

    private function isEbayCustomer(mixed $contact, AfterContactCreate $event): bool
    {
        $email = strtolower($this->resolveEmail($contact, $event));

        if ($email === '' || !str_contains($email, '@')) {
            return false;
        }

        $ebayDomain = strtolower((string) $this->config->get('B2BClassEdit.ebayEmailDomain', '@members.ebay.com'));

        if ($ebayDomain === '' || !str_starts_with($ebayDomain, '@')) {
            $ebayDomain = '@members.ebay.com';
        }

        return str_ends_with($email, $ebayDomain);
    }

    private function resolveEmail(mixed $contact, AfterContactCreate $event): string
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

            if ($value !== '' && str_contains($value, '@')) {
                return $value;
            }
        }

        return '';
    }

    private function isValidContactPayload(mixed $contact): bool
    {
        return is_array($contact) || is_object($contact);
    }

    private function readValue(mixed $source, string $key, mixed $default = null): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? $default;
        }

        if (is_object($source) && isset($source->{$key})) {
            return $source->{$key};
        }

        return $default;
    }
}
