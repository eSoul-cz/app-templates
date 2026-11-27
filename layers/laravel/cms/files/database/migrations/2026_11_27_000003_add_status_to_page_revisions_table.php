<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_revisions', function (Blueprint $table): void {
            // Every revision that exists is content that was applied to its page.
            $table->string('status', 16)->default('applied')->after('source');
            $table->index(['page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('page_revisions', function (Blueprint $table): void {
            $table->dropIndex(['page_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
