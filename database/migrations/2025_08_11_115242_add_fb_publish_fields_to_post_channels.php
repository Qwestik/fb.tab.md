<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('post_channels', function (Blueprint $t) {
            if (!Schema::hasColumn('post_channels', 'fb_post_id')) {
                $t->string('fb_post_id', 64)->nullable()->after('status');
            }
            if (!Schema::hasColumn('post_channels', 'published_at')) {
                $t->timestamp('published_at')->nullable()->after('fb_post_id');
            }
            if (!Schema::hasColumn('post_channels', 'error')) {
                $t->text('error')->nullable()->after('published_at');
            }
        });
    }

    public function down(): void {
        Schema::table('post_channels', function (Blueprint $t) {
            if (Schema::hasColumn('post_channels', 'error'))        $t->dropColumn('error');
            if (Schema::hasColumn('post_channels', 'published_at')) $t->dropColumn('published_at');
            if (Schema::hasColumn('post_channels', 'fb_post_id'))   $t->dropColumn('fb_post_id');
        });
    }
};
