<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('password')->index();
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 30)->default('public')->change();
            });
        } else {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(30) NOT NULL DEFAULT 'public'");
        }

        DB::table('users')
            ->where('role', 'admin')
            ->orWhere('email', 'aiautono247@gmail.com')
            ->update(['is_admin' => true]);

        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_session_id')
                ->nullable()
                ->constrained('class_sessions')
                ->nullOnDelete();
            $table->foreignId('student_access_code_id')
                ->nullable()
                ->unique()
                ->constrained('student_access_codes')
                ->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('access_expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['user_id', 'class_session_id'],
                'student_enrollments_user_class_unique',
            );
        });

        Schema::create('partnership_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('business_name', 160)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained('partnership_workspaces')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('member_role', 20)->default('partner')->index();
            $table->string('invitation_status', 20)->default('pending')->index();
            $table->string('invited_email')->nullable()->index();
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'user_id'],
                'workspace_members_workspace_user_unique',
            );
        });

        $now = now();
        $users = DB::table('users')->orderBy('id')->get();

        foreach ($users as $user) {
            $isAdmin = (bool) $user->is_admin;
            $isLegacyStudent = $user->role === 'student';

            if ($isLegacyStudent) {
                $usedCode = DB::table('student_access_codes')
                    ->where('used_by_user_id', $user->id)
                    ->first();

                DB::table('student_enrollments')->insert([
                    'user_id' => $user->id,
                    'class_session_id' => $user->class_session_id,
                    'student_access_code_id' => $usedCode?->id,
                    'status' => $user->account_status ?: 'active',
                    'started_at' => $usedCode?->used_at ?? $user->created_at ?? $now,
                    'access_expires_at' => $user->portal_access_expires_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! $isAdmin && ! $isLegacyStudent) {
                continue;
            }

            $workspaceId = DB::table('partnership_workspaces')->insertGetId([
                'owner_user_id' => $user->id,
                'name' => $isAdmin
                    ? $user->name.' — Admin Workspace'
                    : $user->name.' — My PBR Workspace',
                'business_name' => null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('workspace_members')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'member_role' => 'owner',
                'invitation_status' => 'accepted',
                'invited_email' => strtolower($user->email),
                'invitation_token_hash' => null,
                'invited_by_user_id' => $user->id,
                'invited_at' => $now,
                'accepted_at' => $now,
                'permissions' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('partnership_workspaces');
        Schema::dropIfExists('student_enrollments');

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 30)->default('student')->change();
            });
        } else {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(30) NOT NULL DEFAULT 'student'");
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_admin']);
            $table->dropColumn('is_admin');
        });
    }
};
