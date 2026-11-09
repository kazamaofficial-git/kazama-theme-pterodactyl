<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('kazama_created_by')->nullable()->index();
            $table->timestamp('kazama_expires_at')->nullable()->index();
            $table->boolean('kazama_expired')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('kazama_created_by')->nullable()->index();
            $table->string('google_id', 64)->nullable()->unique();
        });

        Schema::create('kazama_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('kazama_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable()->index(); // null = broadcast ke semua
            $table->unsignedInteger('server_id')->nullable();
            $table->string('type', 16)->default('info'); // info|success|warning|danger
            $table->string('title');
            $table->text('message');
            $table->string('dedupe')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('kazama_notification_reads', function (Blueprint $table) {
            $table->unsignedBigInteger('notification_id');
            $table->unsignedInteger('user_id');
            $table->primary(['notification_id', 'user_id']);
        });

        Schema::create('kazama_auto_backups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('frequency', 16)->default('daily'); // hourly|daily|weekly
            $table->string('time', 5)->default('02:00');        // HH:MM WIB
            $table->unsignedTinyInteger('weekday')->default(1); // 0=Minggu
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kazama_auto_backups');
        Schema::dropIfExists('kazama_notification_reads');
        Schema::dropIfExists('kazama_notifications');
        Schema::dropIfExists('kazama_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kazama_created_by', 'google_id']);
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['kazama_created_by', 'kazama_expires_at', 'kazama_expired']);
        });
    }
};
