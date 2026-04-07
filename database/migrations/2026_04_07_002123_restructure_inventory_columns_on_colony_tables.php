<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'colony_template_items',
        'colony_inventory',
        'turn_report_colony_inventory',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('inventory_section')->default('operational');
                $blueprint->integer('quantity')->default(0);
            });

            DB::table($table)->update([
                'quantity' => DB::raw('quantity_assembled + quantity_disassembled'),
            ]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['quantity_assembled', 'quantity_disassembled']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->integer('quantity_assembled')->default(0);
                $blueprint->integer('quantity_disassembled')->default(0);
            });

            DB::table($table)->update([
                'quantity_assembled' => DB::raw('quantity'),
            ]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['inventory_section', 'quantity']);
            });
        }
    }
};
