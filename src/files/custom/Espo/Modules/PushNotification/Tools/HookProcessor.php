<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\Name\Field;
use Espo\Core\Notification\AssignmentNotificatorFactory;
use Espo\Core\Notification\AssignmentNotificator;
use Espo\Core\Notification\AssignmentNotificator\Params as AssignmentNotificatorParams;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Config;
use Espo\Tools\Stream\Service as StreamService;
use Espo\Modules\PushNotification\Tools\PushNotificationSender as PushSender;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Entities\User;
use Espo\Core\Utils\Log;
use Espo\Entities\Notification;
use Espo\Core\Utils\Language;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\ORM\Repository\Option\SaveOptions;

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
        private PushSender $pushSender,
        private EntityManager $entityManager,
        private StreamService $streamService,
        private Log $log,
        private AssignmentNotificatorFactory $notificatorFactory,
        private User $user
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
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


        // === OneSignal Push通知追加 ===
        if ($entity->has('assignedUserId')) {
            $userId = $entity->get('assignedUserId');
            /** @var \Espo\Core\ORM\Entity\User $user */
            $user = $this->entityManager->getEntity('User', $userId);

            if ($user && $user->has('userName')) {
                $title = 'New Assignment';
                $message = $this->language->translate($entityType, 'labels') . ' has been assigned to you.';

                $this->pushSender->send(
                    $user->get('userName'),
                    $title,
                    $message,
                    [
                        'entityType' => $entityType,
                        'entityId' => $entity->getId()
                    ]
                );
            }
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
