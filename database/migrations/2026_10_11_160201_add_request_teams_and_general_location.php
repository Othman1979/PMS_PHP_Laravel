<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('location', 255)->nullable();
        });

        Schema::create('request_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('worked_at')->nullable();
            $table->boolean('worked_as_lead')->default(false);
            $table->timestamps();
            $table->unique(['request_id', 'user_id']);
            $table->index(['user_id', 'worked_at']);
        });

        DB::table('maintenance_requests')->whereNotNull('assigned_technician_id')
            ->orderBy('id')->chunkById(500, function ($requests) {
                foreach ($requests as $request) {
                    DB::table('request_technicians')->insert([
                        'request_id' => $request->id,
                        'user_id' => $request->assigned_technician_id,
                        'confirmed_at' => $request->accepted_at,
                        'worked_at' => $request->completed_at,
                        'worked_as_lead' => $request->completed_at !== null,
                        'created_at' => $request->assigned_at ?? $request->created_at,
                        'updated_at' => $request->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_technicians');
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
