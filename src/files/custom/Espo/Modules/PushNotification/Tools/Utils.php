<?php

namespace Espo\Modules\PushNotification\Tools;

use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\TemplateFileManager;

/**
 * Handles operations with entities.
 */
class Utils
{

    public function __construct(
        private Metadata $metadata,
        private Config $config,
        private TemplateFileManager $templateFileManager,
        private EntityManager $entityManager,
        private Log $log,
    ) {}

    public function getUserNameById(string $userId, string $key = "userName"): string
    {
        $user = $this->entityManager->getEntityById('User', $userId);
        return $user->get($key);
    }
}
