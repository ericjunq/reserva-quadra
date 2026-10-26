<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservaRequest;
use App\Models\Reserva;
use Illuminate\Http\Request;
use App\Http\Resources\ReservaResource;
use App\Services\ReservaService;

class ReservaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // TESTAR AS ROTAS 
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ReservaRequest $request, ReservaService $reservaService)
    {
        $reserva = $reservaService->create($request->validated());

        return new ReservaResource($reserva);
    }

    /**
     * Display the specified resource.
     */
    public function show(Reserva $reserva)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Reserva $reserva)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reserva $reserva)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reserva $reserva)
    {
        //
    }
}
