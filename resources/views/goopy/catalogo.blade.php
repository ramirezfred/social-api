<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <style>

      @media print {
        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; }
        html {
          margin: 0;
          padding: 0;
        }
      }

        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; }
        html {
          margin: 0;
          padding: 0;
        }

        
        /* Flexbox para distribuir equitativamente los divs verticales */
        .color-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        
    </style>

</head>
<body>

      @if ($tipo_catalogo == 1)
        <div style="
              height: 11in; /* Altura de hoja carta en pulgadas */
              margin: 0 auto; /* Margen superior e inferior 0, margen izquierdo y derecho automático para centrar */
              padding: 0; /* Eliminar relleno */
              overflow: hidden; /* Evitar desbordamiento de contenido */">

              <img class="img-fluid" src="https://apisocial.internow.com.mx/pdfs_goopy/images/portada1.PNG" alt="" style="width: 100%; height: 100%;">
          
        </div>
      @endif

      @foreach ($catalogo as $producto)
        <div style="
            height: 11in; /* Altura de hoja carta en pulgadas */
            margin: 0 auto; /* Margen superior e inferior 0, margen izquierdo y derecho automático para centrar */
            padding: 0; /* Eliminar relleno */
            overflow: hidden; /* Evitar desbordamiento de contenido */">


          <table width="100%">
            <tr>
              <td style="width: 30%; text-align: center;">
                @if ($tipo_catalogo == 1)
                  <img class="img-fluid" src="https://apisocial.internow.com.mx/pdfs_goopy/images/imagen_comprimida_thumbnail_goopy.png" alt="" style="max-width: 60% !important;">
                @endif
              </td>
              <td style="width: 60%;">
                <div style="font-weight: bold; font-size: 40px; color: #ff9c00;">
                  {{ $producto->nombre }}
                </div> 
                <div style="text-align: center; font-weight: bold; font-size: 26px; color: #ff9c00; margin-top: -12px;">
                  @if ($tipo_catalogo == 1)
                    PRECIO ${{ number_format($producto->colores[0]->tipos[0]->precio, 2) }} MXN
                  @endif
                </div>
              </td>
              <td style="width: 10%;">
              </td>
            </tr>
          </table>
          

            <table width="100%">
              <tr>
                <td style="width: 30%;">
                  @foreach ($producto->imagenes as $imagen)
                      <div style="text-align: center; width: 100%;">
                          <img class="img-fluid" src="{{ $imagen->url }}" alt="" style="max-width: 98% !important; max-height: 180px !important; border-radius: 50px 20px; padding: 2px;">
                      </div>
                  @endforeach
                </td>
                {{-- <td style="width: 60%;">

                  <div class="row" style="font-size: 14px;
                      display: flex;
                      justify-content: space-between;
                      height: 8in; /* asegúrate de que el contenedor tenga una altura definida*/
                      align-items: center;">
                  
                      @foreach ($producto->colores as $color)

                        <div class="col-12" style="font-size: 14px; width: 100%;">
                            Tallas disponibles: Color <strong>{{ strtoupper($color->nombre) }}</strong>
                            <div class="row" style="margin-left: 4px;">
                                @foreach ($color->tipos as $tipo)
                                    @if ($tipo->stock > 0)
                                        <div class="col-12">
                                            <strong>{{ $tipo->nombre }}</strong> | {{ $tipo->stock }} Disponibles.
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                      @endforeach

                  </div>
                  
                </td> --}}
                <td style="width: 60%; font-size: 14px;">
                  @foreach ($producto->colores as $color)
                      <div
                       @if (count($producto->colores) == 2) style=" margin-bottom: 300px" @endif
                       @if (count($producto->colores) == 3) style=" margin-bottom: 200px" @endif
                       @if (count($producto->colores) == 4) style=" margin-bottom: 50px" @endif
                       
                       >
                          Tallas disponibles: Color <strong>{{ strtoupper($color->nombre) }}</strong>
                          <div style="margin-left: 4px;">
                              @foreach ($color->tipos as $tipo)
                                  @if ($tipo->stock > 0)
                                      <div>
                                          <strong>{{ $tipo->nombre }}</strong> | {{ $tipo->stock }} Disponibles.
                                      </div>
                                  @endif
                              @endforeach
                          </div>
                      </div>
                  @endforeach
                </td>
                <td style="width: 10%; text-align: center;">
                  <img class="img-fluid" src="https://apisocial.internow.com.mx/pdfs_goopy/images/imagen_comprimida_catalogo.jpg" alt="" style="width: 100%; height: 9in;">
                </td>
              </tr>
            </table>
  

            {{-- <div class="row" style="position: absolute;
                  bottom: 0;
                  left: 0;">
                <div class="col-sm-12 text-center" style="margin: 0; padding: 0; width: 9in;">
                    <img class="img-fluid pie-row" src="https://apisocial.internow.com.mx/pdfs_goopy/images/pie.PNG" alt="" style="width: 100%;">
                </div>
            </div> --}}
        </div>
      @endforeach

</body>
</html>
