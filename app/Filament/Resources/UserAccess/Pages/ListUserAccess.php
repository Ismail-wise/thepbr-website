<?php

namespace App\Filament\Resources\UserAccess\Pages;

use App\Filament\Resources\UserAccess\UserAccessResource;
use Filament\Resources\Pages\ListRecords;

class ListUserAccess extends ListRecords
{
    protected static string $resource = UserAccessResource::class;
}
