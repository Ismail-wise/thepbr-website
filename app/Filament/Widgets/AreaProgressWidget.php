<?php

namespace App\Filament\Widgets;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceOperatingSnapshot;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Where people stop, broken down by business area.
 *
 * The funnel widget says how many reached each stage. This says which of the
 * ten areas they got stuck in, which is the part that tells you what to fix.
 *
 * An area with many drafts and few agreements means people are willing to
 * calculate but not to commit — usually a trust or clarity problem in that
 * specific tool, not a general one. An area with neither means nobody is
 * reaching it at all, which is a navigation problem instead.
 */
class AreaProgressWidget extends TableWidget
{
    protected static ?string $heading = 'Progress by business area';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->areaRows())
            ->columns([
                TextColumn::make('area')
                    ->label('Business area'),

                TextColumn::make('draft_workspaces')
                    ->label('Drafting')
                    ->alignEnd(),

                TextColumn::make('agreed_workspaces')
                    ->label('Agreed')
                    ->alignEnd()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('conversion')
                    ->label('Draft → agreed')
                    ->alignEnd()
                    ->color(fn ($record): string => match (true) {
                        $record['agreed_workspaces'] > 0 => 'success',
                        $record['draft_workspaces'] > 0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No workspace activity yet')
            ->emptyStateDescription(
                'Rows appear once a workspace saves its first tool result.'
            );
    }

    /**
     * One row per configured domain, ordered by the areas with the widest gap
     * between drafting and agreeing — the places most worth investigating.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function areaRows(): Collection
    {
        $domains = config('pbr_canonical_data.domains', []);

        if ($domains === []) {
            return collect();
        }

        // Two grouped queries rather than one per domain: ten domains would
        // otherwise mean twenty queries on a dashboard that loads on every
        // admin page view.
        $drafting = $this->countByDomain(null);
        $agreed = $this->countByDomain('agreed');

        return collect($domains)
            ->map(function (array $domain, string $key) use ($drafting, $agreed): array {
                $d = (int) ($drafting[$key] ?? 0);
                $a = (int) ($agreed[$key] ?? 0);

                return [
                    'id' => $key,
                    // The config key is 'name', not a label_* pair — verified
                    // against config/pbr_canonical_data.php rather than assumed.
                    'area' => $domain['name'] ?? $key,
                    'chapter' => (int) ($domain['chapter'] ?? 99),
                    'draft_workspaces' => $d,
                    'agreed_workspaces' => $a,
                    'conversion' => $d > 0 ? round($a / $d * 100).'%' : '—',
                    'gap' => $d - $a,
                ];
            })
            ->sortByDesc('gap')
            ->values();
    }

    /**
     * Distinct workspaces per domain, optionally filtered by snapshot status.
     *
     * @return array<string, int>
     */
    private function countByDomain(?string $status): array
    {
        $query = WorkspaceOperatingSnapshot::query()
            ->selectRaw('domain_key, COUNT(DISTINCT workspace_id) as total')
            ->groupBy('domain_key');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->pluck('total', 'domain_key')->all();
    }
}
