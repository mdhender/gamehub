<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $mapping = [
        'gold' => 'GOLD',
        'fuel' => 'FUEL',
        'metallics' => 'METS',
        'non_metallics' => 'NMTS',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->mapping as $old => $new) {
            DB::table('deposits')->where('resource', $old)->update(['resource' => $new]);
            DB::table('home_system_template_deposits')->where('resource', $old)->update(['resource' => $new]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $reverse = array_flip($this->mapping);

        foreach ($reverse as $old => $new) {
            DB::table('deposits')->where('resource', $old)->update(['resource' => $new]);
            DB::table('home_system_template_deposits')->where('resource', $old)->update(['resource' => $new]);
        }
    }
};
