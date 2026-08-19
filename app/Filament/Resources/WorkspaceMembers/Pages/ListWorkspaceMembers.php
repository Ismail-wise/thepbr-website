<?php

namespace App\Filament\Resources\WorkspaceMembers\Pages;

use App\Filament\Resources\WorkspaceMembers\WorkspaceMemberResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaceMembers extends ListRecords
{
    protected static string $resource = WorkspaceMemberResource::class;
}
