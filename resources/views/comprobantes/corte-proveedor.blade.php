<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte Proveedor</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <style>
        @page { margin: 10mm 0mm; }
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            color: #333;
        }

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

        .content {
            margin-top: 110px; /* Incrementado para dar aire abajo del header */
            margin-bottom: 80px;
            padding: 0 40px; /* Margen interno para que no pegue a los bordes de la hoja */
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

        /* Sección de Firmas */
        .signature-section {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .signature-line {
            border-top: 1px solid #cbd5e1;
            width: 80%;
            margin: 0 auto 8px auto;
        }
    </style>
</head>
<body>

    <div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
        
        <img src="{{ $header }}" alt="Encabezado" class="top-image">
        
        <div class="content">

            <div class="info-box">
                <div class="info-title">Detalles del Comprobante</div>
                <div class="info-body">
                    <table width="100%">
                        <tr>
                            <td style="width: 60%; font-size: 14px; vertical-align: top;">
                                <span style="color: #64748b;">Proveedor:</span><br>
                                <strong style="font-size: 16px; color: #0f172a;">{{ $data->supplier->razon_social }}</strong>
                            </td>
                            <td style="width: 40%; font-size: 14px; text-align: right; vertical-align: top;">
                                <span style="color: #64748b;">Fecha de Emisión:</span><br>
                                <strong style="color: #0f172a;">{{ \Carbon\Carbon::parse($data->created_at)->format('d-m-Y h:i A') }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="info-box">
                <div class="info-title">Resumen de Liquidación</div>
                <div class="info-body">
                    <table class="table-summary">
                        <tr>
                            <td style="color: #475569;">Total Bruto Vendido:</td>
                            <td class="text-right" style="font-weight: 500;">${{ number_format($data->total_vendido, 2, '.', ',') }}</td>
                        </tr>
                        
                        <tr>
                            <td style="color: #475569;">Comisión Retenida (10%):</td>
                            <td class="text-right" style="color: #b91c1c;">-${{ number_format($data->total_vendido - $data->monto, 2, '.', ',') }}</td>
                        </tr>

                        <tr class="border-top-dash">
                            <td style="font-weight: bold; font-size: 16px; color: #1e3a8a;">Total Neto a Entregar:</td>
                            <td class="text-right" style="font-weight: bold; font-size: 18px; color: #1e3a8a;">
                                ${{ number_format($data->monto, 2, '.', ',') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="info-box" style="margin-bottom: 40px;">
                <div class="info-body" style="padding: 12px 15px;">
                    <table width="100%">
                        <tr>
                            <td style="font-size: 14px; color: #475569;">
                                <span>Método de pago:</span> 
                                <strong style="color: #0f172a; text-transform: uppercase; margin-left: 5px;">{{ $data->metodo_pago }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="signature-section">
                <tr>
                    <td style="width: 50%;">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            Entregado por<br>
                            <strong>Plaza del Vestido</strong>
                        </div>
                    </td>
                    <td style="width: 50%;">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            Recibido por<br>
                            <strong>{{ $data->supplier->razon_social }}</strong>
                        </div>
                    </td>
                </tr>
            </table>

        </div>

        <img src="{{ $footer }}" alt="Pie de página" class="bottom-image">
        
    </div>
    
</body>
</html>