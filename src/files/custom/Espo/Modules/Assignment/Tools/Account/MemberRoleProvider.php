<?php

namespace Espo\Modules\Assignment\Tools\Account;

use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Assignment\Entities\AccountRole;
use Espo\ORM\EntityManager;

class MemberRoleProvider
{
    /** @var array<string, ?MemberRole> */
    private array $cache = [];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function get(User $user, string $accountId): ?MemberRole
    {
        $cacheKey = $user->getId() . '-' . $accountId;

        if (!array_key_exists($cacheKey, $this->cache)) {
            $this->cache[$cacheKey] = $this->getInternal($user, $accountId);
        }

        return $this->cache[$cacheKey];
    }

    private function getInternal(User $user, string $accountId): ?MemberRole
    {
        $accountUser = $this->entityManager
            ->getRDBRepository(AccountRole::RELATIONSHIP_ACCOUNT_USER)
            ->where([
                'entityId' => $accountId,
                'userId' => $user->getId(),
            ])
            ->findOne();

        if (!$accountUser) {
            return null;
        }

        $role = $accountUser->get('role');
        $roleId = $accountUser->get('roleId');

        $roleEntity = null;

        if ($roleId) {
            $roleEntity = $this->entityManager->getRDBRepositoryByClass(AccountRole::class)->getById($roleId);
        }

        return new MemberRole($role, $roleEntity);
    }
}
