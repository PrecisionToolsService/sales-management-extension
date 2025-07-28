<?php

namespace Espo\Modules\Assignment\Tools\Account\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Assignment\Tools\Account\MembersService;

class PostMembers implements Action
{
    public function __construct(
        private Acl $acl,
        private EntityProvider $entityProvider,
        private MembersService $service,
    ) {}

    /**
     * @inheritDoc
     */
    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();
        $ids = $request->getParsedBody()->ids ?? null;
        $role = $request->getParsedBody()->role ?? null;

        if (!property_exists($request->getParsedBody(), 'role')) {
            throw new BadRequest("No 'role'");
        }

        if (!is_string($role) && $role !== null) {
            throw new BadRequest("Bad 'role'");
        }

        if (!is_array($ids)) {
            throw new BadRequest("No or bad 'ids'");
        }

        foreach ($ids as $itId) {
            if (!is_string($itId)) {
                throw new BadRequest("Bad 'ids'");
            }
        }

        if (!$this->acl->checkScope(Account::ENTITY_TYPE)) {
            throw new Forbidden("No access to Account scope.");
        }
        
        /** @var \Espo\Modules\Assignment\Entities\Account $account */
        $account = $this->entityProvider->getByClass(Account::class, $id);

        if (!$this->acl->checkEntityEdit($account)) {
            throw new Forbidden("No 'edit' access.");
        }

        $this->service->link($account, $ids, $role);

        return ResponseComposer::json(true);
    }
}
