<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Pedido</title>

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

            <div style="padding: 15px; font-size: 12px;height: 25px;">

              <div><strong>PEDIDO</strong> #{{$pedido->orden}}</div>
              <div><strong>FECHA:</strong> {{$pedido->created_at}} </div>

              <div style="text-align: center;"> 
                <strong>PRODUCTOS SOLICITADOS</strong>    
              </div>

              <table class="table table-sm" style="width: 100%;">
                <thead>
                  <tr>
                      <th scope="col">CANT</th>
                      <th scope="col">PRODUCTO</th>
                      <th scope="col">COLOR</th>
                      <th scope="col">TALLA</th>
                      <th scope="col">P.U.</th>
                      <th scope="col">IMPORTE</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($pedido->detalles as $item)
                      <tr style="font-size: 12px;height: 25px;">
                        <td style="text-align: center;">
                          {{$item->cantidad}}
                        </td>
                        <td style="text-align: center;">
                          {{$item->producto->nombre}}
                        </td>
                        <td style="text-align: center;">
                          {{$item->color->nombre}}
                        </td>
                        <td style="text-align: center;">
                          {{$item->tipo->nombre}}
                        </td>
                        <td style="text-align: center;">
                          {{$item->precio_unitario}}
                        </td>
                        <td style="text-align: center;">
                          {{$item->cantidad * $item->precio_unitario}}
                        </td>
                      </tr>
                  @endforeach
                  <tr style="text-align: right;height: 25px;">
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td><strong>SUBTOTAL</strong></td>
                      <td style="text-align: center;">{{$pedido->subtotal}}</td>
                  </tr>
                  @if ($pedido->envio > 0)
                    <tr style="text-align: right;height: 25px;">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td><strong>ENVÍO</strong></td>
                        <td style="text-align: center;">{{$pedido->envio}}</td>
                    </tr>  
                  @endif             
                  <tr style="text-align: right;height: 25px;">
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td><strong>TOTAL</strong></td>
                      <td style="text-align: center;">{{$pedido->total}}</td>
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