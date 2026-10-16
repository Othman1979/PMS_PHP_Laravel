<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->string('description', 500)->nullable();
            $table->string('default_for_role', 30)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('user_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);
            $table->unique(['user_group_id', 'permission']);
        });

        Schema::create('user_user_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'user_group_id']);
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dateTime('archived_at')->nullable()->index()->after('closed_at');
            $table->dateTime('cancelled_at')->nullable()->after('archived_at');
            $table->foreignId('cancelled_by_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 1000)->nullable()->after('cancelled_by_id');
        });

        UserGroup::seedDefaults();
        foreach (Role::cases() as $role) {
            $group = UserGroup::defaultFor($role);
            if ($group === null) {
                continue;
            }
            User::query()->where('role', $role->value)->pluck('id')
                ->each(fn (int $id) => $group->users()->syncWithoutDetaching([$id]));
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_id');
            $table->dropColumn(['archived_at', 'cancelled_at', 'cancel_reason']);
        });
        Schema::dropIfExists('user_user_group');
        Schema::dropIfExists('user_group_permissions');
        Schema::dropIfExists('user_groups');
    }
};
