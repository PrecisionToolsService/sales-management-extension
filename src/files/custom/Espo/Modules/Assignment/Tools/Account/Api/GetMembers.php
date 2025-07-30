<?php

namespace Espo\Modules\Assignment\Tools\Account\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Crm\Entities\Account;
use Espo\Entities\User;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Modules\Assignment\Tools\Account\MembersService;

class GetMembers implements Action
{
    public function __construct(
        private Log $log,
        private EntityProvider $entityProvider,
        private MembersService $membersService,
        private EntityManager $entityManager,
    ) {}

    /**
     * @inheritDoc
     */
    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $link = "assignedUsers";
        /** @var \Espo\Modules\Crm\Entities\Account $account */
        $account = $this->entityProvider->getByClass(Account::class, $id);
        $entityUsers = $this->entityManager
            ->getRDBRepository("EntityUser")
            ->where([
                'entityId' => $account->getId(),
                'entityType' => Account::ENTITY_TYPE
            ])
            ->find();
        $list = [];

        foreach ($entityUsers as $entityUser) {
            /** @var \Espo\Entities\User $user */
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $entityUser->get("userId"));
            $user->getTeams()->getIdList(); // おまじない

            $list[] = [
                "id" => $user->getId(),
                "name" => $user->getName(),
                "userName" => $user->getUserName(),
                "type" => $user->getType(),
                "title" => $user->getTitle(),
                "teamsIds" => $user->get("teamsIds"),
                "teamsNames" => $user->get("teamsNames"),
                "salutationName" => $user->get("salutationName"),
                "firstName" => $user->getFirstName(),
                "lastName" => $user->getLastName(),
                "isActive" => $user->get("isActive"),
                "accountRole" => $entityUser->get("role"),
                "accountRoleId" => $entityUser->get("roleId"),
                "accountSynced" => $entityUser->get("synced"),
                "middleName" => $user->getMiddleName(),
                "createdById" => $user->get("createdById")
            ];
        }

        return ResponseComposer::json([
            "total" => count($list),
            "list" => $list
        ]);
    }
}
