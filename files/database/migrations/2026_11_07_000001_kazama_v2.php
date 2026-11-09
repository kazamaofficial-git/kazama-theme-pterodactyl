<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('kazama_suspended_by', 120)->nullable();
            $table->string('kazama_suspend_reason', 500)->nullable();
            $table->timestamp('kazama_suspended_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id', 32)->nullable()->unique();
        });

        Schema::create('kazama_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source', 12)->index();          // admin | user | mcp | system
            $table->unsignedInteger('actor_id')->nullable()->index();
            $table->string('actor_name', 120);
            $table->string('client', 64)->nullable();        // mis. Claude-MCP
            $table->string('action', 64)->index();
            $table->unsignedInteger('server_id')->nullable()->index();
            $table->string('server_name', 191)->nullable();
            $table->string('target', 191)->nullable();
            $table->text('detail')->nullable();
            $table->string('status', 10)->default('ok');
            $table->unsignedBigInteger('connection_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('kazama_tombstones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('server_name', 191);
            $table->unsignedInteger('owner_id')->index();
            $table->string('address', 191)->nullable();
            $table->string('created_by_name', 120)->nullable();
            $table->string('deleted_by_name', 120);
            $table->text('reason');
            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamp('expires_at')->index();
        });

        Schema::create('kazama_trash', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id')->index();
            $table->string('trash_dir', 191);
            $table->string('name', 255);
            $table->text('original_path');
            $table->boolean('is_dir')->default(false);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('deleted_by', 120);
            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamp('expires_at')->index();
        });

        Schema::create('kazama_console_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id')->index();
            $table->string('line', 500);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('kazama_user_prefs', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->boolean('bg_enabled')->default(false);
            $table->string('bg_type', 8)->default('url');   // url | upload
            $table->string('bg_url', 500)->nullable();
            $table->unsignedTinyInteger('bg_blur')->default(12);
        });

        Schema::create('kazama_mcp_connections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('name', 120);
            $table->string('platform', 24)->default('other');
            $table->string('auth_type', 8)->default('manual');   // manual | oauth
            $table->char('token_hash', 64)->nullable()->unique();
            $table->char('refresh_hash', 64)->nullable()->index();
            $table->timestamp('access_expires_at')->nullable();
            $table->unsignedInteger('api_key_id')->nullable();
            $table->unsignedInteger('app_key_id')->nullable();
            $table->string('access', 8)->default('full');          // full | read
            $table->string('servers_mode', 8)->default('all');     // all | selected
            $table->text('server_ids')->nullable();
            $table->unsignedSmallInteger('daily_limit')->default(0);
            $table->unsignedSmallInteger('auto_disconnect_days')->default(0);
            $table->boolean('auto_backup')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('kazama_mcp_prefs', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->text('settings');
        });

        Schema::create('kazama_mcp_clients', function (Blueprint $table) {
            $table->string('client_id', 64)->primary();
            $table->string('client_name', 120);
            $table->text('redirect_uris');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('kazama_mcp_codes', function (Blueprint $table) {
            $table->char('code_hash', 64)->primary();
            $table->string('client_id', 64);
            $table->unsignedBigInteger('connection_id');
            $table->text('redirect_uri');
            $table->string('code_challenge', 128);
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        foreach (['kazama_mcp_codes', 'kazama_mcp_clients', 'kazama_mcp_prefs', 'kazama_mcp_connections', 'kazama_user_prefs', 'kazama_console_lines', 'kazama_trash', 'kazama_tombstones', 'kazama_logs'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('github_id'));
        Schema::table('servers', fn (Blueprint $t) => $t->dropColumn(['kazama_suspended_by', 'kazama_suspend_reason', 'kazama_suspended_at']));
    }
};
