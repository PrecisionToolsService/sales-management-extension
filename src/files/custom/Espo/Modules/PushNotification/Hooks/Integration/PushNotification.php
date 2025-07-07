<?php

namespace Espo\Modules\PushNotification\Hooks\Integration;

use Espo\ORM\Entity;

use Espo\Entities\Integration;
use Espo\Core\Utils\Log;

use Espo\Core\Utils\Config\ConfigWriter;

class PushNotification
{
    private ConfigWriter $configWriter;

    public function __construct(ConfigWriter $configWriter, private Log $log,)
    {
        $this->configWriter = $configWriter;
    }

    /**
     * @param Integration $entity
     */
    public function afterSave(Entity $entity): void
    {
        if ($entity->getId() !== 'PushNotification') {
            return;
        }

        $apiKey = $entity->get('apiKey');
        $appId = $entity->get('appId');

        if (!$entity->isEnabled()) {
            $apiKey = null;
            $appId = null;
        }
        $this->configWriter->set('onesignalApiKey', $apiKey);
        $this->configWriter->set('onesignalAppId', $appId);

        $this->configWriter->save();
    }
}
