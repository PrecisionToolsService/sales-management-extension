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
}
