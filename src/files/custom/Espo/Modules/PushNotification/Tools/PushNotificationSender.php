<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Log;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;

/**
 * A service for email sending. Can send with SMTP parameters of the system email account or with specific parameters.
 * Uses a builder to send with specific parameters.
 */
class PushNotificationSender
{
    public function __construct(
        private Log $log,
        private InjectableFactory $injectableFactory,
        private EntityManager $entityManager
    ) {}

    /**
     * Send an email.
     *
     * @throws Exceptions\SendingError
     * @param string[] $externalIds
     */
    public function send(array $externalIds, string $title, string $message, array $data = []): void
    {
        $this->sendOneSignalPushToExternalId($externalIds, $title, $message, $data);
    }

    /**
     * @param string[] $externalIds
     */
    private function sendOneSignalPushToExternalId(array $externalIds, string $title, string $message, array $data = []): void
    {
        $integration = $this->entityManager->getEntityById('Integration', 'PushNotification');
        if (!$integration) {
            throw new NotFound();
        }
        $appId = $integration->get('appId');
        $apiKey = $integration->get('apiKey');

        if (!$appId || !$apiKey || !$externalIds) {
            $this->log->warning("Missing OneSignal credentials or externalId");
            return;
        }

        $payload = [
            'app_id' => $appId,
            "include_aliases" => [
                "external_id" => $externalIds,
            ],
            'headings' => ['en' => $title],
            "target_channel" => "push",
            'contents' => ['en' => $message],
            'data' => $data,
        ];

        $ch = curl_init('https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: key ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $this->log->warning("response: " . $response);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            $this->log->error("OneSignal push failed", ['response' => $response]);
        }
    }
}
