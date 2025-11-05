<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CierreService;
use App\Models\{CierreCaja, Sucursal};

class CierresController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct(private CierreService $service) {}


    public function index(Request $req)
    {
        $user = $req->user();
        $q = CierreCaja::with('sucursal')->orderByDesc('fecha_fin');
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
        $data = $req->validate(['sucursal_id' => 'required|exists:sucursales,id', 'observaciones' => 'nullable|string']);
        $cierre = $this->service->cerrarSucursal($data['sucursal_id'], $req->user()->id, $data['observaciones'] ?? null);
        return response()->json($cierre->load('sucursal'), 201);
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
