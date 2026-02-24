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

        if (!is_array($contact)) {
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

        $currentClassId = (int) ($contact['classId'] ?? 0);

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
        if (property_exists($event, 'contactId') && $event->contactId) {
            return (int) $event->contactId;
        }

        if (property_exists($event, 'contact') && is_array($event->contact) && isset($event->contact['id'])) {
            return (int) $event->contact['id'];
        }

        return null;
    }

    private function hasVatTaxId(array $contact): bool
    {
        $vatNumber = trim((string) ($contact['vatNumber'] ?? ''));

        if ($vatNumber !== '') {
            return true;
        }

        if (!isset($contact['options']) || !is_array($contact['options'])) {
            return false;
        }

        foreach ($contact['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }

            $isVatOption = (int) ($option['typeId'] ?? -1) === 6;
            $value = trim((string) ($option['value'] ?? ''));

            if ($isVatOption && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function isEbayCustomer(array $contact, AfterContactCreate $event): bool
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

    private function resolveEmail(array $contact, AfterContactCreate $event): string
    {
        $contactEmail = trim((string) ($contact['email'] ?? ''));

        if ($contactEmail !== '') {
            return $contactEmail;
        }

        $privateEmail = trim((string) ($contact['privateEmail'] ?? ''));

        if ($privateEmail !== '') {
            return $privateEmail;
        }

        if (property_exists($event, 'contact') && is_array($event->contact)) {
            $eventEmail = trim((string) ($event->contact['email'] ?? ''));

            if ($eventEmail !== '') {
                return $eventEmail;
            }
        }

        if (!isset($contact['options']) || !is_array($contact['options'])) {
            return '';
        }

        foreach ($contact['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));

            if ($value !== '' && str_contains($value, '@')) {
                return $value;
            }
        }

        return '';
    }
}
