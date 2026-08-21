<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Author Name
    |--------------------------------------------------------------------------
    |
    | thePBR publishes under a single author, so the byline lives here rather
    | than as a column on every article. It was previously hardcoded in
    | articles/show.blade.php, where it had drifted out of step with the
    | instructor named on the homepage.
    |
    | If the site ever takes guest writers, this becomes an author_id on the
    | articles table instead — but a column that would hold the same value on
    | every row is not worth carrying until that happens.
    |
    */

    'author_name' => env('PBR_AUTHOR_NAME', 'Nyan Lin Aung'),

];
