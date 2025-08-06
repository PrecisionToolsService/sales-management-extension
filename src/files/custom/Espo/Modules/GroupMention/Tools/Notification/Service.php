<?php

namespace Espo\Modules\GroupMention\Tools\Notification;

use Espo\Core\Utils\Id\RecordIdGenerator;
use Espo\Entities\Note;
use Espo\Entities\Notification;
use Espo\Core\AclManager;
use Espo\ORM\Entity;
use Espo\Core\WebSocket\Submission;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;
use Espo\Entities\Role;
use Espo\Entities\Team;

class Service
{
    public function __construct(
        private Log $log,
        private EntityManager $entityManager,
        private AclManager $aclManager,
        private Submission $webSocketSubmission,
        private RecordIdGenerator $idGenerator,
    ) {}

    public function notifyAboutMentionInPost(Entity $entity, Note $note): void
    {
        $entityType = $entity->getEntityType();
        switch ($entityType) {
            case Team::ENTITY_TYPE:
                $tableName = Team::RELATIONSHIP_TEAM_USER;
                break;
            case Role::ENTITY_TYPE:
                $tableName = 'RoleUser';
                break;
        }

        $teamUsers = $this->entityManager
            ->getRDBRepository($tableName)
            ->where([
                strtolower($entityType) . 'Id' => $entity->getId(),
            ])
            ->find();
        foreach ($teamUsers as $teamUser) {
            $this->entityManager->createEntity(Notification::ENTITY_TYPE, [
                'type' => Notification::TYPE_MENTION_IN_POST,
                'data' => [
                    'noteId' => $note->getId(),
                ],
                'userId' => $teamUser->get("userId"),
                'relatedId' => $note->getId(),
                'relatedType' => Note::ENTITY_TYPE,
            ]);
        }
    }
}
