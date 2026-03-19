<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <style>
      /* Configuración general para cada página */
      @page { margin: 10mm 0mm; } /* Márgenes en cada página */
      body { font-family: Arial, sans-serif; }

      /* Encabezado y pie de página en posición fija */
      .top-image {
          position: fixed;
          top: -10mm;
          left: 0;
          width: 100%;
          height: auto;
      }

      .bottom-image {
          position: fixed;
          bottom: -10mm;
          left: 0;
          width: 100%;
          height: 50px;
      }

      /* Margen superior para evitar que el contenido se oculte tras el encabezado */
      .content {
          margin-top: 60px; /* Ajusta el valor para que se vea el encabezado */
          margin-bottom: 60px; /* Ajusta el valor para que se vea el pie de página */
      }

      /* Estilos de párrafos y contenido */
      p {
          page-break-inside: avoid; /* Evita cortes de párrafos entre páginas */
      }

      /* CSS para permitir que un párrafo se corte en varias líneas */
      .parrafo-multilinea {
            word-wrap: break-word; /* Permite que las palabras se dividan en múltiples líneas si no caben. */
            overflow-wrap: break-word; /* Otra opción compatible con navegadores modernos. */
            max-width: 600px; /* Puedes ajustar esto según tus necesidades. */
        }

    </style>

</head>
<body>

    <div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
        
        <!-- Encabezado que se repetirá en cada página -->
        <img src={{$header}} alt="Encabezado" class="top-image">
        
        <!-- Contenido que fluye entre páginas -->
        <div class="content">

          <div style="padding: 15px; font-size: 12px;height: 25px;">

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                  <strong>Cliente:</strong> {{ $data->cliente }}
                </td>
                <td style="width: 30%;">
                  <strong>No. orden: {{ $data->folio }}</strong>
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                  <strong>Telf:</strong> {{ $data->telefono }}
                </td>
                <td style="width: 30%;">
                  <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($data->created_at)->format('d-m-Y') }}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                  <strong>Moneda:</strong> {{ $data->moneda }}
                </td>
              </tr>
            </table>

            <br>

            <div style="text-align: center; font-size: 14px;"> 
              <strong>Detalle</strong>    
            </div>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                  <th scope="col"></th>
                  <th scope="col">Modelo</th>
                  <th scope="col" style="text-align: center;">Talla</th>
                  <th scope="col" style="text-align: center;">Color</th>
                  <th scope="col" style="text-align: center;">Cant. Pzs.</th>
                  <th scope="col" style="text-align: center;">Precio Unit.</th>
                  <th scope="col" style="text-align: right;">Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($data->detalles as $item)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{$item->modelo}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->talla}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->color}}
                      </td>
                      <td style="text-align: center;">
                          @if(floor($item->cantidad) == $item->cantidad)
                              {{ number_format($item->cantidad, 0, '.', ',') }}
                          @else
                              {{ number_format($item->cantidad, 2, '.', ',') }}
                          @endif
                      </td>
                      <td style="text-align: center;">
                        ${{ number_format($item->precio_unitario, 2, '.', ',') }}
                      </td>
                      <td style="text-align: right; margin-right: 8px;">
                        ${{ number_format($item->total, 2, '.', ',') }}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="7">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 14px">
                    <td colspan="6" style="text-align: right;"><strong>SUBTOTAL</strong></td>
                    <td style="text-align: right; margin-right: 8px;">
                      ${{ number_format($data->subtotal, 2, '.', ',') }}
                    </td>
                </tr>
                <tr style="height: 25px; font-size: 14px">
                  <td colspan="6" style="text-align: right;"><strong>Envío</strong></td>
                  <td style="text-align: right; margin-right: 8px;">
                    @if($data->envio !== null && $data->envio > 0)
                        ${{ number_format($data->envio, 2, '.', ',') }}
                    @endif
                  </td>
                </tr>           
                <tr style="height: 25px; font-size: 14px">
                  <td colspan="6" style="text-align: right;"><strong>TOTAL A PAGAR</strong></td>
                  <td style="text-align: right; margin-right: 8px;">
                    <strong>${{ number_format($data->total, 2, '.', ',') }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>

            <table style="width: 50%;">
              <tr>
                <td style="width: 20%;">
                  <strong>Adelanto:</strong>
                </td>
                <td style="width: 80%;">
                  ${{ number_format($data->total_pagado, 2, '.', ',') }}
                </td>
              </tr>
              
              <tr>
                <td style="width: 20%;">
                  <strong>Resta:</strong>
                </td>
                <td style="width: 80%;">
                  <strong>${{ number_format($data->saldo_restante, 2, '.', ',') }}</strong>
                </td>
              </tr>
            </table>

            <table width="100%" style="margin-top: 15px;">
              <tr>
                <td style="width: 100%;">
                  <strong>Condiciones:</strong>
                  <ul style="margin-top: 5px; margin-bottom: 0; padding-left: 20px;">
                      <li>Todos los pedidos se realizan más el costo de envío. En caso de tratarse de zona extendida, el costo de envío puede variar.</li>
                      <li>Los pedidos están sujetos a disponibilidad del día <strong>Jueves</strong>.</li>
                  </ul>
                </td>
              </tr>
            </table>

            
          </div>

        </div>

        <!-- Pie de página que se repetirá en cada página -->
        <img src={{$footer}} alt="Pie de página" class="bottom-image">
        
    </div>
    
</body>
</html>
