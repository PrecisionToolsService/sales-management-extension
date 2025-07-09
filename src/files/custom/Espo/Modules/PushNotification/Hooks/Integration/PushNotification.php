<?php

namespace Espo\Modules\PushNotification\Hooks\Integration;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;

use Espo\Entities\Integration;
use Espo\Core\Utils\Log;

use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\Repository\Option\SaveOptions;

class PushNotification implements AfterSave
{
    private ConfigWriter $configWriter;

    public function __construct(ConfigWriter $configWriter, private Log $log,)
    {
        $this->configWriter = $configWriter;
    }

    /**
     * @param Integration $entity
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getId() !== 'PushNotification') {
            return;
        }

        $apiKey = $entity->get('apiKey');
        $appId = $entity->get('appId');
        $safariId = $entity->get('safariId');

        if (!$entity->isEnabled()) {
            $this->configWriter->remove('onesignalApiKey');
            $this->configWriter->remove('onesignalAppId');
            $this->configWriter->remove('onesignalSafariId');
        } else {
            $this->configWriter->set('onesignalApiKey', $apiKey);
            $this->configWriter->set('onesignalAppId', $appId);
            $this->configWriter->set('onesignalSafariId', $safariId);
        }

        // 更新を反映
        $this->configWriter->save();
    }
}
