<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscussionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discussions', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('channel_id')->constrained();
            $table->foreignId('discussion_id')->nullable()->constrained();
            $table->string('subject')->nullable();
            $table->string('summary')->nullable();
            $table->longText('html')->nullable(); //null when sending an attachment only
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discussions');
    }
}
