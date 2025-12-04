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
        Schema::table('sucursales', function (Blueprint $table) {
            $table->decimal('base_monedas', 10, 2)->nullable()->after('nombre');
            $table->decimal('base_billetes', 10, 2)->nullable()->after('base_monedas');
            $table->tinyInteger('activo')->default(1)->after('base_billetes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['base_monedas', 'base_billetes', 'activo']);
        });
    }
};
