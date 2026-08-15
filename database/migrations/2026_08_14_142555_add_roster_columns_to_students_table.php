<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The students table becomes the roster.
 *
 * An administrator records a student before that student has an account, so
 * the row must be able to exist without one: `user_id` becomes nullable and
 * null now means "unclaimed". The name moves onto the row for the same reason
 * — until the account is claimed there is no user to hold it.
 *
 * `user_id` keeps its unique index. Postgres treats NULLs as distinct under a
 * unique index, so any number of unclaimed rows coexist while still allowing
 * only one student per account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        $this->backfillNamesFromUsers();

        Schema::table('students', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Unclaimed rows have no account to point back at, so they cannot
        // survive a column that is about to be NOT NULL again.
        DB::table('students')->whereNull('user_id')->delete();

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }

    /**
     * Copy each existing student's name off their account. A no-op on a fresh
     * database; written driver-portably because the suite migrates on SQLite.
     */
    private function backfillNamesFromUsers(): void
    {
        DB::table('students')
            ->select('id', 'user_id')
            ->orderBy('id')
            ->chunkById(500, function ($students) {
                foreach ($students as $student) {
                    DB::table('students')
                        ->where('id', $student->id)
                        ->update([
                            'name' => DB::table('users')->where('id', $student->user_id)->value('name') ?? '',
                        ]);
                }
            });
    }
};
