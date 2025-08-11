<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Dacă tabela există deja fără aceste coloane, le adăugăm
            if (!Schema::hasColumn('media', 'post_id')) {
                $table->foreignId('post_id')
                      ->nullable()
                      ->constrained('posts')
                      ->cascadeOnDelete()
                      ->after('id');
            }
            if (!Schema::hasColumn('media', 'disk')) {
                $table->string('disk')->default('public')->after('path');
            }
            if (!Schema::hasColumn('media', 'mime')) {
                $table->string('mime')->nullable()->after('disk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (Schema::hasColumn('media', 'post_id')) {
                $table->dropForeign(['post_id']);
                $table->dropColumn('post_id');
            }
            if (Schema::hasColumn('media', 'disk')) {
                $table->dropColumn('disk');
            }
            if (Schema::hasColumn('media', 'mime')) {
                $table->dropColumn('mime');
            }
        });
    }
};
