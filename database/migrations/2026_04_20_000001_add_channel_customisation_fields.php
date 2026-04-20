<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('description', 500)->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('color', 32)->nullable();
            $table->tinyInteger('is_private')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['description', 'icon', 'color', 'is_private']);
        });
    }
};
