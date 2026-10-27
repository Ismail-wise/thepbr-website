<?php

require_once __DIR__.'/../../scripts/pbr-business-plan-check-support.php';

use Pbr\Deployment\BusinessPlanSchema;

test('schema snapshots treat redundant MySQL column charset decoration as the same definition', function (): void {
    $before = "CREATE TABLE `example` (\n  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL\n) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $restored = str_replace(' COLLATE utf8mb4_unicode_ci NOT NULL', ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL', $before);
    expect(BusinessPlanSchema::normalize($restored))->toBe(BusinessPlanSchema::normalize($before));
    expect(BusinessPlanSchema::normalize(str_replace('utf8mb4_unicode_ci NOT NULL', 'utf8mb4_bin NOT NULL', $restored)))
        ->not->toBe(BusinessPlanSchema::normalize($before));
});

test('only a migration journal may advance its sequence in a schema snapshot', function (): void {
    $before = 'CREATE TABLE `example` (`id` bigint NOT NULL) ENGINE=InnoDB AUTO_INCREMENT=4';
    $after = str_replace('AUTO_INCREMENT=4', 'AUTO_INCREMENT=5', $before);
    expect(BusinessPlanSchema::normalize($after))->not->toBe(BusinessPlanSchema::normalize($before));
    expect(BusinessPlanSchema::normalize($after, true))->toBe(BusinessPlanSchema::normalize($before, true));
});

test('schema normalization still detects meaningful changes to existing tables', function (array $changed): void {
    $before = "CREATE TABLE `example` (\n  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Original',\n  UNIQUE KEY `unique_title` (`title`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    expect(BusinessPlanSchema::normalize(str_replace($changed[0], $changed[1], $before)))->not->toBe(BusinessPlanSchema::normalize($before));
})->with([
    'type' => [['varchar(160)', 'text']],
    'nullability' => [['NOT NULL', 'NULL']],
    'default' => [["DEFAULT 'Original'", "DEFAULT 'Changed'"]],
    'index' => [['UNIQUE KEY', 'KEY']],
    'table collation' => [['COLLATE=utf8mb4_unicode_ci', 'COLLATE=utf8mb4_bin']],
]);
