<?php

declare(strict_types=1);

namespace WG\Faq;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class WGFaq extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get('Doctrine\DBAL\Connection');
        $connection->executeStatement('DROP TABLE IF EXISTS `wg_faq_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `wg_faq`');
        $connection->executeStatement('DELETE FROM `seo_url` WHERE `route_name` = :route', [
            'route' => 'frontend.wg.faq.detail',
        ]);
        $connection->executeStatement('DELETE FROM `seo_url_template` WHERE `route_name` = :route', [
            'route' => 'frontend.wg.faq.detail',
        ]);
    }
}
