<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('channels', function (Blueprint $t) {
            $t->id();
            $t->enum('platform', ['fb','ig']);   // fb = Facebook Page, ig = Instagram Business
            $t->string('page_id')->index();      // Facebook Page ID sau IG Biz ID
            $t->string('name')->nullable();      // Numele paginii
            $t->text('access_token')->nullable();// tokenul (în MVP îl ținem aici; ulterior îl criptăm)
            $t->timestamp('token_expires_at')->nullable();
            $t->json('meta')->nullable();        // orice altceva (page username, picture, etc)
            $t->timestamps();
            $t->unique(['platform','page_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('channels');
    }
};
