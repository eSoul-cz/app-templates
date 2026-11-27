<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            // The user and the client are kept as plain values: the log outlives both.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('tool');
            $table->json('arguments');
            $table->string('status', 20);
            $table->text('detail')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->dateTime('called_at');

            $table->index(['user_id', 'called_at'], 'ai_tool_calls_user_called_idx');
            $table->index('called_at', 'ai_tool_calls_called_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_calls');
    }
};
