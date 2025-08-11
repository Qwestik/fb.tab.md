<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Channels (trebuie creată înainte de post_channels)
        Schema::create('channels', function (Blueprint $t) {
			$t->engine = 'InnoDB';
			$t->id(); // BIGINT UNSIGNED
			$t->enum('platform', ['fb','ig'])->index(); // <— fb/ig ca în UI
			$t->string('page_id')->index();
			$t->string('name')->nullable();            // <— denumirea paginii
			$t->text('access_token')->nullable();      // <— tokenul de pagină
			$t->timestamp('token_expires_at')->nullable();
			$t->json('meta')->nullable();
			$t->timestamps();
			$t->unique(['platform','page_id']);
		});


        // Posts
        Schema::create('posts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('uuid')->unique()->nullable();
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Media (simplu; dacă vrei pivot post_media, îl adăugăm ulterior)
        Schema::create('media', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        // Legătură post → channel (unde salvăm id-ul postării publicate pe platformă)
       Schema::create('post_channels', function (Blueprint $t) {
			$t->engine = 'InnoDB';
			$t->id();
			$t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
			$t->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
			$t->string('provider_post_id')->nullable();
			$t->string('status')->default('pending');
			$t->json('last_error')->nullable();
			$t->timestamps();
			$t->unique(['post_id','channel_id']);
		});

    }

    public function down(): void
    {
        Schema::dropIfExists('post_channels');
        Schema::dropIfExists('media');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('channels');
    }
};
