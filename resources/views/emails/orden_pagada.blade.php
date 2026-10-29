<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden Pagada</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">

<div style="margin: 0 auto !important; padding: 10px 0 !important; width: 100%; max-width: 816px;">
    
    <!-- Imagen arriba en el centro -->
    <img src="{{ $message->embed(public_path('images/ordenPlazaVestido/logoPlazaDelVestido.png')) }}" alt="Plaza del Vestido"
        style="display: block;
            margin: 0 auto 15px auto;
            width: 140px;
            height: auto;" 
    >

    <!-- Borde azul ajustado -->
    <div style="background-color: #FFFFFF;
        padding: 15px 10px;
        border: 12px solid #0062BD;
        box-sizing: border-box;">

        <h2 style="font-size: 18px; text-align: center; color: #333;">¡La orden #{{ $quote->folio }} ha sido pagada en su totalidad!</h2>
        <p style="text-align: center; font-size: 15px;"><strong>Total Orden:</strong> ${{ number_format($quote->total, 2) }}</p>

        <h3 style="font-size: 16px; margin-top: 20px;">Resumen de Pagos Registrados</h3>
        
        <!-- Envoltorio con overflow para evitar rotura de diseño -->
        <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 12px; table-layout: auto;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="padding: 6px 4px; text-align: left; white-space: nowrap;">Fecha</th>
                        <th style="padding: 6px 4px; text-align: left;">Tipo</th>
                        <th style="padding: 6px 4px; text-align: right; white-space: nowrap;">Monto</th>
                        <th style="padding: 6px 4px; text-align: left;">Ref.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quote->pagos as $pago)
                        <tr>
                            <td style="padding: 6px 4px; white-space: nowrap;">{{ $pago->fecha->format('d/m/Y') }}</td>
                            <td style="padding: 6px 4px;">
                                {{ $pago->tipo === 'efectivo' ? 'Efectivo' : ($pago->tipo === 'transferencia' ? 'Transf.' : ucfirst($pago->tipo)) }}
                            </td>
                            <td style="padding: 6px 4px; text-align: right; white-space: nowrap; font-weight: bold; color: #28a745;">${{ number_format($pago->monto, 2) }}</td>
                            <td style="padding: 6px 4px; word-break: break-word;">{{ $pago->referencia ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <br><br><br>

        <!-- Firma abajo -->
        <div style="text-align: center;">
            <span style="color: #000000; font-weight: bold;">
                Plaza del Vestido
            </span>
        </div>

    </div>

</div>

</body>
</html>