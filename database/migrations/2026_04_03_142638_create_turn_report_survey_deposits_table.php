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
        Schema::create('turn_report_survey_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_report_survey_id')->constrained()->cascadeOnDelete();
            $table->integer('deposit_no');
            $table->string('resource');
            $table->integer('yield_pct');
            $table->integer('quantity_remaining');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turn_report_survey_deposits');
    }
};
