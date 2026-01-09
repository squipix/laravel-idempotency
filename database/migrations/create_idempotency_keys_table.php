<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->index();
            $table->string('method', 10);
            $table->string('route', 500);
            $table->string('payload_hash', 64)->nullable();
            $table->json('response');
            $table->unsignedSmallInteger('status_code');
            $table->timestamps();

            $table->unique(['key', 'method', 'route'], 'idempotency_unique');
            $table->index('created_at', 'idempotency_created_at_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
