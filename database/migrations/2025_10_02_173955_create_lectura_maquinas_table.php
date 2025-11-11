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
        Schema::create('lecturas_maquinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('entrada', 18, 2);
            $table->decimal('salida', 18, 2);
            $table->decimal('jackpots', 18, 2);
            $table->decimal('neto_inicial', 18, 2)->default(0);
            $table->decimal('neto_final', 18, 2)->default(0);
            $table->decimal('total_creditos', 18, 2)->default(0);
            $table->decimal('total_recaudo', 18, 2)->default(0);
            $table->boolean('confirmado')->default(0);
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamps();
            $table->unique(['maquina_id','fecha']);
            $table->index(['sucursal_id','fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturas_maquinas');
    }
};
