<?php

namespace App\Filament\Widgets;

use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * The activation funnel, from signing up to agreeing a business rule.
 *
 * The admin dashboard previously showed Filament's stock widgets: a greeting
 * and a link to Filament's own documentation. Neither says anything about
 * thePBR, so answering "how many people actually got value?" meant opening a
 * tinker session on production.
 *
 * The last step is the one that matters. thePBR's promise is not that someone
 * opened a calculator — it is that partners agreed a rule and it became their
 * operating state. A wide gap between "used a tool" and "approved a rule"
 * means people are exploring but not committing, which is a product problem no
 * amount of traffic will fix.
 */
class ActivationFunnelWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Activation funnel';

    protected ?string $description = 'How far people get, from account to agreed business rule.';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $accounts = User::query()->count();

        // Entitled: holds an enrollment that is active and not expired. Mirrors
        // StudentEnrollment::isActive() rather than counting rows with
        // status='active', which would include lapsed access.
        $entitledIds = StudentEnrollment::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>', now());
            })
            ->distinct('user_id')
            ->pluck('user_id');

        // Admins can reach the Business OS without a student enrolment, so
        // counting enrolments alone leaves an admin-owned workspace with no
        // corresponding entitled person and skews the ratio above 100%.
        $adminIds = User::query()
            ->where('role', 'admin')
            ->where('account_status', 'active')
            ->pluck('id');

        $entitled = $entitledIds->merge($adminIds)->unique()->count();

        // OWNERS, not workspaces. One student can own several — in practice
        // one owns eight — so counting workspaces against a count of people
        // produced "180% of entitled", which is nonsense and destroys trust
        // in every other number on the panel.
        $owners = PartnershipWorkspace::query()
            ->where('status', 'active')
            ->distinct('owner_user_id')
            ->count('owner_user_id');

        $workspaceTotal = PartnershipWorkspace::query()
            ->where('status', 'active')
            ->count();

        // Any snapshot at all means a tool was used and saved, draft or not.
        $withDraft = WorkspaceOperatingSnapshot::query()
            ->distinct('workspace_id')
            ->count('workspace_id');

        // The real number: a workspace that has agreed at least one rule.
        $withAgreed = WorkspaceOperatingSnapshot::query()
            ->where('status', 'agreed')
            ->distinct('workspace_id')
            ->count('workspace_id');

        return [
            Stat::make('Accounts', number_format($accounts))
                ->description('Registered users')
                ->color('gray'),

            Stat::make('Entitled', number_format($entitled))
                ->description($this->rate($entitled, $accounts).' of accounts')
                ->color($entitled > 0 ? 'info' : 'gray'),

            Stat::make('Created a business', number_format($owners))
                ->description(
                    $this->rate($owners, $entitled).' of entitled · '
                    .number_format($workspaceTotal).' workspaces'
                )
                ->color($owners > 0 ? 'info' : 'gray'),

            Stat::make('Used a tool', number_format($withDraft))
                ->description($this->rate($withDraft, $workspaceTotal).' of workspaces')
                ->color($withDraft > 0 ? 'warning' : 'gray'),

            Stat::make('Agreed a rule', number_format($withAgreed))
                ->description($this->rate($withAgreed, $withDraft).' of those that tried')
                // Deliberately the only stat that can turn green. Everything
                // before it is a step toward this one.
                ->color($withAgreed > 0 ? 'success' : 'danger'),
        ];
    }

    /**
     * Percentage of one step against the one before it.
     *
     * Returns an em dash rather than 0% when the denominator is zero: "0% of
     * entitled" when nobody is entitled reads as a failure, when in fact
     * there is simply nothing to measure yet.
     */
    private function rate(int $part, int $whole): string
    {
        if ($whole <= 0) {
            return '—';
        }

        return round($part / $whole * 100).'%';
    }
}
