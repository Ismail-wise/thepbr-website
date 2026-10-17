<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserAccess\UserAccessResource;
use App\Models\User;
use App\Services\Portal\StudentPortalAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PortalAccessWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $heading = 'Student Portal Access';

    public static function canView(): bool
    {
        return config('partner_portal.enabled') && auth()->user()?->isAdmin();
    }

    protected function getStats(): array
    {
        $stats = [];
        foreach (['pending' => 'Pending Approval', 'active' => 'Active Users', 'expiring' => 'Expiring in 7 Days'] as $status => $label) {
            $query = User::query()->where('is_admin', false)->where('role', '!=', 'admin');
            $count = app(StudentPortalAccess::class)->queryForStatus($query, $status)->count();
            $stats[] = Stat::make($label, number_format($count))->url(UserAccessResource::getUrl('index', [
                'tableFilters' => ['portal_status' => ['value' => $status]],
            ], panel: 'admin'))->color($status === 'active' ? 'success' : 'warning');
        }

        return $stats;
    }
}
