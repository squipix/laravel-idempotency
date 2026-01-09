<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('method', 10);
            $table->string('route');
            $table->string('payload_hash')->nullable();
            $table->json('response');
            $table->unsignedSmallInteger('status_code');
            $table->timestamps();

            $table->unique(['key', 'method', 'route']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
