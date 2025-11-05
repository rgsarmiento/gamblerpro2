<?php

namespace App\Policies;

use App\Models\Gasto;
use App\Models\Sucursal;
use App\Models\User;

use Illuminate\Auth\Access\Response;

class GastoPolicy
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
    public function view(User $user, Gasto $gasto): bool
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
    public function update(User $user, Gasto $gasto): bool
    {
         if ($gasto->cierre_id) {
            // Cajero no puede editar luego de cierre
            if ($user->hasRole('cajero')) {
                return false;
            }
        }
        return $this->canSeeSucursal($user, $gasto->sucursal_id);
    }

    private function canSeeSucursal(User $user, int $sucursalId): bool
    {
        if ($user->hasRole('master_admin')) {
            return true;
        }

        if ($user->hasRole('casino_admin')) {
            return Sucursal::where('id', $sucursalId)
                ->where('casino_id', $user->casino_id)
                ->exists();
        }

        if ($user->hasAnyRole(['sucursal_admin','cajero'])) {
            return (int)$user->sucursal_id === (int)$sucursalId;
        }

        return false;
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Gasto $gasto): bool
    {
        return $this->update($user, $gasto);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Gasto $gasto): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Gasto $gasto): bool
    {
        return false;
    }
}
