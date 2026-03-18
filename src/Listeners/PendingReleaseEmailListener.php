<?php

namespace B2BClassEdit\Listeners;

use Plenty\Modules\Mail\Templates\Contracts\Service\EmailService\EmailTemplatesSendServiceContract;
use Plenty\Modules\Plugin\Events\PluginSendMail;
use Plenty\Plugin\Log\Loggable;

class PendingReleaseEmailListener
{
    use Loggable;

    /** @var EmailTemplatesSendServiceContract */
    private $emailTemplatesSendService;

    public function __construct(EmailTemplatesSendServiceContract $emailTemplatesSendService)
    {
        $this->emailTemplatesSendService = $emailTemplatesSendService;
    }

    public function handle(PluginSendMail $event)
    {
        $contactEmail = trim((string) $event->getContactEmail());

        if ($contactEmail === '') {
            return;
        }

        $pendingReleaseEmail = CustomerRegistrationListener::pullPendingReleaseEmail($contactEmail);

        if (!is_array($pendingReleaseEmail)) {
            return;
        }

        try {
            $result = $this->emailTemplatesSendService->sendEmail(
                (int) $pendingReleaseEmail['templateId'],
                (array) $pendingReleaseEmail['payload']
            );

            $this->getLogger(__METHOD__)->info('B2BClassEdit: release email sent after PluginSendMail', [
                'contactEmail' => $contactEmail,
                'templateId' => (int) $pendingReleaseEmail['templateId'],
                'result' => is_array($result) ? $result : [],
            ]);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error('B2BClassEdit: release email failed after PluginSendMail', [
                'contactEmail' => $contactEmail,
                'templateId' => (int) $pendingReleaseEmail['templateId'],
                'message' => $e->getMessage(),
            ]);
        }
    }
}
