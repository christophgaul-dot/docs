<?php

declare(strict_types=1);

namespace WG\Faq\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1711200000CreateFaqTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711200000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `wg_faq` (
                `id`                BINARY(16)      NOT NULL,
                `active`            TINYINT(1)      NOT NULL DEFAULT 1,
                `position`          INT(11)         NOT NULL DEFAULT 0,
                `show_on_home`      TINYINT(1)      NOT NULL DEFAULT 0,
                `show_on_category`  TINYINT(1)      NOT NULL DEFAULT 0,
                `show_on_product`   TINYINT(1)      NOT NULL DEFAULT 0,
                `show_on_faq_page`  TINYINT(1)      NOT NULL DEFAULT 1,
                `category_ids`      JSON            NULL,
                `media_id`          BINARY(16)      NULL,
                `video_url`         VARCHAR(1024)   NULL,
                `video_type`        VARCHAR(32)     NULL,
                `created_at`        DATETIME(3)     NOT NULL,
                `updated_at`        DATETIME(3)     NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.wg_faq.media_id` FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `wg_faq_translation` (
                `wg_faq_id`         BINARY(16)      NOT NULL,
                `language_id`       BINARY(16)      NOT NULL,
                `question`          VARCHAR(1024)   NOT NULL,
                `answer`            LONGTEXT        NOT NULL,
                `seo_url`           VARCHAR(512)    NULL,
                `meta_title`        VARCHAR(255)    NULL,
                `meta_description`  VARCHAR(512)    NULL,
                `created_at`        DATETIME(3)     NOT NULL,
                `updated_at`        DATETIME(3)     NULL,
                PRIMARY KEY (`wg_faq_id`, `language_id`),
                CONSTRAINT `fk.wg_faq_translation.wg_faq_id` FOREIGN KEY (`wg_faq_id`)
                    REFERENCES `wg_faq` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.wg_faq_translation.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        // Insert default SEO URL template
        $connection->executeStatement("
            INSERT IGNORE INTO `seo_url_template` (`id`, `route_name`, `entity_name`, `template`, `created_at`)
            VALUES (
                UNHEX(REPLACE(UUID(), '-', '')),
                'frontend.wg.faq.detail',
                'wg_faq',
                'faq/{{ faq.translated.seoUrl ?? faq.translated.question|slugify }}',
                NOW()
            );
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
