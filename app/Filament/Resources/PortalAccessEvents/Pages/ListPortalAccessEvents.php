<?php

namespace App\Filament\Resources\PortalAccessEvents\Pages;

use App\Filament\Resources\PortalAccessEvents\PortalAccessEventResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalAccessEvents extends ListRecords
{
    protected static string $resource = PortalAccessEventResource::class;
}
