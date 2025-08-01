<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización</title>

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

        /* Estilos para el div contenedor */
        .container {
            width: 8.5in; /* Ancho de hoja carta en pulgadas menos los márgenes izquierdo y derecho (8.5in - 1in - 1in) */
            height: 11in; /* Altura de hoja carta en pulgadas */
            position: relative; /* Para posicionar las imágenes dentro del div */
            margin: 0 auto; /* Margen superior e inferior 0, margen izquierdo y derecho automático para centrar */
            padding: 0; /* Eliminar relleno */
            overflow: hidden; /* Evitar desbordamiento de contenido */

        }

        /* Estilos para la imagen superior */
        .top-image {
            position: absolute; /* Posición absoluta dentro del div */
            top: 0; /* Arriba del div */
            left: 0; /* A la izquierda del div */
            width: 100%; /* Ancho completo del div */
            height: auto; /* Altura automática para mantener la proporción de la imagen */
        }

        /* Estilos para la imagen inferior */
        .bottom-image {
            position: absolute; /* Posición absoluta dentro del div */
            bottom: 0; /* Abajo del div */
            left: 0; /* A la izquierda del div */
            width: 100%; /* Ancho completo del div */
            height: 90px; /* Altura automática para mantener la proporción de la imagen */
        }

        /* Estilos para el contenido del div */
        .content {
            margin-top: 154px; /* Margen superior para evitar que el texto se oculte detrás de la imagen superior */
        }
    </style>

</head>
<body>

    <div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
        <!-- Imagen superior -->
        <img class="top-image" src={{$header}} alt="Imagen Superior">

        <!-- Contenido del div -->
        <div class="content">

          <div style="padding: 15px; font-size: 14px;height: 25px;">

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                  <strong>Cotización:</strong> #{{$cotizacion->orden}}
                </td>
                <td style="width: 30%;">
                  <strong>Fecha:</strong> {{$cotizacion->created_at}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                  <strong>A quien corresponda:</strong> {{$cotizacion->cliente}}
                </td>
                <td style="width: 30%;">
                  <strong>Teléfono:</strong> {{$cotizacion->telefono}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                </td>
                <td style="width: 30%;">
                  <strong>Email:</strong> {{$cotizacion->email}}
                </td>
              </tr>
            </table>

            <br>

            <div style="text-align: center;"> 
              <strong>Descripción del servicio solicitado</strong>    
            </div>

            <table class="table table-sm" style="width: 100%; font-size: 14px; margin-top: 50px;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                    <th scope="col">Descripción</th>
                    <th scope="col">Precio</th>
                    <th scope="col">Cantidad</th>
                    <th scope="col" style="text-align: right;">Linea Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($cotizacion->gastos as $item)
                    <tr style="height: 25px;">
                      <td>
                        {{$item->descripcion}}
                      </td>
                      <td>
                        $ {{$item->valorUnitario}}
                      </td>
                      <td>
                        {{$item->cantidad}}
                      </td>
                      <td style="text-align: right; margin-right: 8px;">
                        $ {{$item->importe}}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="4" style="height: 40px;">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 15px">
                    <td colspan="3" style="text-align: right;"><strong>Subtotal</strong></td>
                    <td style="text-align: right; margin-right: 8px;">$ {{$cotizacion->subtotal}}</td>
                </tr>
                @if ($cotizacion->tipo == 2)
                  <tr style="height: 25px; font-size: 15px">
                    <td colspan="3" style="text-align: right;"><strong>Iva</strong></td>
                    <td style="text-align: right; margin-right: 8px;">$ {{$cotizacion->iva}}</td>
                  </tr>  
                @endif
                @if ($cotizacion->envio > 0)
                  <tr style="height: 25px; font-size: 15px">
                    <td colspan="3" style="text-align: right;"><strong>Envío</strong></td>
                    <td style="text-align: right; margin-right: 8px;">$ {{$cotizacion->envio}}</td>
                  </tr>  
                @endif 
                <tr>
                  <td colspan="4" style="height: 40px;">&nbsp;</td>
                </tr>            
                <tr style="height: 25px; font-size: 15px">
                  <td colspan="3" style="text-align: right;"><strong>Total</strong></td>
                  <td style="text-align: right; margin-right: 8px;"><strong>$ {{$cotizacion->total}}</strong></td>
                </tr>
              </tbody>
            </table>

          </div>

            
        </div>

        <!-- Imagen inferior -->
        <img class="bottom-image" src={{$footer}} alt="Imagen Inferior">
    </div>
    
</body>
</html>
