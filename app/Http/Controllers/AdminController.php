<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarAdminsRequest;
use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    public function index(ListarAdminsRequest $request)
    {
        return UserResource::collection(
            $this->adminService->listarPaginado($this->porPagina($request), $request->filtros())
        );
    }

    public function store(StoreAdminRequest $request)
    {
        $admin = $this->adminService->convidar($request->validated(), $request->user());

        return (new UserResource($admin))->response()->setStatusCode(201);
    }

    public function show(User $admin)
    {
        return new UserResource($this->adminService->detalhar($admin));
    }

    public function update(UpdateAdminRequest $request, User $admin)
    {
        return new UserResource($this->adminService->atualizar($admin, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, User $admin)
    {
        return new UserResource($this->adminService->desativar($admin, $request->user()));
    }

    public function reenviarConvite(Request $request, User $admin)
    {
        $this->adminService->reenviarConvite($admin, $request->user());

        return response()->json(['message' => 'Convite reenviado.']);
    }
}
