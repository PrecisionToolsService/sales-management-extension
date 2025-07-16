<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Log;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\Core\Utils\Metadata;
use Espo\Modules\PushNotification\Tools\Utils;

/**
 * A service for email sending. Can send with SMTP parameters of the system email account or with specific parameters.
 * Uses a builder to send with specific parameters.
 */
class PushNotificationSender
{
    public function __construct(
        private Log $log,
        private InjectableFactory $injectableFactory,
        private Config $config,
        private Metadata $metadata,
        private EntityManager $entityManager,
        private Utils $utils
    ) {}

    /**
     * Send an email.
     *
     * @throws Exceptions\SendingError
     * @param string[] $userIds
     */
    public function send(array $userIds, string $title, string $message, Entity $entity): void
    {
        $userNames = array_map(fn($userId) => $this->utils->getUserNameById($userId), $userIds);
        $this->sendOneSignalPushToExternalId($userNames, $title, $message, $entity);
    }

    /**
     * @param string[] $externalIds
     */
    private function sendOneSignalPushToExternalId(array $externalIds, string $title, string $message, Entity $entity): void
    {
        $this->log->info("PushNotification: Recipients" . json_encode($externalIds));
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

        $recordUrl = rtrim($this->config->get('siteUrl'), '/') .
            '/#' . $entity->getEntityType() . '/view/' . $entity->getId();

        $payload = [
            'app_id' => $appId,
            "include_aliases" => [
                "external_id" => $externalIds,
            ],
            'headings' => ['en' => $title],
            "target_channel" => "push",
            'contents' => ['en' => $message],
            'url' => $recordUrl
        ];
        $baseUrl = $this->metadata->get('integrations.PushNotification.params.onesignalApiBaseUrl');
        $ch = curl_init($baseUrl . '/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: key ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $this->log->info("response: " . $response);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            $this->log->error("OneSignal push failed", ['response' => $response]);
        }
    }
}
