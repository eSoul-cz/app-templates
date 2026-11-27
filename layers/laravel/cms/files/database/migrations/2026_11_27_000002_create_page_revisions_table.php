<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table): void {
            $table->id();
            // Pages are soft deleted; restrict makes sure nothing hard-deletes a page and takes its history with it.
            $table->foreignId('page_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->boolean('noindex')->default(false);
            $table->string('og_image')->nullable();
            $table->json('blocks')->nullable();
            $table->boolean('is_published')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16);
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['page_id', 'id']);
        });

        // A page that exists before this migration gets a baseline revision dated at its last update, so that its
        // history starts at the current content and the page does not count as changed since the last publish.
        DB::table('pages')->orderBy('id')->each(static function (object $page): void {
            DB::table('page_revisions')->insert([
                'page_id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'description' => $page->description,
                'noindex' => $page->noindex,
                'og_image' => $page->og_image,
                'blocks' => $page->blocks,
                'is_published' => $page->is_published,
                'user_id' => null,
                'source' => 'system',
                'created_at' => $page->updated_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
