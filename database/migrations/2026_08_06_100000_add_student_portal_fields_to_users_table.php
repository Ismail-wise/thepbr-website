<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('role', 30)->default('student')->after('password')->index();
            $table->foreignId('class_session_id')
                ->nullable()
                ->after('role')
                ->constrained('class_sessions')
                ->nullOnDelete();
            $table->string('account_status', 20)->default('active')->after('class_session_id')->index();
            $table->timestamp('portal_access_expires_at')->nullable()->after('account_status');
        });

        DB::table('users')
            ->where('email', 'aiautono247@gmail.com')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_session_id');
            $table->dropIndex(['role']);
            $table->dropIndex(['account_status']);
            $table->dropColumn([
                'phone',
                'role',
                'account_status',
                'portal_access_expires_at',
            ]);
        });
    }
};
