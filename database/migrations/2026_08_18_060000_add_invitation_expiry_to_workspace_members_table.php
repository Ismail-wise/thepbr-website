<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_members', function (Blueprint $table): void {
            $table->timestamp('invitation_expires_at')
                ->nullable()
                ->after('invited_at')
                ->index();
        });

        DB::table('workspace_members')
            ->where('invitation_status', 'pending')
            ->where('invited_email', 'like', '%@invite.thepbr.local')
            ->update([
                'invitation_status' => 'revoked',
                'invitation_token_hash' => null,
            ]);

        DB::table('workspace_members')
            ->where('invitation_status', 'pending')
            ->whereNull('invitation_expires_at')
            ->update([
                'invitation_expires_at' => now()->addDays(7),
            ]);
    }

    public function down(): void
    {
        Schema::table('workspace_members', function (Blueprint $table): void {
            $table->dropIndex(['invitation_expires_at']);
            $table->dropColumn('invitation_expires_at');
        });
    }
};
