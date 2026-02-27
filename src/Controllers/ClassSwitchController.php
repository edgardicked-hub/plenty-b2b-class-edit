<?php

namespace B2BClassEdit\Controllers;

use B2BClassEdit\Listeners\CustomerRegistrationListener;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;

class ClassSwitchController extends Controller
{
    public function trigger($contactId, CustomerRegistrationListener $listener, ConfigRepository $config, Request $request)
    {
        $contactId = (int) $contactId;

        if ($contactId <= 0) {
            return [
                'success' => false,
                'message' => 'invalid_contact_id',
            ];
        }

        $configuredUser = trim((string) $config->get('B2BClassEdit.apiUsername', $config->get('apiUsername', '')));
        $configuredPassword = (string) $config->get('B2BClassEdit.apiPassword', $config->get('apiPassword', ''));

        // Wenn API-Credentials konfiguriert sind, müssen sie im Request mitgegeben werden.
        if ($configuredUser !== '' || $configuredPassword !== '') {
            $providedUser = trim((string) $request->get('apiUser'));
            $providedPassword = (string) $request->get('apiPassword');

            if ($providedUser !== $configuredUser || $providedPassword !== $configuredPassword) {
                return [
                    'success' => false,
                    'message' => 'unauthorized',
                ];
            }
        }

        // Nutzt dieselbe Logik wie der Event-Listener, aber manuell per API aufrufbar.
        $listener->processContactId($contactId, 'manual_rest', null);

        return [
            'success' => true,
            'message' => 'class_switch_triggered',
            'contactId' => $contactId,
        ];
    }
}
