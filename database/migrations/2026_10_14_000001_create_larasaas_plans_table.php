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
    }

    public function down(): void
    {
        Schema::dropIfExists('larasaas_plans');
    }
};
