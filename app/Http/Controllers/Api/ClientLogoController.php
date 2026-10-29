<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ClientLogoRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ClientLogoController extends Controller
{
    #[OA\Post(
        path: '/clients/{client}/logo',
        summary: 'Upload or replace the client logo',
        description: 'multipart/form-data. JPG, PNG or WEBP, max 1 MB, max 2000×2000 px. The old logo file is deleted.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['logo'], properties: [new OA\Property(property: 'logo', type: 'string', format: 'binary')]),
        )),
        tags: ['Clients'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Logo saved', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Client')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(ClientLogoRequest $request, Client $client): ClientResource
    {
        Gate::authorize('update', $client);

        $oldPath = $client->logo_path;

        // Random file name (hashName) — never trust the uploaded file name.
        $client->forceFill(['logo_path' => $request->file('logo')->store('client-logos', 'public')])->save();

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return new ClientResource($client->load(['status', 'country', 'creator'])->loadCount('managers'));
    }

    #[OA\Delete(
        path: '/clients/{client}/logo',
        summary: 'Remove the client logo',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Logo removed', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Client')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Client $client): ClientResource
    {
        Gate::authorize('update', $client);

        if ($client->logo_path) {
            Storage::disk('public')->delete($client->logo_path);
            $client->forceFill(['logo_path' => null])->save();
        }

        return new ClientResource($client->load(['status', 'country', 'creator'])->loadCount('managers'));
    }
}
