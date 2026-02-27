<?php

namespace B2BClassEdit\Listeners;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Events\AfterContactCreate;
use Plenty\Modules\Account\Contact\Events\AfterContactUpdate;
use Plenty\Modules\Authentication\Events\AfterAccountAuthentication;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class CustomerRegistrationListener
{
    use Loggable;

    const DEFAULT_SOURCE_CLASS_ID = 4;
    const DEFAULT_TARGET_CLASS_ID = 5;

    /** @var array<int, bool> */
    private static $inProgress = [];

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
        try {
            if (!is_object($event)) {
                return;
            }

            $eventClass = get_class($event);
            $eventContact = null;
            $contactId = 0;

            if ($event instanceof AfterContactCreate || $event instanceof AfterContactUpdate) {
                $eventContact = $event->getContact();
                $contactId = (int) $this->readValue($eventContact, 'id', 0);
            } elseif ($event instanceof AfterAccountAuthentication) {
                if (!$event->isSuccessful()) {
                    return;
                }

                $eventContact = $event->getAccountContact();
                $contactId = (int) $this->readValue($eventContact, 'id', 0);
            } else {
                return;
            }

            if ($contactId <= 0) {
                $this->getLogger(__METHOD__)->warning('B2BClassEdit: contactId missing on event contact', [
                    'eventClass' => $eventClass,
                ]);
                return;
            }

            $this->processContactId($contactId, $eventClass, $eventContact);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error('B2BClassEdit: handler crashed', [
                'message' => $e->getMessage(),
            ]);
            return;
        }
    }

    public function processContactId($contactId, $eventClass = 'manual', $eventContact = null)
    {
        $contactId = (int) $contactId;

        if ($contactId <= 0) {
            return;
        }

        if (isset(self::$inProgress[$contactId])) {
            return;
        }

        self::$inProgress[$contactId] = true;

        try {
            $contact = $this->loadContactWithRelations($contactId);

            if (!$this->isValidPayload($contact)) {
                return;
            }

            if (!$this->isValidPayload($eventContact)) {
                $eventContact = $contact;
            }

            $sourceClassId = $this->getSourceClassId();
            $targetClassId = $this->getTargetClassId();
            $currentClassId = (int) $this->readValue($contact, 'classId', 0);

            $this->getLogger(__METHOD__)->info('B2BClassEdit: evaluating contact', [
                'eventClass' => $eventClass,
                'contactId' => $contactId,
                'currentClassId' => $currentClassId,
                'sourceClassId' => $sourceClassId,
                'targetClassId' => $targetClassId,
            ]);

            if ($sourceClassId === $targetClassId) {
                $this->getLogger(__METHOD__)->warning('B2BClassEdit: sourceClassId equals targetClassId');
                return;
            }

            if ($currentClassId === $targetClassId) {
                $this->getLogger(__METHOD__)->info('B2BClassEdit: skipped, already target class', [
                    'contactId' => $contactId,
                ]);
                return;
            }

            if ($currentClassId !== $sourceClassId) {
                $this->getLogger(__METHOD__)->info('B2BClassEdit: skipped, class does not match sourceClassId', [
                    'contactId' => $contactId,
                    'currentClassId' => $currentClassId,
                    'sourceClassId' => $sourceClassId,
                ]);
                return;
            }

            $vat = $this->getVatTaxId($contact);

            if ($vat === '') {
                $this->getLogger(__METHOD__)->info('B2BClassEdit: skipped, no VAT/USt-IdNr found', [
                    'contactId' => $contactId,
                ]);
                return;
            }

            if ($this->isEbayCustomer($contact, $eventContact)) {
                $this->getLogger(__METHOD__)->info('B2BClassEdit: skipped, eBay domain', [
                    'contactId' => $contactId,
                ]);
                return;
            }

            try {
                $this->contactRepository->updateContact([
                    'classId' => $targetClassId,
                ], $contactId);
            } catch (\Throwable $e) {
                $this->getLogger(__METHOD__)->error('B2BClassEdit: updateContact failed', [
                    'contactId' => $contactId,
                    'message' => $e->getMessage(),
                ]);
                return;
            }

            $this->getLogger(__METHOD__)->info('B2BClassEdit: class updated', [
                'eventClass' => $eventClass,
                'contactId' => $contactId,
                'fromClassId' => $currentClassId,
                'toClassId' => $targetClassId,
                'vat' => $this->maskVat($vat),
                'updated' => true,
            ]);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error('B2BClassEdit: processContactId crashed', [
                'contactId' => $contactId,
                'message' => $e->getMessage(),
            ]);
            return;
        } finally {
            unset(self::$inProgress[$contactId]);
        }
    }

    private function loadContactWithRelations($contactId)
    {
        try {
            return $this->contactRepository->findContactById($contactId, [
                'accounts',
                'addresses',
                'addresses.options',
                'options',
            ]);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->warning('B2BClassEdit: failed to eager-load relations', [
                'contactId' => (int) $contactId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getVatTaxId($contact)
    {
        // 1) Account.taxIdNumber
        $accounts = $this->asIterable($this->readValue($contact, 'accounts'));
        foreach ($accounts as $account) {
            $vat = trim((string) $this->readValue($account, 'taxIdNumber', ''));
            if ($vat !== '') {
                return $vat;
            }
        }

        // 2) Address.taxIdNumber (Alias)
        $addresses = $this->asIterable($this->readValue($contact, 'addresses'));
        foreach ($addresses as $address) {
            $vat = trim((string) $this->readValue($address, 'taxIdNumber', ''));
            if ($vat !== '') {
                return $vat;
            }

            // 3) Address options typeId=1
            $options = $this->asIterable($this->readValue($address, 'options'));
            foreach ($options as $option) {
                if ((int) $this->readValue($option, 'typeId', -1) === 1) {
                    $value = trim((string) $this->readValue($option, 'value', ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        // Fallback auf direkte Contact-Felder
        $directVat = trim((string) $this->readValue($contact, 'vatNumber', ''));
        if ($directVat !== '') {
            return $directVat;
        }

        $directTax = trim((string) $this->readValue($contact, 'taxIdNumber', ''));
        if ($directTax !== '') {
            return $directTax;
        }

        return '';
    }

    private function isEbayCustomer($contact, $eventContact)
    {
        $email = strtolower($this->resolveEmail($contact, $eventContact));

        if ($email === '' || strpos($email, '@') === false) {
            return false;
        }

        $ebayDomain = strtolower((string) $this->readConfigValue('ebayEmailDomain', '@members.ebay.com'));

        if ($ebayDomain === '' || strpos($ebayDomain, '@') !== 0) {
            $ebayDomain = '@members.ebay.com';
        }

        return $this->endsWith($email, $ebayDomain);
    }

    private function resolveEmail($contact, $eventContact)
    {
        $contactEmail = trim((string) $this->readValue($contact, 'email', ''));
        if ($contactEmail !== '') {
            return $contactEmail;
        }

        $privateEmail = trim((string) $this->readValue($contact, 'privateEmail', ''));
        if ($privateEmail !== '') {
            return $privateEmail;
        }

        $eventEmail = trim((string) $this->readValue($eventContact, 'email', ''));
        if ($eventEmail !== '') {
            return $eventEmail;
        }

        return '';
    }

    private function readConfigValue($key, $default = null)
    {
        $value = $this->config->get('B2BClassEdit.' . $key, null);

        if ($value === null || $value === '') {
            $value = $this->config->get($key, $default);
        }

        return $value;
    }

    private function getSourceClassId()
    {
        return (int) $this->readConfigValue('sourceClassId', self::DEFAULT_SOURCE_CLASS_ID);
    }

    private function getTargetClassId()
    {
        return (int) $this->readConfigValue('targetClassId', self::DEFAULT_TARGET_CLASS_ID);
    }

    private function isValidPayload($value)
    {
        return is_array($value) || is_object($value);
    }

    private function readValue($source, $key, $default = null)
    {
        if (is_object($source)) {
            try {
                $source = $source->toArray();
            } catch (\Throwable $e) {
                // no-op fallback
            }
        }

        $iterable = $this->asIterable($source);

        if (!empty($iterable)) {
            return array_key_exists($key, $iterable) ? $iterable[$key] : $default;
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

                if (substr($objectKey, -strlen($key)) === $key) {
                    return $value;
                }
            }
        }

        return $default;
    }

    private function asIterable($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            $out = [];

            foreach ($value as $itemKey => $itemValue) {
                $out[$itemKey] = $itemValue;
            }

            return $out;
        }

        return [];
    }

    private function maskVat($vat)
    {
        $vat = trim((string) $vat);

        if ($vat === '') {
            return '';
        }

        $length = strlen($vat);

        if ($length <= 4) {
            return '****';
        }

        return substr($vat, 0, 2) . '…' . substr($vat, -4);
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
