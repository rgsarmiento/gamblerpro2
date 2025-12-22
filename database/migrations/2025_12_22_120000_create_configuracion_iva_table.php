<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configuracion_iva', function (Blueprint $table) {
            $table->id();
            $table->integer('anio')->unique();
            $table->integer('valor_uvt');
            $table->integer('cantidad_uvt'); // e.g. 20 (según imagen parece ser un número pequeño o un multiplicador)
            $table->decimal('porcentaje_iva', 5, 2); // e.g. 19.00
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuracion_iva');
    }
};
