<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Publication;
use App\Models\PublicationImage;
use App\Models\User;
use App\Models\QuoteDetail;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

use Carbon\Carbon;

class PublicationController extends Controller
{

    public function indexFilter(Request $request)
    {
        // $query = Publication::select('id', 'texto')->where('id', '>=', 282);

        $query = Publication::select('id', 'texto')
            ->where(function ($q) {
                $q->where('id', '>=', 282)
                ->orWhere('id', 1); //Publicacion generica de Plaza del Vestido
            });

        // 1. Filtrar únicamente sobre la línea del código mediante REGEXP
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('texto', 'REGEXP', '(^|\r|\n)[^\r\n]*' . preg_quote($search, '/') . '[^\r\n]*(\r|\n|$)');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // 2. Traer resultados y transformar cada texto a la estructura deseada
        $publications = $query->orderBy('id', 'desc')->get()->map(function ($pub) {
            $texto = $pub->texto;

            // Extraer Código (ej: *SN-260723-160654*)
            preg_match('/\*(?:[A-Z0-9]+-[0-9]+-[0-9]+)\*/i', $texto, $matchCodigo);
            $codigo = isset($matchCodigo[0]) ? trim($matchCodigo[0], '*') : null;

            return [
                'id' => $pub->id,
                'codigo' => $codigo,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $publications,
        ]);
    }

    /**
     * GET /api/publications
     * Lista solo las publicaciones en estado borrador
     */
    public function index(Request $request)
    {
        $query = Publication::where('estado', 'borrador');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $publications = $query->with([
            'images', 
            'supplier:id,razon_social,categoria'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($publications);
    }

    /**
     * GET /api/publications/index/stories
     * Lista solo las publicaciones en estado borrador
     */
    public function indexStories(Request $request)
    {
        // Semana actual: viernes 00:00:00 → jueves 23:59:59
        $inicioSemana = Carbon::now()->startOfWeek(Carbon::FRIDAY); // viernes de esta semana
        $finSemana    = $inicioSemana->copy()->addDays(7)->subSecond(); // jueves 23:59:59

        // Umbral de 24 horas: publicaciones con history_date <= este instante ya "expiraron"
        $limite24h = Carbon::now()->subHours(24);

        $query = Publication::
            // Filtro de semana en curso sobre created_at
            whereBetween('created_at', [$inicioSemana, $finSemana])
            // Solo no publicadas, o publicadas hace más de 24h
            ->where(function ($q) use ($limite24h) {
                $q->whereNull('history_date')
                ->orWhere('history_date', '<=', $limite24h);
            });

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $publications = $query->with([
            'images', 
            'supplier:id,razon_social,categoria'
        ])
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
        $validator = Validator::make($request->all(), [
            'user_id'       => 'required|numeric',
            'supplier_id'   => 'required|exists:suppliers,id',
            'texto'         => 'required|string',
            'images'        => 'required|array|min:1|max:9',
            'images.*'      => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 500);
        }

        $user = User::buscarPorId($request->user_id);
        if (!$user)
        {

            \Log::error("[PublicacionUser401]", [
                'user_id'     => $request->user_id,
                'message' => 'Usuario no encontrado.',
            ]);

            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado.'
            ], 401);
        }

        // if($user->status != 1)
        // {
        //     \Log::error("[PublicacionUser422]", [
        //         'user_id'     => $request->user_id,
        //         'message' => 'El usuario no está activo.',
        //     ]);

        //     return response()->json([
        //         'success' => false,
        //         'message'=>'El usuario no está activo.'
        //     ], 422);
        // }

        $repetida = Publication::where('user_id', $request->user_id)
            ->where('supplier_id', $request->supplier_id)
            ->where('texto', $request->texto)
            ->first();

        if ($repetida) {

            \Log::error("[PublicacionRepetida]", [
                'id'          => $repetida->id,
                'user_id'     => $request->user_id,
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
                'user_id'     => $request->user_id,
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

    public function publishStories(Request $request)
    {
        $ids = $request->input('array_ids', []);

        if (empty($ids)) {
            return response()->json(['message' => 'No hay publicaciones para marcar.'], 400);
        }

        Publication::whereIn('id', $ids)->update([
            'history_date' => now(),   // si usas timestamp
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Publicaciones marcadas como publicadas.',
            'ids' => $ids
        ], 200);
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

        // Validar si la publicación está asociada a una cotización
        if (QuoteDetail::where('publication_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar esta publicación porque está asociada a una cotización.',
            ], 409);
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

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
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
            $originalImages = $pub->images->take(5); // Esto es una colección de Eloquent
            $count = $originalImages->count();
            $finalImages = [];

            if ($count > 0) {
                for ($i = 0; $i < 5; $i++) {
                    $imgOriginal = $originalImages[$i % $count];

                    // Comprimir y convertir a base64
                    // $imgComprimida['url'] = $this->comprimirImagenParaPDF($imgOriginal->url);
                    // $url = $this->comprimirImagenParaPDF($imgOriginal->image_path);
                    $url = $this->comprimirImagenParaPDF($imgOriginal->image_path, 65, 340);
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
            // 'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-CATALOGO.jpeg'),
            // 'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),

            'logo' => public_path('images/ordenPlazaVestido/logoPlazaDelVestido.png'),
            'logo2' => public_path('images/ordenPlazaVestido/logoPlazaDelVestido2.png'),

            // 'data' => $processedPublications,
            'data'   => $processedPublications->toArray(),
        ];

        // return view('catalogos.catalogo', $data);

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        // $pdf = Pdf::loadView('catalogos.catalogo', $data)->setPaper('letter');
        $pdf = Pdf::loadView('catalogos.testCatalogo', $data)->setPaper('letter');
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
        $cacheKey = 'img_pdf_v5_' . md5($imagePath . $calidad . $maxWidth);

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

    public function editar($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id'   => 'required|exists:suppliers,id',
            'texto'         => 'required|string',
            'images'        => 'required|array|min:1|max:9',
            'images.*'      => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 500);
        }

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

            $publication->supplier_id = $request->supplier_id;
            $publication->texto       = $request->texto;
            $publication->save();

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

            return response()->json([
                'success' => true,
                'message' => 'Publicación editada'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al editar la publicación'], 500);
        }
    }

    public function showNormal($id)
    {
        $publication = Publication::find($id);

        if (!$publication) {
            return response()->json(['message' => 'Publicación no encontrada'], 404);
        }

        $publication->load([
            'images', 
            'supplier:id,razon_social,categoria'
        ]);

        // Procesar el texto de la publicación individual
        $texto = $publication->texto;

        // Extraer Código
        preg_match('/\*(?:[A-Z0-9]+-[0-9]+-[0-9]+)\*/i', $texto, $matchCodigo);
        $codigo = isset($matchCodigo[0]) ? trim($matchCodigo[0], '*') : null;

        // Si el ID es 1, omitimos el parseo y devolvemos arrays vacíos
        if ((int)$id === 1) {
            $tallas = [];
            $colores = [];
        } else {

            // Extraer Tallas disponibles
            preg_match('/🔖 \*Tallas disponibles\*\s*\r?\n([\s\S]*?)(?=\r?\n\r?\n🎨|\r?\n\r?\nPrecio|$)/i', $texto, $matchTallas);
            $tallas = isset($matchTallas[1]) ? array_values(array_filter(explode("\n", str_replace("\r", "", trim($matchTallas[1]))))) : [];

            // Extraer Colores disponibles
            preg_match('/🎨 \*Colores disponibles\*\s*\r?\n([\s\S]*?)(?=\r?\n\r?\nPrecio|\r?\n\r?\n📦|$)/i', $texto, $matchColores);
            $colores = isset($matchColores[1]) ? array_values(array_filter(explode("\n", str_replace("\r", "", trim($matchColores[1]))))) : [];

        }

        // Extraer Precio
        preg_match('/Precio \*\$?([0-9]+(?:\.[0-9]+)?)/i', $texto, $matchPrecio);
        $precio = isset($matchPrecio[1]) ? (float)$matchPrecio[1] : null;

        // Asignar los campos transformados directamente o estructurar la respuesta
        $formattedPublication = array_merge($publication->toArray(), [
            'codigo' => $codigo,
            'tallas' => $tallas,
            'colores' => $colores,
            'precio' => $precio,
        ]);

        return response()->json([
            'data' => $formattedPublication,
        ]);

        
    }



}
