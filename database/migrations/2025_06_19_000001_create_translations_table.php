<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTranslationsTable extends Migration
{
    public function up()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('lang', 2);
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('readonly')->default(false);
            $table->timestamps();
            $table->unique(['lang', 'key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('translations');
    }
}