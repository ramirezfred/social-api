<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\View;
//use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

use Spatie\Browsershot\Browsershot;
use Barryvdh\DomPDF\Facade\Pdf;
use Imagick;

class GoopyCatalogoController extends Controller
{
    public function catalogoPdfGoopy($tipo=1)
    {

        set_time_limit(600);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.goopy.app/pruebas/ropa/inventario_pdf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;

            return response()->json([
                'error'=>'Error al conectar con Meta',
                'goopy'=>$err
            ], 409);

        } else {

            $goopy_obj = json_decode($response);

            $catalogo = $goopy_obj->productos_ropa;
          
            // return response()->json([
            //     'catalogo'=>$catalogo,
            // ], 200);

            // Comprimir las imágenes antes de pasarlas a la vista
            $countProductos = count($catalogo);
            for ($i = 0; $i < $countProductos; $i++) {
                $producto = $catalogo[$i];
                $countImagenes = count($producto->imagenes);
                for ($j = 0; $j < $countImagenes; $j++) {
                    $imagen =& $producto->imagenes[$j];
                    // Ruta de la imagen original
                    //$imagenPath = public_path($imagen->url);
                    $imagenPath = $imagen->url;

                    // Crear un objeto Imagick a partir de la imagen original
                    $imagick = new Imagick($imagenPath);

                    // Establecer la calidad deseada (valores entre 0 y 100)
                    $imagick->setImageCompressionQuality(30);

                    // // Guardar la imagen comprimida en una ubicación temporal
                    // $tempImagePath = tempnam(sys_get_temp_dir(), 'compressed_image_');
                    // $imagick->writeImage($tempImagePath);

                    // Carpeta específica para almacenar las imágenes comprimidas
                    $carpetaDestino = public_path('/pdfs_goopy/compressed_images/');

                    // Asegurarse de que la carpeta de destino exista
                    if (!file_exists($carpetaDestino)) {
                        mkdir($carpetaDestino, 0777, true);
                    }

                    // Nombre del archivo en la carpeta de destino
                    $nombreArchivoDestino = 'imagen_comprimida_' . $i . '_' . $j . '.jpg';

                    // Guardar la imagen comprimida en la carpeta de destino
                    $imagenDestinoPath = $carpetaDestino . $nombreArchivoDestino;
                    $imagick->writeImage($imagenDestinoPath);

                    // Actualizar la URL de la imagen con la ubicación temporal comprimida
                    $imagen->url = $imagenDestinoPath;
                }
            }

            $data = [
                'tipo_catalogo'=>$tipo,
                'catalogo'=>$catalogo,
            ];

            //return view('goopy.catalogo', $data);

            //$pdf = Pdf::loadView('goopy.catalogo', $data);
            // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
            $pdf = Pdf::loadView('goopy.catalogo', $data)->setPaper('letter');
            $pdfContent = $pdf->output();

            $countProductos = count($catalogo);
            for ($i = 0; $i < $countProductos; $i++) {
                $producto = $catalogo[$i];
                $countImagenes = count($producto->imagenes);
                for ($j = 0; $j < $countImagenes; $j++) {
                    $imagen = $producto->imagenes[$j];

                    if (file_exists($imagen->url)) {
                        unlink($imagen->url); // Eliminar la imagen
                    }
                }
            }

            if($tipo==1){
                // Genera un nombre de archivo único
                $nombreArchivo = 'pdf_catalogo_con_precios.pdf';
            }else{
                // Genera un nombre de archivo único
                $nombreArchivo = 'pdf_catalogo_sin_precios.pdf';
            }

            // Guarda el PDF en la carpeta "public" del directorio raíz
            Storage::disk('public_root')->put('pdfs_goopy/'.$nombreArchivo, $pdf->output());

            // Obtiene la URL del archivo guardado
            $url = asset('pdfs_goopy/' . $nombreArchivo);

            return $url;
              
        } 
        

    }
}
