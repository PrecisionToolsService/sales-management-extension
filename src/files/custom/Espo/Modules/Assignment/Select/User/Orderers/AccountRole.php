<?php

namespace Espo\Modules\Assignment\Select\User\Orderers;

use Espo\Core\Select\Order\Item;
use Espo\Core\Select\Order\Orderer;
use Espo\ORM\Query\SelectBuilder;

class AccountRole implements Orderer
{
    public function apply(SelectBuilder $queryBuilder, Item $item): void
    {
        if (!$queryBuilder->hasJoinAlias('entityUser')) {
            return;
        }

        $queryBuilder
            ->order('entityUser.role', $item->getOrder())
            ->order('entityUser.roleId', $item->getOrder())
            ->order('name', $item->getOrder());
    }
}
