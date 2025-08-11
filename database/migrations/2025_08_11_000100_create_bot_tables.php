<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('social_accounts', function (Blueprint $t) {
      $t->id(); $t->string('provider')->default('facebook_page');
      $t->string('page_id')->index(); $t->string('name')->nullable();
      $t->text('access_token'); $t->json('config')->nullable(); $t->timestamps();
    });
    Schema::create('posts', function (Blueprint $t) {
      $t->id(); $t->uuid()->unique(); $t->string('status')->default('scheduled');
      $t->timestamp('scheduled_at')->nullable(); $t->timestamp('published_at')->nullable();
      $t->string('timezone')->default(config('app.timezone','Europe/Chisinau')); $t->timestamps();
    });
    Schema::create('post_targets', function (Blueprint $t) {
      $t->id(); $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
      $t->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
      $t->string('status')->default('scheduled'); $t->string('provider_post_id')->nullable();
      $t->json('errors')->nullable(); $t->timestamps(); $t->unique(['post_id','account_id']);
    });
    Schema::create('post_versions', function (Blueprint $t) {
      $t->id(); $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
      $t->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
      $t->longText('body'); $t->json('media')->nullable(); $t->timestamps();
      $t->unique(['post_id','account_id']);
    });
    Schema::create('media', function (Blueprint $t) {
      $t->id(); $t->uuid()->unique(); $t->string('disk')->default('public');
      $t->string('path'); $t->string('mime')->nullable(); $t->unsignedBigInteger('size')->default(0);
      $t->json('conversions')->nullable(); $t->timestamps();
    });
    Schema::create('ai_settings', function (Blueprint $t) { $t->id(); $t->json('config'); $t->timestamps(); });
    Schema::create('comment_logs', function (Blueprint $t) {
      $t->id(); $t->string('page_id')->index(); $t->string('post_id')->index();
      $t->string('comment_id')->unique(); $t->string('from_id')->nullable();
      $t->text('message')->nullable(); $t->text('reply')->nullable();
      $t->string('reply_id')->nullable(); $t->string('status')->default('fetched');
      $t->json('meta')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {
    Schema::dropIfExists('comment_logs'); Schema::dropIfExists('ai_settings');
    Schema::dropIfExists('media'); Schema::dropIfExists('post_versions');
    Schema::dropIfExists('post_targets'); Schema::dropIfExists('posts');
    Schema::dropIfExists('social_accounts');
  }
};
