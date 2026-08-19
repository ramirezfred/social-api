<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Ruta de Recolección</title>

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
          margin-top: 60px;
          margin-bottom: 60px;
          padding: 10px 15px;
      }

      p {
          page-break-inside: avoid;
          margin: 3px 0;
      }

      .supplier-card {
          border: 1px solid #ccc;
          margin-bottom: 15px;
          page-break-inside: avoid;
      }

      .supplier-header {
          background-color: rgba({{ $r }}, {{ $g }}, {{ $b }}, 0.2);
          padding: 6px 10px;
          border-bottom: 1px solid #ccc;
      }

      .quote-section {
          padding: 6px 8px;
          border-bottom: 1px dashed #e0e0e0;
      }

      .table-items {
          width: 100%;
          border-collapse: collapse;
          margin-top: 5px;
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

      /* Contenedor de imágenes optimizado para vista móvil */
      .row-images-container {
          background-color: #f9f9f9;
          padding: 12px 8px;
          border-bottom: 1px solid #ddd;
          text-align: center; /* Centra el bloque de imágenes */
      }

      /* Imágenes de gran tamaño para fácil lectura en celular */
      .img-preview {
          width: 200px;         /* Ancho amplio para vista móvil */
          height: 230px;        /* Alto suficiente para ver textura/detalles */
          object-fit: cover;
          border-radius: 6px;
          border: 1px solid #bbb;
          margin: 6px 4px;
          display: inline-block;
          vertical-align: middle;
      }
    </style>

</head>
<body>

    <div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
        
        <!-- Encabezado fijo -->
        <img src="{{ $header }}" alt="Encabezado" class="top-image">
        
        <!-- Contenido -->
        <div class="content">

            <table width="100%" style="margin-bottom: 10px;">
              <tr>
                <td style="width: 60%; font-size: 13px;">
                  <strong>Ruta de Recolección</strong> 
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
                  <table width="100%">
                    <tr>
                      <td style="width: 50%; font-size: 11px;">
                        <strong>Proveedor:</strong> {{ $supplier['razon_social'] }}
                      </td>
                      <td style="width: 50%; text-align: right; font-size: 10px;">
                        <strong>Contacto:</strong> {{ $supplier['contacto'] ?? 'N/A' }} ({{ $supplier['telefono'] ?? 'S/D' }})
                      </td>
                    </tr>
                    <tr>
                      <td colspan="2" style="font-size: 9px; color: #444; padding-top: 2px;">
                        <strong>Dirección:</strong> {{ $supplier['direccion'] }}
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
                            <strong>Folio:</strong> {{ $quote['folio'] }} | 
                            <strong>Cliente:</strong> {{ $quote['cliente'] }} | 
                            <strong>Teléfono:</strong> {{ $quote['telefono'] ?? 'N/A' }}
                          </td>
                          <td style="text-align: right;">
                            <span style="background-color: #eee; padding: 2px 4px; border-radius: 3px; font-size: 8px;">
                              Estado: {{ ucfirst($quote['estado']) }}
                            </span>
                          </td>
                        </tr>
                      </table>

                      <!-- Detalle de Productos -->
                      <table class="table-items">
                        <thead>
                          <tr>
                            <th>Modelo</th>
                            <th>Talla</th>
                            <th>Color</th>
                            <th>Cant. Total</th>
                            <th>Recolectado</th>
                            <th>Pendiente</th>
                            <th>Precio U.</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach ($quote['detalles'] as $detalle)
                            <!-- Fila 1: Datos del Producto -->
                            <tr>
                              <td><strong>{{ $detalle['modelo'] }}</strong></td>
                              <td style="text-align: center;">{{ $detalle['talla'] }}</td>
                              <td style="text-align: center;">{{ $detalle['color'] }}</td>
                              <td style="text-align: center;">{{ $detalle['cantidad_total'] }}</td>
                              <td style="text-align: center;">{{ $detalle['cantidad_recolectada'] }}</td>
                              <td style="text-align: center; font-weight: bold; color: #d9534f;">
                                {{ $detalle['cantidad_pendiente'] }}
                              </td>
                              <td style="text-align: right;">
                                ${{ number_format($detalle['precio_unitario'], 2, '.', ',') }}
                              </td>
                            </tr>

                            <!-- Fila 2: Imágenes en formato grande para celular -->
                            @if(!empty($detalle['imagenes']) && count($detalle['imagenes']) > 0)
                              <tr>
                                <td colspan="7" class="row-images-container">
                                  <div style="font-size: 10px; color: #444; margin-bottom: 6px; text-align: center;">
                                    <strong>Imágenes del producto (Máx. 5):</strong>
                                  </div>
                                  {{-- Muestra hasta 5 imágenes con tamaño grande --}}
                                  @foreach(array_slice($detalle['imagenes'], 0, 5) as $imgUrl)
                                    <img src="{{ $imgUrl }}" class="img-preview" alt="Imagen del producto">
                                  @endforeach
                                </td>
                              </tr>
                            @endif
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  @endforeach
                </div>

              </div>
            @empty
              <div style="text-align: center; padding: 20px; color: #777;">
                <p>No se encontraron rutas de recolección pendientes.</p>
              </div>
            @endforelse

        </div>

        <!-- Pie de página fijo -->
        <img src="{{ $footer }}" alt="Pie de página" class="bottom-image">
        
    </div>
    
</body>
</html>