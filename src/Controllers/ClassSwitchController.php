<?php

namespace B2BClassEdit\Controllers;

use B2BClassEdit\Listeners\CustomerRegistrationListener;
use Plenty\Plugin\Controller;

class ClassSwitchController extends Controller
{
    public function trigger($contactId, CustomerRegistrationListener $listener)
    {
        $contactId = (int) $contactId;

        if ($contactId <= 0) {
            return [
                'success' => false,
                'message' => 'invalid_contact_id',
            ];
        }

        // Nutzt dieselbe Logik wie der Event-Listener, aber manuell per API aufrufbar.
        $listener->handle([
            'contactId' => $contactId,
        ]);

        return [
            'success' => true,
            'message' => 'class_switch_triggered',
            'contactId' => $contactId,
        ];
    }
}
