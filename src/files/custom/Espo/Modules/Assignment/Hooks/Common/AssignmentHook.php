<?php

namespace Espo\Modules\Assignment\Hooks\Common;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Core\Utils\Log;
use Espo\Entities\Note;
use Espo\ORM\Entity;

class AssignmentHook implements AfterSave
{
    public static int $order = 13;

    public function __construct(private Log $log,){}

    /**
     * @param SaveOptions $options
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if($entity->getEntityType() !== Note::ENTITY_TYPE) return;

        

        $this->log->warning("AssignmentExtension: ".$entity->getEntityType());
    }
}
