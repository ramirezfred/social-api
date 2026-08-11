<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero</title>

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
        <img src="{{ $header }}" alt="Encabezado" class="top-image">
        
        <!-- Contenido que fluye entre páginas -->
        <div class="content">

          <div style="padding: 15px; font-size: 12px;height: 25px;">

            <br>

            <table width="100%">
              <tr>
                <td style="width: 70%;">
                  <strong>Reporte Financiero</strong> 
                </td>
                <td style="width: 30%;">
                  <strong>Fecha de Generación:</strong> {{ now()->format('d-m-Y') }}
                </td>
              </tr>
            </table>

            <br>
            <br>

            <div style="text-align: center; font-size: 14px;"> 
              <strong>Ventas Semanales</strong>    
            </div>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                  <th scope="col"></th>
                  <th scope="col">Semana</th>
                  <th scope="col" style="text-align: center;">Total</th>
                  <th scope="col" style="text-align: center;">Comisión (%)</th>
                  <th scope="col" style="text-align: center;">Monto Comisión</th>
                  <th scope="col" style="text-align: right;">Envíos Cobrados</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($ventas_semanales['reporte'] as $item)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{ \Carbon\Carbon::parse($item['fecha_inicio'])->format('d-m-Y') }} - {{ \Carbon\Carbon::parse($item['fecha_fin'])->format('d-m-Y') }}
                      </td>
                      <td style="text-align: center;">
                        ${{ number_format($item['total_semanal'], 2, '.', ',') }}
                      </td>
                      <td style="text-align: center;">
                        {{ $item['comision_percent'] }}
                      </td>
                      <td style="text-align: center;">
                        ${{ number_format($item['descuento_comision'], 2, '.', ',') }}
                      </td>
                      <td style="text-align: right; margin-right: 8px;">
                        ${{ number_format($item['costo_envios'], 2, '.', ',') }}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="6">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 14px;">
                    <td colspan="2" style="text-align: right;"><strong>TOTALES GENERALES</strong></td>
                    <td style="text-align: center;">
                      <strong>${{ number_format($ventas_semanales['totales_generales']['total'], 2, '.', ',') }}</strong>
                    </td>
                    <td style="text-align: center;">
                      -
                    </td>
                    <td style="text-align: center;">
                      <strong>${{ number_format($ventas_semanales['totales_generales']['comision'], 2, '.', ',') }}</strong>
                    </td>
                    <td style="text-align: right; margin-right: 8px;">
                      <strong>${{ number_format($ventas_semanales['totales_generales']['envios'], 2, '.', ',') }}</strong>
                    </td>
                </tr>
              </tbody>
            </table>

            <br>
            <br>

            <div style="text-align: center; font-size: 14px;"> 
              <strong>Gastos</strong>    
            </div>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                  <th scope="col"></th>
                  <th scope="col">Concepto</th>
                  <th scope="col" style="text-align: center;">Fecha</th>
                  <th scope="col" style="text-align: right;">Importe</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($gastos['reporte'] as $item)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{ $item['concepto'] }}
                      </td>
                      <td style="text-align: center;">
                        {{ \Carbon\Carbon::parse($item['fecha'])->format('d-m-Y') }}
                      </td>
                      <td style="text-align: right; margin-right: 8px;">
                        ${{ number_format($item['monto'], 2, '.', ',') }}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="4">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 14px;">
                    <td colspan="3" style="text-align: right;"><strong>TOTAL GENERAL</strong></td>
                    <td style="text-align: right; margin-right: 8px;">
                      <strong>${{ number_format($gastos['totales_generales'], 2, '.', ',') }}</strong>
                    </td>
                </tr>
              </tbody>
            </table>

            <br>
            <br>
            

            <div style="text-align: center; font-size: 14px;"> 
              <strong>Nómina</strong>    
            </div>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                  <th scope="col"></th>
                  <th scope="col">Concepto</th>
                  <th scope="col" style="text-align: center;">Fecha</th>
                  <th scope="col" style="text-align: right;">Importe</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($nominas['reporte'] as $item)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{ $item['concepto'] }}
                      </td>
                      <td style="text-align: center;">
                        {{ \Carbon\Carbon::parse($item['fecha'])->format('d-m-Y') }}
                      </td>
                      <td style="text-align: right; margin-right: 8px;">
                        ${{ number_format($item['monto'], 2, '.', ',') }}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="4">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 14px;">
                    <td colspan="3" style="text-align: right;"><strong>TOTAL GENERAL</strong></td>
                    <td style="text-align: right; margin-right: 8px;">
                      <strong>${{ number_format($nominas['totales_generales'], 2, '.', ',') }}</strong>
                    </td>
                </tr>
              </tbody>
            </table>

            <br>
            <br>
            

            <div style="text-align: center; font-size: 14px;"> 
              <strong>Adeudo a Proveedores</strong>    
            </div>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                  <th scope="col"></th>
                  <th scope="col">Tipo</th>
                  <th scope="col">Proveedor</th>
                  <th scope="col" style="text-align: center;">Total Vendido</th>
                  <th scope="col" style="text-align: right;">Total Neto Pendiente (-10%)</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($deuda_proveedores['reporte'] as $item)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td>
                        {{ $item->tipo }}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{ $item->razon_social }}
                      </td>
                      <td style="text-align: center;">
                        ${{ number_format($item->total_vendido, 2, '.', ',') }}
                      </td>
                      <td style="text-align: right; margin-right: 8px;">
                        ${{ number_format($item->total_deuda, 2, '.', ',') }}
                      </td>
                    </tr>
                @endforeach
                <tr>
                  <td colspan="5">&nbsp;</td>
                </tr>
                <tr style="height: 25px; font-size: 14px;">
                    <td colspan="4" style="text-align: right;"><strong>TOTAL GENERAL</strong></td>
                    <td style="text-align: right; margin-right: 8px;">
                      <strong>${{ number_format($deuda_proveedores['totales_generales'], 2, '.', ',') }}</strong>
                    </td>
                </tr>
              </tbody>
            </table>

            <br>
            <br>

            <div style="text-align: center; font-size: 14px;"> 
              <strong>Resumen</strong>    
            </div>

            <table style="width: 100%;">
              <tr>
                  <td style="width: 80%;"><strong>Total Generado</strong> (Comisiones + Envíos)</td>
                  <td style="width: 20%; text-align: right; margin-right: 8px;">
                    <strong>${{ number_format($totales_generales['total_generado'], 2, '.', ',') }}</strong>
                  </td>
              </tr>
              <tr>
                  <td><strong>Total Ingresos</strong> (Total Generado - Gastos)</td>
                  <td style="text-align: right; margin-right: 8px;">
                    <strong>${{ number_format($totales_generales['total_ingresos_a'], 2, '.', ',') }}</strong>
                  </td>
              </tr>
              <!-- <tr>
                  <td><strong>Total Ingresos B</strong> (Total Generado - Gastos - Nómina)</td>
                  <td style="text-align: right; margin-right: 8px;">
                    <strong>${{ number_format($totales_generales['total_ingresos_b'], 2, '.', ',') }}</strong>
                  </td>
              </tr> -->
              <tr>
                  <td><strong>Adeudo a Proveedores</strong></td>
                  <td style="text-align: right; margin-right: 8px;">
                    <strong>${{ number_format($totales_generales['total_deuda_proveedores'], 2, '.', ',') }}</strong>
                  </td>
              </tr>
              <tr style="font-size: 14px;">
                  <td>
                    <strong>Total Disponible en Caja</strong> (Total Generado - Gastos - Nómina)
                  </td>
                  <td style="text-align: right; margin-right: 8px;">
                    <strong>${{ number_format($totales_generales['total_caja'], 2, '.', ',') }}</strong>
                  </td>
              </tr>
            </table>

            
          </div>

        </div>

        <!-- Pie de página que se repetirá en cada página -->
        <img src="{{$footer}}" alt="Pie de página" class="bottom-image">
        
    </div>
    
</body>
</html>
