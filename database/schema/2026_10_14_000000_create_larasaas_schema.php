<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasaas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->boolean('highlight')->default(false);
            $table->string('badge', 80)->nullable();
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->json('monthly')->nullable();
            $table->json('yearly')->nullable();
            $table->json('features');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('larasaas_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 140);
            $table->text('body');
            $table->string('type', 32)->default('info');
            $table->string('color', 9)->nullable();   // hex override for the type color
            $table->string('icon', 60)->nullable();   // icon override for the type icon
            $table->string('action_label', 60)->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('dismissible')->default(true);
            $table->boolean('active')->default(true);
            $table->json('audience')->nullable();     // { "emails": [...], "workspaces": [...] }
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'starts_at', 'ends_at']);
        });

        Schema::create('larasaas_announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('larasaas_announcements')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index(); // references the host user model
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasaas_plans');
        Schema::dropIfExists('larasaas_announcement_dismissals');
        Schema::dropIfExists('larasaas_announcements');
    }
};
