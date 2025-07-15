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
        $data = [];
        $users = [];
        $templateType = "";

        if ($entity->getEntityType() !== Note::ENTITY_TYPE) return;

        /** @var ?Note $note */
        $note = $entity;
        $entityId = $note->getParentId();
        $entityType = $note->getParentType();
        $entity = $this->entityManager->getEntityById($entityType, $entityId);
        switch ($note->getType()) {
            case Note::TYPE_POST:
                $data['post'] = Markdown::defaultTransform($note->getPost() ?? '');
                if ($this->isStreamPushProcess($note)) {
                    $templateType = "pushNotePost";
                    $users = $this->getAssignedUsersFromEntity($entity);
                } elseif ($this->isStreamMentionedPushProcess($note)) {
                    $templateType = 'pushMention';
                    $users = $this->getMentionedUsersFromNote($note);
                } else return;
                break;
            case Note::TYPE_ASSIGN:
                if (!$this->isAssignmentProcess($entity)) return;
                $templateType = "pushAssignment";
                $users = $this->getAssignedUsersFromNote($note);
                break;
            default:
                return;
        }
        $data['userName'] = $this->getUserNameById($note->getCreatedById(), "name");
        $data['name'] = $entity->get(Field::NAME);
        $data['entityTypeLowerFirst'] = Util::mbLowerCaseFirst($this->language->translateLabel($entityType, 'scopeNames'));
        $title = $this->getTemplateString($entity, "subject", $templateType, $data);
        $message = $this->getTemplateString($entity, "body", $templateType, $data);

        $this->log->info("PushNotification: " . $entity->getEntityType() . ":" . $entity->getId());
        // === OneSignal Push通知追加 ===
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
    private function getAssignedUsersFromEntity(CoreEntity $entity): array
    {
        $supportMultipleAssignedUsers = $this->metadata->get(['scopes', $entity->getEntityType(), 'assignedUsers']);
        if (!$supportMultipleAssignedUsers && $this->isNotSelfAssignment($entity, $entity->get('assignedUserId'))) {
            return [$this->getUserNameById($entity->get('assignedUserId'))];
        }

        $userIdList = $entity->getLinkMultipleIdList(Field::ASSIGNED_USERS);
        $fetchedAssignedUserIdList = $entity->getFetched(Field::ASSIGNED_USERS . 'Ids') ?? [];
        $this->log->info(json_encode($userIdList));
        $this->log->info(json_encode($fetchedAssignedUserIdList));

        $targetUserIdList = [];

        foreach ($userIdList as $userId) {
            if (
                in_array($userId, $fetchedAssignedUserIdList) ||
                !$this->isNotSelfAssignment($entity, $userId)
            ) {
                $targetUserIdList[] = $this->getUserNameById($userId);
            }
        }
        return $targetUserIdList;
    }

    /**
     * @return array<string> UserNameの配列
     */
    private function getAssignedUsersFromNote(Note $note): array
    {
        $data = $note->getData();

        if (isset($data->assignedUserId)) {
            return [$this->getUserNameById($data->assignedUserId)];
        }

        if (isset($data->addedAssignedUsers)) {
            $targetUserIdList = [];
            foreach ($data->addedAssignedUsers as $user) {
                if ($user->id == $note->getCreatedById()) continue;
                $targetUserIdList[] = $this->getUserNameById($user->id);
            }
            return $targetUserIdList;
        } else return [];
    }

    /**
     * @return array<string> UserNameの配列
     */
    private function getMentionedUsersFromNote(Note $note): array
    {
        if (isset($note->getData()->mentions)) {
            $targetUserIdList = [];
            foreach ($note->getData()->mentions as $mentionedUser) {
                $targetUserIdList[] = $this->getUserNameById($mentionedUser->id);
            }
            return $targetUserIdList;
        } else return [];
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

        return in_array(
            $entity->getEntityType(),
            $this->config->get('assignmentPushNotificationsEntityList') ?? []
        );
    }

    private function isStreamPushProcess(Note $note): bool
    {
        if (!$this->config->get('streamPushNotifications')) {
            $this->log->info("PushNotification: streamPushNotifications is false");
            return false;
        }
        if (isset($note->getData()->mentions)) {
            return false;
        }

        return in_array(
            $note->getParentType(),
            $this->config->get('streamPushNotificationsEntityList') ?? []
        );
    }
    private function isStreamMentionedPushProcess(Note $note): bool
    {
        if (!$this->config->get('mentionPushNotifications')) {
            $this->log->info("PushNotification: streamPushNotifications is false");
            return false;
        }
        return isset($note->getData()->mentions);
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

    private function getUserNameById(string $userId, string $key = "userName"): string
    {
        $user = $this->entityManager->getEntityById('User', $userId);
        return $user->get($key);
    }
}
