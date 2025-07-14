<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\Name\Field;
use Espo\Core\Notification\AssignmentNotificatorFactory;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Config;
use Espo\Core\Htmlizer\Htmlizer;
use Espo\Core\Htmlizer\HtmlizerFactory as HtmlizerFactory;
use Espo\Tools\Stream\Service as StreamService;
use Espo\Entities\Note;
use Espo\Modules\PushNotification\Tools\PushNotificationSender as PushSender;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Core\ApplicationState;
use Espo\Entities\User;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\TemplateFileManager;
use Espo\ORM\Repository\Option\SaveOptions;

use Michelf\Markdown;

/**
 * Handles operations with entities.
 */
class HookProcessor
{
    private ?Htmlizer $htmlizer = null;

    public function __construct(
        private Metadata $metadata,
        private Config $config,
        private Language $language,
        private PushSender $pushSender,
        private HtmlizerFactory $htmlizerFactory,
        private EntityManager $entityManager,
        private TemplateFileManager $templateFileManager,
        private StreamService $streamService,
        private ApplicationState $applicationState,
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

        if (!$this->isSupportedEntity($entity)) return;
        $this->log->info($entityType);

        /** @var ?Note $note */
        $note = $entity;
        $data = [];
        $entityId = $note->getParentId();
        $entityType = $note->getParentType();
        $entity = $this->entityManager->getEntityById($entityType, $entityId);
        $user = $this->entityManager->getEntityById('User', $note->getCreatedById());
        $data['userName'] = $user->get('name');
        $data['name'] = $entity->get(Field::NAME);
        $data['entityTypeLowerFirst'] = Util::mbLowerCaseFirst($this->language->translateLabel($entityType, 'scopeNames'));
        $templateType = "";
        switch ($note->getType()) {
            case Note::TYPE_POST:
                if (!$this->isStreamPushProcess($note)) return;
                $data['post'] = Markdown::defaultTransform($note->getPost() ?? '');
                $templateType = "pushNotePost";
                break;
            case Note::TYPE_ASSIGN:
                if (!$this->isAssignmentProcess($entity)) return;
                $templateType = "pushAssignment";
                break;
            default:
                return;
        }

        $title = $this->getTemplateString($entity, "subject", $templateType, $data);
        $message = $this->getTemplateString($entity, "body", $templateType, $data);

        $this->log->info("PushNotification: " . $entity->getEntityType() . ":" . $entity->getId());
        // === OneSignal Push通知追加 ===
        $users = $this->getAssignedUsers($entity);
        if ($users == []) {
            $this->log->info("PushNotification: No assigned Users");
            return;
        }

        $this->pushSender->send(
            $users,
            $title,
            strip_tags($message),
            $entity
        );
    }

    /**
     * @return array<string> UserNameの配列
     */
    private function getAssignedUsers(CoreEntity $entity): array
    {
        if ($entity->has('assignedUserId') && $this->isNotSelfAssignment($entity, $entity->get('assignedUserId'))) {
            $userId = $entity->get('assignedUserId');
            $user = $this->entityManager->getEntityById('User', $userId);
            return [$user->get('userName')];
        }

        $userIdList = $entity->getLinkMultipleIdList(Field::ASSIGNED_USERS);
        $fetchedAssignedUserIdList = $entity->getFetched(Field::ASSIGNED_USERS . 'Ids') ?? [];

        $targetUserIdList = [];

        foreach ($userIdList as $userId) {
            if (
                in_array($userId, $fetchedAssignedUserIdList) ||
                !$this->isNotSelfAssignment($entity, $userId)
            ) {
                $user = $this->entityManager->getEntityById('User', $userId);
                $targetUserIdList[] = $user->get('userName');
            }
        }
        return $targetUserIdList;
    }

    private function isAssignmentProcess(CoreEntity $entity): bool
    {
        if (!$this->config->get('assignmentPushNotifications')) {
            $this->log->info("PushNotification: assignmentPushNotifications is false");
            return false;
        }

        $hasAssignedUserField =
            $entity->has('assignedUserId') ||
            $entity->hasLinkMultipleField(Field::ASSIGNED_USERS) &&
            $entity->has('assignedUsersIds');

        if (!$hasAssignedUserField) {
            $this->log->info("PushNotification: hasAssignedUserField is false");
            return false;
        }
        // if (!$entity->isAttributeChanged('assignedUserId') && !$entity->isAttributeChanged('assignedUserIds')) {
        //     $this->log->info("PushNotification: isAttributeChanged is false");
        //     return false;
        // }

        return in_array(
            $entity->getEntityType(),
            $this->config->get('assignmentPushNotificationsEntityList') ?? []
        );
    }

    private function isSupportedEntity(Entity $entity): bool
    {
        if (!$entity instanceof CoreEntity) {
            $this->log->info("PushNotification: CoreEntity is false");
            return false;
        }
        if ($entity->getEntityType() !== Note::ENTITY_TYPE) {
            $this->log->info("PushNotification: Note::ENTITY_TYPE is false");
            return false;
        }
        return true;
    }

    private function isStreamPushProcess(CoreEntity $entity): bool
    {
        if (!$this->config->get('streamPushNotifications')) {
            $this->log->info("PushNotification: streamPushNotifications is false");
            return false;
        }

        /** @var ?Note $entity */
        return in_array(
            $entity->getParentType(),
            $this->config->get('streamPushNotificationsEntityList') ?? []
        );
    }

    private function isNotSelfAssignment(Entity $entity, string $assignedUserId): bool
    {
        return true;
        if ($entity->hasAttribute('createdById') && $entity->hasAttribute('modifiedById')) {
            if ($entity->isNew()) {
                return $assignedUserId !== $entity->get('createdById');
            }

            return $assignedUserId !== $entity->get('modifiedById');
        }

        return $assignedUserId !== $this->applicationState->getUserId();
    }

    private function getHtmlizer(): Htmlizer
    {
        if (!$this->htmlizer) {
            $this->htmlizer = $this->htmlizerFactory->create(true);
        }

        return $this->htmlizer;
    }

    private function getTemplateString(Entity $entity, string $templateType, string $templateName, array $data): string
    {
        $template = $this->templateFileManager->getTemplate($templateName, $templateType, $entity->getEntityType(), "PushNotification");
        return $this->getHtmlizer()->render(
            $entity,
            $template,
            $templateName . '-push-' . $templateType . '-' . $entity->getEntityType(),
            $data,
            true
        );
    }
}
