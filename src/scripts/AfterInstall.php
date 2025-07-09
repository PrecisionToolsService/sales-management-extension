<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Log;

class AfterInstall
{
    protected $container;

    public function run($container)
    {
        $this->container = $container;
        $log = $container->getByClass(Log::class);
        $config = $container->getByClass(Config::class);

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $list = $config->get('clientCspScriptSourceList') ?? [];

        // 追加したいドメイン
        $requiredDomains = [
            'https://cdn.onesignal.com',
            'https://onesignal.com',
            'https://api.onesignal.com'
        ];



        // 必要なドメインがなければ追加
        foreach ($requiredDomains as $domain) {
            if (!in_array($domain, $list, true)) {
                $list[] = $domain;
            }
        }

        // 更新
        $configWriter->set('clientCspScriptSourceList', $list);
        $configWriter->save();
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {
        }
    }
}
