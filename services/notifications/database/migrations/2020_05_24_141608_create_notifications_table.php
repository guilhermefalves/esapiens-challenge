<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from')
                ->comment('user que gerou a notificacao');
            $table->unsignedBigInteger('to')
                ->comment('user que recebeu/receberá a notificacao');
            $table->unsignedBigInteger('identifier')
                ->nullable()
                ->comment('Identificador para ser usado pelo service que gera a notificacao');
            $table->string('mail_to', 100);
            $table->text('content');
            $table->boolean('sended')->default(false);
            $table->timestamp('sended_at')->nullable();
            $table->boolean('readed')->default(false);
            $table->timestamp('readed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
