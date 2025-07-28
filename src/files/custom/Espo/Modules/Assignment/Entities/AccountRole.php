<?php

namespace Espo\Modules\Assignment\Entities;

use Espo\Core\ORM\Entity;

class AccountRole extends Entity
{
    public const ENTITY_TYPE = 'AccountRole';

    public const LEVEL_YES = 'yes';
    public const LEVEL_ALL = 'all';
    public const LEVEL_ASSIGNED = 'assigned';
    public const LEVEL_OWN = 'own';
    public const LEVEL_NO = 'no';

    public const ROLE_MEMBER = 'Member';
    public const ROLE_EDITOR = 'Editor';
    public const ROLE_OWNER = 'Owner';

    public const RELATIONSHIP_ACCOUNT_USER = 'AccountRole';

    public function getName(): string
    {
        return $this->get('name');
    }

    /**
     * @return array<string, string>
     */
    public function getPermissionMap(): array
    {
        $list = [
            'taskCreate',
            'taskRead',
            'taskStream',
            'taskEdit',
            'taskColumnEdit',
            'taskDelete',
            'taskAssignment',
        ];

        $map = [];

        foreach ($list as $item) {
            $map[$item] = $this->get($item);
        }

        return $map;
    }

    public function populateMaxPermissions(): void
    {
        $this->setMultiple([
            'taskCreate' => self::LEVEL_YES,
            'taskRead' => self::LEVEL_ALL,
            'taskStream' => self::LEVEL_ALL,
            'taskEdit' => self::LEVEL_ALL,
            'taskColumnEdit' => self::LEVEL_ALL,
            'taskDelete' => self::LEVEL_ALL,
            'taskAssignment' => self::LEVEL_ALL,
        ]);
    }

    public function populateDefaultMemberPermissions(): void
    {
        $this->setMultiple([
            'taskCreate' => self::LEVEL_NO,
            'taskRead' => self::LEVEL_OWN,
            'taskStream' => self::LEVEL_OWN,
            'taskEdit' => self::LEVEL_OWN,
            'taskColumnEdit' => self::LEVEL_ASSIGNED,
            'taskDelete' => self::LEVEL_NO,
            'taskAssignment' => self::LEVEL_OWN,
        ]);
    }

    public function getTaskCreate(): string
    {
        return $this->get('taskCreate');
    }

    public function getTaskRead(): string
    {
        return $this->get('taskRead');
    }

    public function getTaskStream(): string
    {
        return $this->get('taskStream');
    }

    public function getTaskEdit(): string
    {
        return $this->get('taskEdit');
    }

    public function getTaskColumnEdit(): string
    {
        return $this->get('taskColumnEdit');
    }

    public function getTaskDelete(): string
    {
        return $this->get('taskDelete');
    }

    public function getTaskAssignment(): string
    {
        return $this->get('taskAssignment');
    }
}
