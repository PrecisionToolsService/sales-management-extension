<?php

namespace Espo\Modules\Assignment\Tools\Account;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Assignment\Entities\AccountRole;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;

class MembersService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
        private Log $log,
        private MemberRoleProvider $memberRoleProvider,
    ) {}

    public function get(Account $account): ?MemberRole
    {
        return $this->memberRoleProvider->get($this->user, $account->getId());
    }

    /**
     * @param string[] $userIds
     * @throws Forbidden
     */
    public function link(Account $account, array $userIds, bool $synced, ?string $role): void
    {
        $users = $this->getUsers($userIds);

        $this->checkAccess($users, $account);

        $relation = $this->entityManager->getRelation($account, 'assignedUsers');

        if ($role === null || in_array($role, [AccountRole::ROLE_OWNER, AccountRole::ROLE_EDITOR])) {
            foreach ($users as $user) {
                $columns = [
                    'role' => $role,
                    'roleId' => null,
                    'synced' => $synced,
                ];

                if ($relation->isRelated($user)) {
                    $relation->updateColumns($user, $columns);

                    continue;
                }

                $relation->relate($user, $columns);
            }

            return;
        }

        $this->checkRole($role);

        foreach ($users as $user) {
            $columns = [
                'role' => null,
                'roleId' => $role,
                'synced' => $synced,
            ];

            if ($relation->isRelated($user)) {
                $relation->updateColumns($user, $columns);

                continue;
            }

            $relation->relate($user, $columns);
        }
    }

    /**
     * @param string[] $userIds
     * @return iterable<User>
     */
    private function getUsers(array $userIds): iterable
    {
        /** @var iterable<User> */
        return $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where(['id' => $userIds])
            ->find();
    }

    /**
     * @param iterable<User> $users
     * @throws Forbidden
     */
    private function checkUserAccess(iterable $users): void
    {
        foreach ($users as $user) {
            if (!$this->acl->checkEntityRead($user)) {
                throw new Forbidden("No 'read' access to user {$user->getUserName()}.");
            }

            if (!$this->acl->checkAssignmentPermission($user)) {
                throw new Forbidden("No 'assignment' permission to user {$user->getUserName()}.");
            }
        }
    }

    /**
     * @param iterable<User> $users
     * @throws Forbidden
     */
    private function checkAccess(iterable $users, Account $account): void
    {
        $this->checkUserAccess($users);

        if (!$this->acl->checkEntityEdit($account)) {
            throw new Forbidden("No 'edit' access.");
        }

        $role = $this->memberRoleProvider->get($this->user, $account->getId());

        if (
            !$this->user->isAdmin() &&
            $role?->role !== AccountRole::ROLE_OWNER
        ) {
            throw new Forbidden("No access. Not owner.");
        }
    }

    /**
     * @throws Forbidden
     */
    private function checkRole(string $role): void
    {
        if ($this->entityManager->getEntityById(AccountRole::ENTITY_TYPE, $role)) {
            return;
        }

        throw new Forbidden("Role not found.");
    }
}
