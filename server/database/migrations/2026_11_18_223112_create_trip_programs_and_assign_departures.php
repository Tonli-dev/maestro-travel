<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: reusable day-by-day programs per trip and an optional program per departure.
 * Existing trips keep their legacy `program` text; existing departures stay unassigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('days');
            $table->timestamps();
        });

        Schema::table('departures', function (Blueprint $table): void {
            $table->foreignId('trip_program_id')->nullable()->after('trip_id')
                ->constrained('trip_programs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trip_program_id');
        });
        Schema::dropIfExists('trip_programs');
    }
};
