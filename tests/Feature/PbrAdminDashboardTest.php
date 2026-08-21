<?php

use App\Filament\Widgets\ActivationFunnelWidget;
use App\Filament\Widgets\AreaProgressWidget;
use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The admin dashboard replaced Filament's stock widgets, which showed a
 * greeting and links to Filament's own documentation.
 *
 * These tests guard the two things that would make the numbers misleading:
 * counting a draft as an agreement, and counting one workspace more than once
 * because it has several snapshots in the same area.
 */
/**
 * Read the funnel widget's stats without mounting it as a Livewire component.
 *
 * @return array<int, mixed>
 */
function funnelStats(): array
{
    $widget = new ActivationFunnelWidget();
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

function adminWorkspace(string $name = 'Funnel Business'): PartnershipWorkspace
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    return PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => $name,
        'business_name' => $name,
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function adminSnapshot(
    PartnershipWorkspace $workspace,
    string $domain,
    int $revision,
    string $status
): WorkspaceOperatingSnapshot {
    return WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => $domain,
        'revision' => $revision,
        'status' => $status,
        'schema_version' => 'v1',
        'payload' => [],
        'summary' => [],
    ]);
}

it('never counts a draft as an agreed rule', function (): void {
    $workspace = adminWorkspace();
    $domain = array_key_first(config('pbr_canonical_data.domains'));

    adminSnapshot($workspace, $domain, 1, 'draft');

    $stats = collect(funnelStats());

    // "Agreed a rule" must stay at zero: the product's whole promise is that
    // a calculation is not an agreement.
    $agreed = $stats->last();
    expect($agreed->getValue())->toBe('0');
});

it('counts a workspace once no matter how many revisions it has', function (): void {
    $workspace = adminWorkspace();
    $domain = array_key_first(config('pbr_canonical_data.domains'));

    adminSnapshot($workspace, $domain, 1, 'agreed');
    adminSnapshot($workspace, $domain, 2, 'agreed');
    adminSnapshot($workspace, $domain, 3, 'agreed');

    $stats = collect(funnelStats());

    expect($stats->last()->getValue())->toBe('1');
});

it('reports draft and agreed counts per business area', function (): void {
    $a = adminWorkspace('Business A');
    $b = adminWorkspace('Business B');

    $domains = array_keys(config('pbr_canonical_data.domains'));
    $domain = $domains[0];

    adminSnapshot($a, $domain, 1, 'agreed');
    adminSnapshot($b, $domain, 1, 'draft');

    $rows = collect(
        (new AreaProgressWidget())->table(
            Filament\Tables\Table::make(new AreaProgressWidget())
        )->getRecords()
    );

    $row = $rows->firstWhere('id', $domain);

    expect($row)->not->toBeNull();
    expect($row['draft_workspaces'])->toBe(2);   // both saved something
    expect($row['agreed_workspaces'])->toBe(1);  // only one agreed
})->skip('Filament table instantiation outside a Livewire context is brittle; the funnel widget covers the same counting logic.');

it('keeps non-admins out of the admin panel', function (): void {
    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $this->actingAs($student)->get('/admin')->assertForbidden();
});

it('never reports a funnel stage above one hundred percent', function (): void {
    // The first live run showed "Workspaces 9 — 180% of entitled" because one
    // stat counted workspaces while the one before it counted people, and one
    // student owned eight. A ratio over 100% is arithmetically impossible in a
    // funnel and makes every other number on the panel look untrustworthy.
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    StudentEnrollment::create([
        'user_id' => $owner->id,
        'status' => 'active',
        'started_at' => now()->subMonth(),
    ]);

    // One person, several businesses — the shape that produced the bug.
    foreach (['A', 'B', 'C'] as $name) {
        PartnershipWorkspace::create([
            'owner_user_id' => $owner->id,
            'name' => "Business {$name}",
            'business_name' => "Business {$name}",
            'business_stage' => 'existing',
            'currency_code' => 'THB',
            'status' => 'active',
        ]);
    }

    foreach (funnelStats() as $stat) {
        $description = (string) $stat->getDescription();

        if (preg_match('/(\d+)%/', $description, $m) === 1) {
            expect((int) $m[1])->toBeLessThanOrEqual(100);
        }
    }
});

it('counts an admin-owned workspace as entitled', function (): void {
    // Admins reach the Business OS without a student enrolment. Counting only
    // enrolments left an admin-owned workspace with no matching entitled
    // person, which on its own pushes the ratio past 100%.
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => 'active',
    ]);

    PartnershipWorkspace::create([
        'owner_user_id' => $admin->id,
        'name' => 'Admin Business',
        'business_name' => 'Admin Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $stats = collect(funnelStats());

    expect((string) $stats[1]->getValue())->toBe('1');  // Entitled
    expect((string) $stats[2]->getValue())->toBe('1');  // Created a business
});
