<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Proveedor;

class ProveedoresController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        $user = $req->user();
        $q = Proveedor::with('sucursal');
        if ($user->hasRole('master_admin')) {
        } elseif ($user->hasRole('casino_admin')) {
            $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));
        } else {
            $q->where('sucursal_id', $user->sucursal_id);
        }
        return $q->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $data = $req->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre' => 'required|string',
            'identificacion' => 'required|string',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
        ]);
        $exists = Proveedor::where('sucursal_id', $data['sucursal_id'])
            ->where('identificacion', $data['identificacion'])->exists();
        if ($exists) {
            return response()->json(['message' => 'Ya existe un proveedor con esa identificación en la sucursal.'], 422);
        }
        $prov = Proveedor::create($data);
        return response()->json($prov->load('sucursal'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
