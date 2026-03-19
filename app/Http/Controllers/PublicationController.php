<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Publication;
use App\Models\PublicationImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

use Carbon\Carbon;

class PublicationController extends Controller
{
    /**
     * GET /api/publications
     * Lista solo las publicaciones en estado borrador
     */
    public function index()
    {
        $publications = Publication::with(['images', 'supplier'])
            ->where('estado', 'borrador')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($publications);
    }

    /**
     * POST /api/publications
     * Crea una publicación con sus imágenes (máximo 4)
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'   => 'required|exists:suppliers,id',
            'texto'         => 'required|string',
            'images'        => 'required|array|min:1|max:9',
            'images.*'      => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        DB::beginTransaction();

        try {
            $publication = Publication::create([
                'supplier_id' => $request->supplier_id,
                'texto'       => $request->texto,
                'estado'      => 'borrador',
            ]);

            foreach ($request->file('images') as $image) {
                $path = $image->store('images/publications', 'public_root');

                PublicationImage::create([
                    'publication_id' => $publication->id,
                    'image_path'     => $path,
                ]);
            }

            DB::commit();

            return response()->json(
                $publication->load(['images', 'supplier']),
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la publicación'], 500);
        }
    }

    /**
     * GET /api/publications/{id}
     * Detalle de una publicación
     */
    public function show($id)
    {
        $publication = Publication::find($id);

        if (!$publication) {
            return response()->json(['message' => 'Publicación no encontrada'], 404);
        }

        // Cargar imágenes con base64
        $publication->load(['supplier']);
        
        $images = $publication->images->map(function ($image) {
            // $path = storage_path('app/public/' . $image->image_path);
            $path = public_path($image->image_path);
            
            $base64 = '';
            $mime   = 'image/jpeg';

            if (file_exists($path)) {
                $mime   = mime_content_type($path);
                $base64 = base64_encode(file_get_contents($path));
            }

            return [
                'id'             => $image->id,
                'publication_id' => $image->publication_id,
                'image_path'     => $image->image_path,
                'url'            => "data:{$mime};base64,{$base64}",
            ];
        });

        return response()->json([
            'id'          => $publication->id,
            'supplier_id' => $publication->supplier_id,
            'texto'       => $publication->texto,
            'estado'      => $publication->estado,
            'created_at'  => $publication->created_at,
            'updated_at'  => $publication->updated_at,
            'supplier'    => $publication->supplier,
            'images'      => $images,
        ]);
    }

    /**
     * PATCH /api/publications/{id}/publish
     * Marca la publicación como publicada
     */
    public function publish($id)
    {
        $publication = Publication::find($id);

        if (!$publication) {
            return response()->json(['message' => 'Publicación no encontrada'], 404);
        }

        if ($publication->estado === 'publicada') {
            return response()->json(['message' => 'La publicación ya fue publicada'], 422);
        }

        $publication->update([
            'estado'           => 'publicada',
            'publication_date' => now(), 
        ]);

        return response()->json($publication->load(['images', 'supplier']));
    }

    /**
     * DELETE /api/publications/{id}
     * Elimina la publicación y sus imágenes del storage
     */
    public function destroy($id)
    {
        $publication = Publication::find($id);

        if (!$publication) {
            return response()->json(['message' => 'Publicación no encontrada'], 404);
        }

        DB::beginTransaction();

        try {
            foreach ($publication->images as $image) {
                Storage::disk('public_root')->delete($image->image_path);
                $image->delete();
            }

            $publication->delete();

            DB::commit();

            // return response()->json(null, 204);
            return response()->json(['message' => 'Publicación eliminada'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar la publicación'], 500);
        }
    }

}
