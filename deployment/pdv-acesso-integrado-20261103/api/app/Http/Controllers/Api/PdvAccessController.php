<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePdvRequest;
use App\Http\Resources\PdvResource;
use App\Services\PdvService;
class PdvAccessController extends Controller {
    public function store(StorePdvRequest $request, PdvService $points) {
        $point=$points->create($request->validated(),$request->user());
        return (new PdvResource($point))->additional(['message'=>'PDV e acesso de administrador cadastrados.'])->response()->setStatusCode(201);
    }
}
