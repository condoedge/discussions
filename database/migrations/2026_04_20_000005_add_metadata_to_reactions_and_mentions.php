<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('discussion_reactions', function (Blueprint $table) {
            $table->foreignId('added_by')->nullable()->after('id')->constrained('users');
            $table->foreignId('modified_by')->nullable()->after('added_by')->constrained('users');
            $table->softDeletes();
        });

        Schema::table('discussion_mentions', function (Blueprint $table) {
            $table->foreignId('added_by')->nullable()->after('id')->constrained('users');
            $table->foreignId('modified_by')->nullable()->after('added_by')->constrained('users');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('discussion_reactions', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropForeign(['modified_by']);
            $table->dropColumn(['added_by', 'modified_by', 'deleted_at']);
        });

        Schema::table('discussion_mentions', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropForeign(['modified_by']);
            $table->dropColumn(['added_by', 'modified_by', 'deleted_at']);
        });
    }
};
