<?php

namespace App\Policies;

use App\Models\{User, LecturaMaquina, Sucursal};
use Illuminate\Auth\Access\Response;

class LecturaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LecturaMaquina $lecturaMaquina): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['master_admin', 'casino_admin', 'sucursal_admin', 'cajero']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LecturaMaquina $lecturaMaquina): bool
    {
        if ($lecturaMaquina->cierre_id) {
            return !$user->hasRole('cajero'); // cajero no puede editar después del cierre
        }
        return $this->canSeeSucursal($user, $lecturaMaquina->sucursal_id);
    }

    private function canSeeSucursal(User $user, int $sucursalId): bool
    {
        if ($user->hasRole('master_admin')) return true;
        if ($user->hasRole('casino_admin')) {

            return Sucursal::where('id', $sucursalId)->where('casino_id', $user->casino_id)->exists();
        }
        if ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
            return $user->sucursal_id === $sucursalId;
        }
        return false;
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LecturaMaquina $lecturaMaquina): bool
    {
        return $this->update($user, $lecturaMaquina);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LecturaMaquina $lecturaMaquina): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LecturaMaquina $lecturaMaquina): bool
    {
        return false;
    }
}
