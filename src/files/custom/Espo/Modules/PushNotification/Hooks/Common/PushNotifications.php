<?php

namespace Espo\Modules\PushNotification\Hooks\Common;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Modules\PushNotification\Tools\HookProcessor;
use Espo\Core\Utils\Log;
use Espo\Entities\Note;
use Espo\ORM\Entity;

class PushNotifications implements AfterSave
{
    public static int $order = 13;

    private HookProcessor $processor;

    public function __construct(HookProcessor $processor, private Log $log,)
    {
        $this->processor = $processor;
    }

    /**
     * @param SaveOptions $options
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SILENT) || $options->get(SaveOption::NO_NOTIFICATIONS)) {
            return;
        }

        if ($entity->getEntityType() !== Note::ENTITY_TYPE) return;

        $this->processor->sendPushNotification($entity);
    }
}
