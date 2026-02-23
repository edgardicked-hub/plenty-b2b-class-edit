<?php

namespace B2BClassEdit\Listeners;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Events\AfterContactCreate;
use Plenty\Plugin\ConfigRepository;

class CustomerRegistrationListener
{
    private const SOURCE_CLASS_FROM = 4;
    private const SOURCE_CLASS_TO = 5;

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

        if (!$this->isShopCustomer($contact, $event)) {
            return;
        }

        $currentClassId = (int) ($contact['classId'] ?? 0);

        if ($currentClassId !== self::SOURCE_CLASS_FROM) {
            return;
        }

        $this->contactRepository->updateContact($contactId, [
            'classId' => self::SOURCE_CLASS_TO,
        ]);
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

    private function isShopCustomer(array $contact, AfterContactCreate $event): bool
    {
        $referrerId = $this->resolveReferrerId($contact, $event);

        if ($referrerId === null) {
            return true;
        }

        $blockedReferrerIds = (array) $this->config->get('B2BClassEdit.blockedReferrerIds', [2, 11]);
        $blockedReferrerIds = array_map('intval', $blockedReferrerIds);

        return !in_array($referrerId, $blockedReferrerIds, true);
    }

    private function resolveReferrerId(array $contact, AfterContactCreate $event): ?int
    {
        if (isset($contact['referrerId'])) {
            return (int) $contact['referrerId'];
        }

        if (property_exists($event, 'referrerId') && $event->referrerId !== null) {
            return (int) $event->referrerId;
        }

        return null;
    }
}
