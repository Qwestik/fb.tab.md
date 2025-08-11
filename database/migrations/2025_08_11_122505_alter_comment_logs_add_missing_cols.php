<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Coloane – doar dacă lipsesc
        Schema::table('comment_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('comment_logs','channel_id')) {
                $table->unsignedBigInteger('channel_id')->after('id');
            }
            if (!Schema::hasColumn('comment_logs','post_id')) {
                $table->unsignedBigInteger('post_id')->nullable()->after('channel_id');
            }
            if (!Schema::hasColumn('comment_logs','post_channel_id')) {
                $table->unsignedBigInteger('post_channel_id')->nullable()->after('post_id');
            }
            if (!Schema::hasColumn('comment_logs','page_id')) {
                $table->string('page_id', 64)->after('post_channel_id');
            }
            if (!Schema::hasColumn('comment_logs','post_fb_id')) {
                $table->string('post_fb_id', 64)->after('page_id');
            }
            if (!Schema::hasColumn('comment_logs','comment_id')) {
                $table->string('comment_id', 64)->after('post_fb_id');
            }
            if (!Schema::hasColumn('comment_logs','from_id')) {
                $table->string('from_id', 64)->nullable()->after('comment_id');
            }
            if (!Schema::hasColumn('comment_logs','message')) {
                $table->text('message')->nullable()->after('from_id');
            }
            if (!Schema::hasColumn('comment_logs','reply')) {
                $table->text('reply')->nullable()->after('message');
            }
            if (!Schema::hasColumn('comment_logs','reply_id')) {
                $table->string('reply_id', 64)->nullable()->after('reply');
            }
            if (!Schema::hasColumn('comment_logs','status')) {
                $table->string('status', 32)->default('sent')->after('reply_id');
            }
            if (!Schema::hasColumn('comment_logs','meta')) {
                $table->json('meta')->nullable()->after('status');
            }

            if (!Schema::hasColumn('comment_logs','created_at')) {
                $table->timestamps();
            }
        });

        // 2) Indexuri – doar dacă lipsesc
        $this->addIndexIfMissing('comment_logs', 'post_fb_id');                 // comment_logs_post_fb_id_index
        $this->addIndexIfMissing('comment_logs', 'comment_id');                 // comment_logs_comment_id_index
        $this->addCompositeIndexIfMissing('comment_logs', ['channel_id','post_id'], 'comment_logs_channel_post_idx');
    }

    public function down(): void
    {
        // opțional: nu ștergem (safe)
    }

    private function addIndexIfMissing(string $table, string $column, ?string $name = null): void
    {
        $name   = $name ?: "{$table}_{$column}_index";
        $exists = collect(DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$name]))->isNotEmpty();
        if (!$exists) {
            Schema::table($table, function (Blueprint $t) use ($column, $name) {
                $t->index([$column], $name);
            });
        }
    }

    private function addCompositeIndexIfMissing(string $table, array $columns, string $name): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$name]))->isNotEmpty();
        if (!$exists) {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        }
    }
};
