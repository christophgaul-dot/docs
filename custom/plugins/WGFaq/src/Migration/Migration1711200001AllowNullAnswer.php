<?php

declare(strict_types=1);

namespace WG\Faq\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1711200001AllowNullAnswer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711200001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `wg_faq_translation`
            MODIFY COLUMN `answer` LONGTEXT NULL DEFAULT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
