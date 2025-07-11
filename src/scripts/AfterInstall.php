<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;

class AfterInstall
{
    protected $container;
    protected $config;
    protected $entityManager;
    protected $log;

    public function run($container)
    {
        $this->container = $container;
        $this->config = $container->getByClass(Config::class);
        $this->entityManager = $container->getByClass(EntityManager::class);
        $this->log = $container->getByClass(Log::class);

        $this->updateIntegrationEntity();
        $this->updateCspConfig();
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {
        }
    }

    /**
     * ContentSecurityPolicyの一覧をconfig.phpに追加
     */
    private function updateCspConfig()
    {
        $list = $this->config->get('clientCspScriptSourceList') ?? [];

        // CSPに追加したいドメイン
        $requiredDomains = [
            'https://cdn.onesignal.com',
            'https://onesignal.com',
            'https://api.onesignal.com'
        ];

        // ドメインをリストに追加
        foreach ($requiredDomains as $domain) {
            if (!in_array($domain, $list, true)) {
                $list[] = $domain;
            }
        }

        // Config更新
        $configWriter = $this->container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        $configWriter->set('clientCspScriptSourceList', $list);
        $configWriter->save();
    }

    /**
     * Configを元にIntegrationエンティティを更新
     */
    private function updateIntegrationEntity()
    {
        $entity = $this->entityManager->getEntityById('Integration', 'PushNotification');
        if (!$entity) {
            throw new NotFound();
        }
        $appId = $this->config->get('onesignalAppId');
        $apiKey = $this->config->get('onesignalApiKey');
        $safariId = $this->config->get('onesignalSafariId');
        $entity->setMultiple([
            'appId' => $appId,
            'apiKey' => $apiKey,
            'safariId' => $safariId,
            'enabled' => !empty($appId),
            'deleted' => false
        ]);
        $this->entityManager->saveEntity($entity);
    }
}
