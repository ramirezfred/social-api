<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Publication;
use App\Models\PublicationImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

use Carbon\Carbon;

class PublicationController extends Controller
{
    /**
     * GET /api/publications
     * Lista solo las publicaciones en estado borrador
     */
    public function index()
    {
        $publications = Publication::with([
            'images', 
            'supplier:id,razon_social,categoria'
        ])
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

        $repetida = Publication::where('supplier_id', $request->supplier_id)
            ->where('texto', $request->texto)
            ->first();

        if ($repetida) {

            \Log::error("[PublicacionRepetida]", [
                'id'          => $repetida->id,
                'supplier_id' => $request->supplier_id,
                'texto'       => $request->texto
            ]);

            // return response()->json(
            //     $repetida->load(['images', 'supplier']),
            //     201
            // );

            return response()->json([
                'success' => true
            ], 200);
        } 

        DB::beginTransaction();

        try {

            $publication = Publication::create([
                'supplier_id' => $request->supplier_id,
                'texto'       => $request->texto,
                'estado'      => 'borrador',
            ]);

            $savedPaths = [];

            foreach ($request->file('images') as $image) {
                $path = $image->store('images/publications', 'public_root');

                PublicationImage::create([
                    'publication_id' => $publication->id,
                    'image_path'     => $path,
                ]);

                $savedPaths[] = $path;
            }

            DB::commit();

            // Cachear las primeras 4 imágenes en background
            // foreach (array_slice($savedPaths, 0, 4) as $imagePath) {
            //     $this->comprimirImagenParaPDF($imagePath);
            // }

            // return response()->json(
            //     $publication->load(['images', 'supplier']),
            //     201
            // );

            return response()->json([
                'success' => true
            ], 200);

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

    public function destroyTestPublications()
    {
        $supplierId = 65; // Tu proveedor de pruebas

        // Buscamos todas las publicaciones de este proveedor
        $publications = Publication::where('supplier_id', $supplierId)->get();

        if ($publications->isEmpty()) {
            return response()->json(['message' => 'No hay publicaciones de prueba para eliminar'], 404);
        }

        DB::beginTransaction();

        try {
            foreach ($publications as $publication) {
                // 1. Borramos las imágenes físicas y sus registros
                foreach ($publication->images as $image) {
                    Storage::disk('public_root')->delete($image->image_path);
                    $image->delete();
                }

                // 2. Borramos la publicación
                $publication->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Se eliminaron ' . $publications->count() . ' publicaciones de prueba con sus imágenes.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al limpiar las publicaciones de prueba',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function catalogo(Request $request)
    {
        set_time_limit(300); // 5 minutos
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $query = Publication::query();

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }else{
            //Test Desarrollo: Excluir proveedor específico (65) para no mostrar sus publicaciones
            $query->where('supplier_id', '!=', 65);
        }

        // Filtro por fechas
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $publications = $query->with([
            'images', 
            'supplier:id,razon_social,categoria'
        ])->get();

        $processedPublications = $publications->map(function ($pub) {
            $text = $pub->texto;

            // 1. Extraer Producto (Lo que esté entre el segundo par de asteriscos)
            // Buscamos: *Coleccion*\r\n\r\n*Producto*
            preg_match('/\*([^*]+)\*\r\n\r\n\*([^*]+)\*/', $text, $productMatch);
            $producto = $productMatch[2] ?? null;

            // 2. Extraer Tallas (Lo que esté debajo de 🔖 *Tallas disponibles*)
            preg_match('/🔖 \*Tallas disponibles\*\r\n(.*?)\r\n\r\n/s', $text, $tallasMatch);
            $tallas = isset($tallasMatch[1]) ? explode("\r\n", trim($tallasMatch[1])) : [];

            // 3. Extraer Colores (Lo que esté debajo de 🎨 *Colores disponibles*)
            preg_match('/🎨 \*Colores disponibles\*\r\n(.*?)\r\n\r\n/s', $text, $coloresMatch);
            $colores = isset($coloresMatch[1]) ? explode("\r\n", trim($coloresMatch[1])) : [];

            // 4. Extraer Precio (Solo el número después del $)
            preg_match('/\$(\d+)/', $text, $precioMatch);
            $precio = $precioMatch[1] ?? null;

            // Validar que los datos esenciales existan, si no, retornamos null para descartar
            if (!$producto || !$precio || empty($tallas)) {
                return null;
            }

            // 5. Normalizar a exactamente 4 imágenes
            $originalImages = $pub->images->take(4); // Esto es una colección de Eloquent
            $count = $originalImages->count();
            $finalImages = [];

            if ($count > 0) {
                for ($i = 0; $i < 4; $i++) {
                    $imgOriginal = $originalImages[$i % $count];

                    // Comprimir y convertir a base64
                    // $imgComprimida['url'] = $this->comprimirImagenParaPDF($imgOriginal->url);
                    $url = $this->comprimirImagenParaPDF($imgOriginal->image_path);
                    $finalImages[] = ['url' => $url];
                }
            }

            // Retornamos el objeto con los datos extraídos y las 4 imágenes
            return [
                'id' => $pub->id,
                'supplier' => $pub->supplier,
                'images' => $finalImages,
                'extraido' => [
                    'producto' => trim($producto),
                    'tallas' => $tallas,
                    'colores' => array_map('trim', $colores),
                    'precio' => (int)$precio
                ]
            ];
        })->filter() // filter() elimina los elementos null (los que no cumplieron el formato)
        ->unique(function ($item) {
            return strtolower(trim($item['extraido']['producto']));
        })
        ->values(); // .values() resetea los índices del array
        

        // Validación de resultados
        if ($processedPublications->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay publicaciones disponibles en el rango de fechas seleccionado.',
                'data' => [],
                'publications' => $publications
            ], 404);
        }

        $data = [
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-CATALOGO.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),

            // 'data' => $processedPublications,
            'data'   => $processedPublications->toArray(),
        ];

        // return view('catalogos.catalogo', $data);

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('catalogos.catalogo', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/catalogos/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/catalogos/' . $nombreArchivo);

        return response()->json([
            'success' => true,
            // 'data' => $processedPublications
            'data' => $url
        ], 200);
    }

    private function comprimirImagenParaPDF(string $imagePath, int $calidad = 30, int $maxWidth = 250): string
    {
        // Cache key basada en el path + parámetros
        $cacheKey = 'img_pdf_' . md5($imagePath . $calidad . $maxWidth);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($imagePath, $calidad, $maxWidth) {
            try {
                $contenido = Storage::disk('public_root')->get($imagePath);
                if (!$contenido) return '';

                $original = imagecreatefromstring($contenido);
                if (!$original) return '';

                $w = imagesx($original);
                $h = imagesy($original);

                if ($w > $maxWidth) {
                    $newH           = (int)($h * $maxWidth / $w);
                    $redimensionada = imagecreatetruecolor($maxWidth, $newH);
                    imagecopyresampled($redimensionada, $original, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
                    imagedestroy($original);
                } else {
                    $redimensionada = $original;
                }

                ob_start();
                imagejpeg($redimensionada, null, $calidad);
                $jpeg = ob_get_clean();
                imagedestroy($redimensionada);

                return 'data:image/jpeg;base64,' . base64_encode($jpeg);

            } catch (\Exception $e) {
                \Log::error("Error comprimiendo imagen: {$imagePath} - " . $e->getMessage());
                return '';
            }
        });
    }

}
