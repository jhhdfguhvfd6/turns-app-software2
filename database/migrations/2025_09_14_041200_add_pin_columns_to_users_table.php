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
        Schema::table('users', function (Blueprint $table) {
            // Hacemos la contraseña nullable porque ya no será el método principal
            $table->string('password')->nullable()->change();

            // Añadimos las columnas para el PIN
            $table->string('pin', 4)->nullable();
            $table->timestamp('pin_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['pin', 'pin_expires_at']);
        });
    }
};
