<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'partnership_workspaces',
            function (Blueprint $table): void {
                $table->string(
                    'business_stage',
                    20
                )
                    ->nullable()
                    ->after('business_name')
                    ->index();

                $table->char(
                    'currency_code',
                    3
                )
                    ->nullable()
                    ->after('business_stage');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'partnership_workspaces',
            function (Blueprint $table): void {
                $table->dropIndex(
                    ['business_stage']
                );

                $table->dropColumn([
                    'business_stage',
                    'currency_code',
                ]);
            }
        );
    }
};
