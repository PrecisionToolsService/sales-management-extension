<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\Name\Field;
use Espo\Core\Notification\AssignmentNotificatorFactory;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Config;
use Espo\Core\Htmlizer\HtmlizerFactory as HtmlizerFactory;
use Espo\Tools\Stream\Service as StreamService;
use Espo\Entities\Note;
use Espo\Entities\Team;
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
use Espo\ORM\Name\Attribute;
use Espo\Modules\PushNotification\Tools\Utils as PushUtils;


use Michelf\Markdown;

/**
 * Handles operations with entities.
 */
class HookProcessor
{

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
        private PushUtils $utils,
        private AssignmentNotificatorFactory $notificatorFactory
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function sendPushNotification(Note $note): void
    {

        if (! in_array(
            $note->getType(),
            $this->config->get('streamPushNotificationsTypeList') ?? []
        )) return;

        $entityId = $note->getParentId();
        $entityType = $note->getParentType();
        $entity = $this->entityManager->getEntityById($entityType, $entityId);
        $this->log->info("PushNotification: " . $entity->getEntityType() . ":" . $entity->getId());

        $recipientIds = [];
        $followerIds = $this->getFollowersFromEntity($entity);
        $assignedUserIdsNote = $this->getAssignedUsersFromNote($note);
        $assignedUserIds = $this->getAssignedUsersFromEntity($entity);
        $mentionedUserIds = $this->getMentionedUsersFromNote($note);
        $teamUserIds = $this->getTeamUserNames($entity);
        $recipientIds = array_merge($followerIds, $assignedUserIdsNote, $assignedUserIds, $mentionedUserIds, $teamUserIds);
        $recipientIds = array_filter($recipientIds, fn($userId) => $this->isNotSelfAssignment($entity, $userId));
        if ($recipientIds == []) {
            $this->log->info("PushNotification: No notification recipients");
            return;
        }

        $data = [];
        $data['userName'] = $this->utils->getUserNameById($note->getCreatedById(), "name");
        $data['name'] = $entity->get(Field::NAME);
        $data['post'] = Markdown::defaultTransform($note->getPost() ?? '');
        $data['entityTypeLowerFirst'] = Util::mbLowerCaseFirst($this->language->translateLabel($entityType, 'scopeNames'));

        $templateType = $this->getTemplateType($note);
        if ($templateType == null) return;
        $title = $this->getTemplateString($entity, "subject", $templateType, $data);
        $message = $this->getTemplateString($entity, "body", $templateType, $data);

        // === OneSignal Push通知追加 ===
        $this->pushSender->send(
            array_values(array_unique($recipientIds)),
            $title,
            strip_tags($message),
            $entity
        );
    }

    /**
     * @return array<string> AssignedUserIdの配列
     */
    private function getAssignedUsersFromEntity(CoreEntity $entity): array
    {
        $hasAssignedUserField =
            $entity->has('assignedUserId') ||
            $entity->hasLinkMultipleField(Field::ASSIGNED_USERS) &&
            $entity->has('assignedUsersIds');

        if (!$hasAssignedUserField) {
            $this->log->info("PushNotification: hasAssignedUserField is false");
            return [];
        }

        $isMultipleAssignedUsersEnabled = $this->metadata->get(['scopes', $entity->getEntityType(), 'assignedUsers']);
        if ($isMultipleAssignedUsersEnabled) {
            return $entity->getLinkMultipleIdList(Field::ASSIGNED_USERS);
        } else {
            return $entity->get('assignedUserId') !== null ? [$entity->get('assignedUserId')] : [];
        }
    }

    /**
     * @return array<string> FollowerのUserIdの配列
     */
    private function getFollowersFromEntity(CoreEntity $entity): array
    {
        if (!$this->config->get('streamPushNotifications')) {
            $this->log->info("PushNotification: streamPushNotifications is false");
            return [];
        }
        if (!in_array(
            $entity->getEntityType(),
            $this->config->get('streamPushNotificationsEntityList') ?? []
        )) {
            $this->log->info("PushNotification: streamPushNotificationsEntityList is false");
            return [];
        }

        if (!$this->config->get('followerStreamPushNotifications')) {
            $this->log->info("PushNotification: followerStreamPushNotifications is false");
            return [];
        }

        return $this->streamService->getEntityFollowerIdList($entity);
    }

    /**
     * @return array<string> NoteのData内のAssignedUserIdの配列
     */
    private function getAssignedUsersFromNote(Note $note): array
    {
        $data = $note->getData();

        if (isset($data->assignedUserId)) {
            return [$data->assignedUserId];
        }

        if (isset($data->addedAssignedUsers)) {
            return array_filter($data->addedAssignedUsers, fn($user) => $user->id !== $note->getCreatedById());
        }
        return [];
    }

    /**
     * @return array<string> MentionされたUserのUserIdの配列
     */
    private function getMentionedUsersFromNote(Note $note): array
    {
        if (!$this->config->get('mentionPushNotifications')) return [];
        if (!isset($note->getData()->mentions)) return [];
        return array_map(fn($user) => $user->id, $note->getData()->mentions);
    }

    private function getTemplateType(Note $note): ?string
    {
        switch ($note->getType()) {
            case Note::TYPE_POST:
                if (isset($note->getData()->mentions)) return 'pushMention';
                else return "pushNotePost";
            case Note::TYPE_ASSIGN:
                return "pushAssignment";
            default:
                return null;
        }
    }

    private function isNotSelfAssignment(Entity $entity, ?string $assignedUserId): bool
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

    private function getTemplateString(Entity $entity, string $templateType, string $templateName, array $data): string
    {
        $template = $this->templateFileManager->getTemplate($templateName, $templateType, $entity->getEntityType(), "PushNotification");
        $htmlizer = $this->htmlizerFactory->create();
        return $htmlizer->render(
            $entity,
            $template,
            $templateName . '-push-' . $templateType . '-' . $entity->getEntityType(),
            $data,
            true
        );
    }

    /**
     * @return array<string> Teamに含まれるUserのUserNameの配列
     */
    private function getTeamUserNames(CoreEntity $entity): array
    {
        if (!$this->config->get('teamsStreamPushNotifications')) return [];
        /** @var ?array<Team> $teams */
        $teamIds = $entity->getLinkMultipleIdList(Field::TEAMS);
        $teamusers = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->select([Attribute::ID])
            ->distinct()
            ->join(Field::TEAMS)
            ->where([
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
                'isActive' => true,
                'teamsMiddle.teamId' => $teamIds,
            ])
            ->find();

        return array_map(fn($teamuser) => $teamuser->getId(), iterator_to_array($teamusers));
    }
}
