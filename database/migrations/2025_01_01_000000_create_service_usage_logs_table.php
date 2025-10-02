<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('uniformed-ai.logging.table', 'service_usage_logs');
        Schema::create($table, function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('provider', 40)->index();
            $t->string('service_type', 20)->index();
            $t->string('service_operation', 40)->nullable();
            $t->string('driver', 120)->nullable();
            $t->string('model', 120)->nullable()->index();
            $t->string('status', 16);
            $t->smallInteger('http_status')->nullable();
            $t->unsignedInteger('latency_ms')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->json('request_payload')->nullable();
            $t->json('response_payload')->nullable();
            $t->text('error_message')->nullable();
            $t->string('error_class', 160)->nullable();
            $t->integer('exception_code')->nullable();
            $t->json('stream_chunks')->nullable();
            $t->json('extra')->nullable();
            $t->timestamps();
            $t->index(['provider','service_type','created_at'],'idx_service_usage_logs_provider_service');
            $t->index(['provider','model'],'idx_service_usage_logs_provider_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('uniformed-ai.logging.table', 'service_usage_logs'));
    }
};
