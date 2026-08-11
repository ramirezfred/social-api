<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte General de Ventas por Proveedor</title>

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
                  <strong>Reporte General de Ventas por Proveedor</strong> 
                </td>
                <td style="width: 30%;">
                  <strong>Fecha de Generación:</strong> {{ now()->format('d-m-Y') }}
                </td>
              </tr>
            </table>

            <table width="100%">
                <tr>
                    <td style="width: 100%;">
                        <strong>Desde el:</strong> {{ !empty($desde) ? \Carbon\Carbon::parse($desde)->format('d-m-Y') : 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <td style="width: 100%;">
                        <strong>Hasta el:</strong> {{ !empty($hasta) ? \Carbon\Carbon::parse($hasta)->format('d-m-Y') : 'N/A' }}
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
                  <th scope="col">Tipo</th>
                  <th scope="col">Nombre</th>
                  <th scope="col" style="text-align: center;">Tienda</th>
                  <th scope="col" style="text-align: center;">Total Compra</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($reporte as $item)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td>
                        {{ $item['tipo'] }} 
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{ $item['nombre'] }}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{ $item['tienda'] }}
                      </td>
                      <td style="text-align: center;">
                        ${{ number_format($item['total_compra'], 2, '.', ',') }}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="5">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 14px;">
                    <td colspan="4" style="text-align: right;"><strong>TOTAL GENERAL</strong></td>
                    <td style="text-align: center;">
                      <strong>${{ number_format($totales_generales['compra'], 2, '.', ',') }}</strong>
                    </td>
                </tr>
              </tbody>
            </table>

            
          </div>

        </div>

        <!-- Pie de página que se repetirá en cada página -->
        <img src={{$footer}} alt="Pie de página" class="bottom-image">
        
    </div>
    
</body>
</html>
