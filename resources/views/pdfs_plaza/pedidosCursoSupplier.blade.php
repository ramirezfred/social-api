<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos en Curso</title>

    <style>
      /* Configuración general para cada página */
      @page { margin: 10mm 0mm; } 
      body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }

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
        padding: 10px 15px;
      }

      p {
          margin: 3px 0;
      }

      /* CAMBIO CLAVE: Se permite romper la tarjeta para que aproveche el espacio sobrante */
      .supplier-card {
          border: 1px solid #ccc;
          margin-bottom: 15px;
          page-break-inside: auto; 
      }

      /* Evita que el título del proveedor quede aislado al final de la página */
      .supplier-header {
          background-color: rgba({{ $r }}, {{ $g }}, {{ $b }}, 0.2);
          padding: 6px 10px;
          border-bottom: 1px solid #ccc;
          page-break-after: avoid; 
      }

      .quote-section {
          padding: 6px 8px;
          border-bottom: 1px dashed #e0e0e0;
          page-break-inside: auto;
      }

      .table-items {
          width: 100%;
          border-collapse: collapse;
          margin-top: 5px;
      }

      /* Mantiene la cabecera visible si la tabla se corta entre páginas */
      .table-items thead {
          display: table-header-group;
      }

      /* CAMBIO CLAVE: Evita que una fila (con su foto de 180px) se corte por la mitad */
      .table-items tr {
          page-break-inside: avoid;
      }

      .table-items th {
          background-color: #f1f1f1;
          border: 1px solid #ddd;
          padding: 5px;
          font-size: 9px;
          text-align: center;
      }

      .table-items td {
          padding: 5px;
          border: 1px solid #e9e9e9;
          vertical-align: middle;
      }

      /* Medidas originales restauradas */
      .img-product {
          width: 30px;
          height: 60px;
          object-fit: cover;
          border-radius: 4px;
          border: 1px solid #ccc;
          display: block;
          margin: 0 auto;
      }

      /* Estructura de Tarjeta/Bloque */
      .info-box {
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          background: #fff;
          margin-bottom: 25px;
          width: 100%;
      }

      .info-title {
          background-color: #f8fafc;
          border-bottom: 1px solid #e2e8f0;
          padding: 10px 15px;
          font-size: 13px;
          font-weight: bold;
          color: #1e3a8a; /* Azul institucional */
          text-transform: uppercase;
          letter-spacing: 0.5px;
      }

      .info-body {
          padding: 15px;
      }

      /* Tablas de datos */
      .table-summary {
          width: 100%;
          margin-top: 10px;
      }
      .table-summary td {
          padding: 8px 12px;
          font-size: 14px;
      }
      .text-right {
          text-align: right;
      }

      /* Línea divisoria interna */
      .border-top-dash {
          border-top: 1px dashed #cbd5e1;
      }
    </style>

</head>
<body>

    <div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
        
      <!-- Contenido -->
      <div class="content">

          <table width="100%">
            <tr>
              <td style="width: 60%; font-size: 13px;">
                <strong>Pedidos en Curso</strong> 
              </td>
              <td style="width: 40%; text-align: right; font-size: 10px;">
                <strong>Fecha de Generación:</strong> {{ now()->format('d-m-Y H:i') }}
              </td>
            </tr>
          </table>

          <!-- Bucle de Proveedores -->
          @forelse ($rutaRecoleccion as $supplier)
            <div class="supplier-card">
              
              <!-- Datos del Proveedor -->
              <div class="supplier-header">
                <table width="100%" style="border-collapse: collapse;">
                  <tr>
                    {{-- Columna izquierda: 80% --}}
                    <td width="80%" valign="top" style="padding-right: 10px;">
                      <table width="100%">
                        <tr>
                          <td style="font-size: 11px;">
                            <strong>Proveedor:</strong> {{ $supplier['razon_social'] }}
                          </td>
                        </tr>
                        <tr>
                          <td style="font-size: 9px; color: #444; padding-top: 2px;">
                            <strong>Dirección:</strong> {{ $supplier['direccion'] }}
                          </td>
                        </tr>
                      </table>
                    </td>

                    {{-- Columna derecha: 20% con logo alineado a la derecha --}}
                    <td width="20%" valign="middle" align="right">
                      <img src="{{ $logo }}" 
                          alt="Logo" 
                          style="max-width: 100%; max-height: 60px;">
                    </td>
                  </tr>
                </table>
              </div>

              <!-- Cotizaciones -->
              <div style="padding: 4px;">
                @foreach ($supplier['quotes'] as $quote)
                  <div class="quote-section">
                    <table width="100%" style="margin-bottom: 4px;">
                      <tr>
                        <td>
                          <strong>{{ $quote['folio'] }} </strong> 
                        </td>
                      </tr>
                    </table>

                    <!-- Detalle de Productos -->
                    <table class="table-items">
                      <thead>
                        <tr>
                          <th style="width: 60px;">Foto</th>
                          <!-- <th>Modelo</th> -->
                          <th>Talla</th>
                          <th>Color</th>
                          <th>Cant.</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($quote['detalles'] as $detalle)
                          <tr>
                            <!-- Columna de Imagen -->
                            <td style="text-align: center; width: 60px;">
                              @if(!empty($detalle['imagenes']) && count($detalle['imagenes']) > 0)
                                <img src="{{ $detalle['imagenes'][0] }}" class="img-product" alt="Producto">
                              @elseif(!empty($detalle['imagen']))
                                <img src="{{ $detalle['imagen'] }}" class="img-product" alt="Producto">
                              @else
                                <span style="color: #777; font-size: 10px;">Sin Foto</span>
                              @endif
                            </td>

                            <!-- Datos del Producto -->
                            <!-- <td><strong>{{ $detalle['modelo'] }}</strong></td> -->
                            <td style="text-align: center;">{{ $detalle['talla'] }}</td>
                            <td style="text-align: center;">{{ $detalle['color'] }}</td>
                            <td style="text-align: center;">
                              <strong>{{ $detalle['cantidad_total'] }}</strong>
                            </td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                @endforeach
              </div>

            </div>

            <div class="info-box">
                <div class="info-title">Resumen</div>
                <div class="info-body">
                    <table class="table-summary">
                        <tr>
                            <td style="color: #475569;">Total Bruto Vendido:</td>
                            <td class="text-right" style="font-weight: 500;">${{ number_format($total_vendido, 2, '.', ',') }}</td>
                        </tr>
                        
                        <tr>
                            <td style="color: #475569;">Comisión Retenida (10%):</td>
                            <td class="text-right" style="color: #b91c1c;">-${{ number_format($total_vendido - $total_entregado, 2, '.', ',') }}</td>
                        </tr>

                        <tr class="border-top-dash">
                            <td style="font-weight: bold; font-size: 16px; color: #1e3a8a;">Total Neto:</td>
                            <td class="text-right" style="font-weight: bold; font-size: 18px; color: #1e3a8a;">
                                ${{ number_format($total_entregado, 2, '.', ',') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

          @empty
            <div style="text-align: center; padding: 20px; color: #777;">
              <p>No se encontraron pedidos en curso.</p>
            </div>
          @endforelse

      </div>
        
    </div>
    
</body>
</html>