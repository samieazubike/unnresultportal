<?php

use App\Enums\RegistrationSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who put a registration there: an administrator's course offering, or the
 * student. Re-saving an offering only ever removes rows it created itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('source', 16)->default(RegistrationSource::Student->value);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
