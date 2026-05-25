<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('name');
            }
            $table->string('email')->nullable()->change();
        });

        $users = \App\Models\User::whereNull('username')->get();
        foreach ($users as $user) {
            $user->username = strtok($user->email, '@') . '_' . $user->id;
            $user->save();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
public function down()
{
    Schema::table('users', function (Blueprint $table) {
        // Try to drop unique index (name may differ by platform)
        $table->dropUnique(['username']); // Will not error if doesn't exist

        // Try to drop column (may fail on SQLite unless doctrine/dbal is present)
        if (Schema::hasColumn('users', 'username')) {
            $table->dropColumn('username');
        }

        $table->string('email')->nullable(false)->change();
    });
}
};