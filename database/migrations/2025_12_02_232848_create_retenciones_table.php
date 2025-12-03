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
        Schema::create('retenciones', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('sucursal_id')->constrained('sucursales')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('cedula', 20);
            $table->string('nombre', 255);
            $table->decimal('valor_premio', 15, 2);
            $table->decimal('valor_retencion', 15, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index('fecha');
            $table->index('sucursal_id');
            $table->index('cedula');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retenciones');
    }
};
