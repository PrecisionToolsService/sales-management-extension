<?php

namespace Espo\Modules\Assignment\Tools\Account;

use Espo\Modules\Assignment\Entities\AccountRole;

class MemberRole
{
    public function __construct(
        readonly public ?string $role,
        readonly public AccountRole $record,
    ) {}
}
