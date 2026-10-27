<?php

namespace Pbr\Deployment;

final class BusinessPlanSchema
{
    public static function normalize(string $sql, bool $migrationJournal = false): string
    {
        // MySQL may reprint a column's charset explicitly after a dump restore.
        // Its COLLATE still identifies the charset; retain that exact collation.
        $lines = explode("\n", $sql);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '  `')) {
                $line = preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE /', ' COLLATE ', $line);
            }
        }
        unset($line);
        $sql = implode("\n", $lines);
        if ($migrationJournal) {
            $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
        }

        return $sql;
    }
}
