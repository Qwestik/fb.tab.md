<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 🔧 Completează posts dacă lipsesc coloane (NU recrea tabela)
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table) {
                // uuid
                if (!Schema::hasColumn('posts', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }
                // timezone
                if (!Schema::hasColumn('posts', 'timezone')) {
                    $table->string('timezone')->default('UTC')->after('published_at');
                }
                // status – dacă nu există deloc (în cazul tău există deja, enum)
                if (!Schema::hasColumn('posts', 'status')) {
                    $table->enum('status', ['draft','scheduled','published'])->default('scheduled')->after('body');
                }
            });
        } else {
            // fallback rar: dacă nu există deloc posts, creeaz-o minimal
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->string('title')->nullable();
                $table->longText('body')->nullable();
                $table->enum('status', ['draft','scheduled','published'])->default('scheduled');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('timezone')->default('UTC');
                $table->timestamps();
            });
        }

        // 📒 Jurnal comentarii AI (dacă nu există)
        if (!Schema::hasTable('comment_logs')) {
            Schema::create('comment_logs', function (Blueprint $table) {
                $table->id();
                $table->string('platform')->default('facebook'); // facebook|instagram
                $table->string('page_id')->index();
                $table->string('post_id')->index();      // provider post id (ex: 523..._123...)
                $table->string('comment_id')->index();   // provider comment id
                $table->string('from_id')->nullable();
                $table->text('message')->nullable();
                $table->text('reply')->nullable();
                $table->string('reply_id')->nullable();
                $table->string('status')->default('logged'); // logged|replied|skipped|failed
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        // (opțional) alte tabele auxiliare specifice botului pot fi create aici, dar NU recrea 'posts'
    }

    public function down(): void
    {
        // Revers pentru ce-am adăugat aici (fără să ștergem posts)
        if (Schema::hasTable('comment_logs')) {
            Schema::dropIfExists('comment_logs');
        }

        // Scoate coloanele adăugate din posts (dacă există)
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table) {
                if (Schema::hasColumn('posts', 'uuid')) {
                    $table->dropUnique(['uuid']);
                    $table->dropColumn('uuid');
                }
                if (Schema::hasColumn('posts', 'timezone')) {
                    $table->dropColumn('timezone');
                }
            });
        }
    }
};
