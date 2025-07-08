<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\Name\Field;
use Espo\Core\Notification\AssignmentNotificatorFactory;
use Espo\Core\Notification\AssignmentNotificator;
use Espo\Core\Notification\AssignmentNotificator\Params as AssignmentNotificatorParams;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Config;
use Espo\Tools\Stream\Service as StreamService;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Entities\User;
use Espo\Core\Utils\Log;
use Espo\Entities\Notification;
use Espo\Core\Utils\Language;
use Espo\Core\ORM\Entity as CoreEntity;

/**
 * Handles operations with entities.
 */
class HookProcessor
{
    /** @var array<string, AssignmentNotificator<Entity>> */
    private $notificatorsHash = [];
    /** @var array<string, bool> */
    private $hasStreamCache = [];
    /** @var array<string, string> */
    private $userNameHash = [];

    public function __construct(
        private Metadata $metadata,
        private Config $config,
        private Language $language,
        private EntityManager $entityManager,
        private StreamService $streamService,
        private Log $log,
        private AssignmentNotificatorFactory $notificatorFactory,
        private User $user
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options): void
    {
        $entityType = $entity->getEntityType();

        if (!$entity instanceof CoreEntity) {
            return;
        }

        $hasStream = $this->checkHasStream($entityType);
        $force = $this->forceAssignmentNotificator($entityType);

        /**
         * No need to process assignment notifications for entity types that have Stream enabled.
         * Users are notified via Stream notifications.
         */
        if ($hasStream && !$force) {
            return;
        }

        $assignmentNotificationsEntityList = $this->config->get('assignmentNotificationsEntityList') ?? [];
        if (
            (!$force || !$hasStream) &&
            !in_array($entityType, $assignmentNotificationsEntityList)
        ) {
            return;
        }

        $notificator = $this->getNotificator($entityType);

        $params = AssignmentNotificatorParams::create()->withRawOptions($options);

        // === OneSignal Push通知追加 ===
    $this->log->warning("Push Notification checkpoint 3");
    if ($entity->has('assignedUserId')) {
        $userId = $entity->get('assignedUserId');
        /** @var \Espo\Core\ORM\Entity\User $user */
        $user = $this->entityManager->getEntity('User', $userId);
        
        $this->log->warning("Push Notification checkpoint 4");
        if ($user && $user->has('userName')) {
            $this->log->warning("Push Notification checkpoint 5");
            $title = 'New Assignment';
            $message = $this->language->translate($entityType, 'labels') . ' has been assigned to you.';

            $this->sendOneSignalPushToExternalId(
                $user->get('userName'),
                $title,
                $message,
                [
                    'entityType' => $entityType,
                    'entityId' => $entity->getId()
                ]
            );
            $this->log->warning("Push Notification checkpoint 6");
        }
    }
    }

    private function sendOneSignalPushToExternalId(string $externalId, string $title, string $message, array $data = []): void
{
    $appId = $this->config->get('onesignalAppId');
    $apiKey = $this->config->get('onesignalApiKey');

    if (!$appId || !$apiKey || !$externalId) {
        $this->log->warning("Missing OneSignal credentials or externalId");
        return;
    }

    $payload = [
        'app_id' => $appId,
        "include_aliases" => [
            "external_id" => [
                $externalId
            ]
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

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options): void
    {
        $entityType = $entity->getEntityType();

        if (!$this->checkHasStream($entityType)) {
            return;
        }

        $followersData = $this->streamService->getEntityFollowers($entity);

        $userIdList = $followersData['idList'];

        $removedById = $options['modifiedById'] ?? $this->user->getId();
        $removedByName = $this->getUserNameById($removedById);

        foreach ($userIdList as $userId) {
            if ($userId === $removedById) {
                continue;
            }

            $this->entityManager->createEntity(Notification::ENTITY_TYPE, [
                'userId' => $userId,
                'type' => Notification::TYPE_ENTITY_REMOVED,
                'data' => [
                    'entityType' => $entity->getEntityType(),
                    'entityId' => $entity->getId(),
                    'entityName' => $entity->get(Field::NAME),
                    'userId' => $removedById,
                    'userName' => $removedByName,
                ],
            ]);
        }
    }

    public function afterRemove(Entity $entity): void
    {
        $query = $this->entityManager
            ->getQueryBuilder()
            ->delete()
            ->from(Notification::ENTITY_TYPE)
            ->where([
                'OR' => [
                    [
                        'relatedId' => $entity->getId(),
                        'relatedType' => $entity->getEntityType(),
                    ],
                    [
                        'relatedParentId' => $entity->getId(),
                        'relatedParentType' => $entity->getEntityType(),
                    ],
                ],
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    private function checkHasStream(string $entityType): bool
    {
        if (!array_key_exists($entityType, $this->hasStreamCache)) {
            $this->hasStreamCache[$entityType] =
                (bool) $this->metadata->get(['scopes', $entityType, 'stream']);
        }

        return $this->hasStreamCache[$entityType];
    }

    /**
     * @return AssignmentNotificator<Entity>
     */
    private function getNotificator(string $entityType): AssignmentNotificator
    {
        if (empty($this->notificatorsHash[$entityType])) {
            $notificator = $this->notificatorFactory->create($entityType);

            $this->notificatorsHash[$entityType] = $notificator;
        }

        return $this->notificatorsHash[$entityType];
    }

    private function getUserNameById(string $id): string
    {
        if ($id === $this->user->getId()) {
            return $this->user->get(Field::NAME);
        }

        if (!array_key_exists($id, $this->userNameHash)) {
            /** @var ?User $user */
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $id);

            if ($user) {
                $this->userNameHash[$id] = $user->getName() ?? $id;
            }
        }

        return $this->userNameHash[$id];
    }

    private function forceAssignmentNotificator(string $entityType): bool
    {
        return (bool) $this->metadata->get(['notificationDefs', $entityType, 'forceAssignmentNotificator']);
    }
}
