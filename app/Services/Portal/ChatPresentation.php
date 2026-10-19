<?php

namespace App\Services\Portal;

class ChatPresentation
{
    public static function answer(string $text): string
    {
        // Hide machine source labels in presentation only. Original messages
        // and authorized citation metadata remain intact for follow-up turns.
        $marker = '(?:\[S\d+(?:[ \t]*[,;][ \t]*S\d+)*\]|\(S\d+(?:[ \t]*[,;][ \t]*S\d+)*\))';

        return preg_replace('/[ \t]*'.$marker.'(?:[ \t]*[,;]?[ \t]*'.$marker.')*/u', '', $text) ?? $text;
    }
}
