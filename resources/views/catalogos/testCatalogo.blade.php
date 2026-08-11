<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Catálogo</title>
    <style>
      @page { margin: 0; size: A4; }
      * { box-sizing: border-box; margin: 0; padding: 0; }

      body {
          font-family: Arial, sans-serif;
          font-size: 12px;
          color: #2c2c2c;
      }

      .page-break { page-break-after: always; }

      /* ── Badges ── */
      .badge {
          display: inline-block;
          border: 1.5px solid #555;
          color: #2c2c2c;
          border-radius: 20px;
          padding: 2px 10px;
          font-size: 13px;
          margin: 2px 3px 2px 0;
      }
      .badge-dark {
          border-color: #fff;
          color: #fff;
      }

      .seccion-label {
          font-size: 26px;
          font-weight: bold;
          margin-bottom: 5px;
      }
    </style>
</head>
<body>

{{-- ══════════════ PORTADA ══════════════ --}}
{{-- Usamos una tabla de 2 filas para simular el fondo dividido --}}
<table width="100%" cellpadding="0" cellspacing="0" class="page-break"
       style="width: 210mm; height: 297mm;">

    {{-- Mitad superior: blanco --}}
    <tr>
        <td height="148mm" bgcolor="#ffffff" align="center" valign="middle"
            style="padding: 40px 40px 20px 40px;">
            <img src="{{ $logo }}" style="width: 460px; height: 460px;">
        </td>
    </tr>

    {{-- Mitad inferior: azul claro --}}
    <tr>
        <td bgcolor="#5bc8d8" valign="top"
            style="padding: 0 40px 30px 40px;">

            {{-- Sub-tabla: empresa | bloque título --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 120px;">
                <tr>
                    <td width="140" valign="top" style="padding-top: 10px;">
                        <span style="font-size: 16px; font-weight: bold; color: #1a4fa0;
                                     line-height: 1.8;">
                            PLAZA DEL<br>VESTIDO DE<br>TULANCINGO S.C
                        </span>
                    </td>
                    <td valign="top">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td bgcolor="#1a4fa0"
                                    style="padding: 50px 20px; text-align: center;
                                           font-size: 36px; font-weight: 900;
                                           color: #fff; line-height: 1.3;">
                                    CATÁLOGO<br>VENTA<br>EN LÍNEA
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Logo pequeño --}}
            <img src="{{ $logo }}" style="width: 70px; margin-top: 56px;">
        </td>
    </tr>
</table>

{{-- ══════════════ PÁGINAS DE PRODUCTO ══════════════ --}}
@foreach($data as $index => $item)
@php
    $isLight = ($index % 2 === 0);
    $bg      = $isLight ? '#b8d4e8' : '#1a4fa0';
    $textCol = $isLight ? '#1a1a1a' : '#ffffff';
    $divCol  = $isLight ? '#1a4fa0' : '#ffffff';
    $badgeClass = $isLight ? 'badge' : 'badge badge-dark';
    $isLast  = ($index === count($data) - 1);

    $imgs = $item['images'] ?? [];
    $img0 = $imgs[0]['url'] ?? '';
    $img1 = $imgs[1]['url'] ?? '';
    $img2 = $imgs[2]['url'] ?? '';
    $img3 = $imgs[3]['url'] ?? '';
    $img4 = $imgs[4]['url'] ?? '';

    $razonSocial = $item['supplier']['razon_social'] ?? '';
    $fontSize = (mb_strlen($razonSocial) > 25) ? '32px' : '46px';
@endphp

<table width="100%" cellpadding="0" cellspacing="0"
       style="width: 210mm; height: 297mm; background: {{ $bg }};"
       class="{{ !$isLast ? 'page-break' : '' }}">
<tr>
    <td valign="top" style="padding: 30px 26px 18px 26px;">

        {{-- Encabezado --}}
        <div style="text-align: center; margin-bottom: 30px;">
            <span style="font-size: {{ $fontSize }}; font-weight: bold; font-style: italic;
                         color: {{ $isLight ? '#1a4fa0' : '#fff' }};">
                {{ $item['supplier']['razon_social'] }}
            </span><br>
            <span style="font-size: 26px; font-weight: bold; text-decoration: underline;
                         color: {{ $textCol }};">
                {{ $item['extraido']['producto'] }}
            </span>
        </div>

        {{-- Galería --}}
        <table width="100%" cellpadding="4" cellspacing="0"
               style="margin-bottom: 20px; table-layout: fixed; height: 650px;">
            <tr>
                {{-- Imagen principal --}}
                <td width="42%" valign="top" rowspan="2" style="padding-right: 6px;">
                    @if($img0)
                        <img src="{{ $img0 }}"
                             style="width: 100%; height: 475px;
                                    object-fit: cover; border-radius: 6px; display: block; margin-top: 90px;">
                    @endif
                </td>
                {{-- Fila 1 del grid --}}
                <td width="29%" valign="top" style="padding-bottom: 4px;">
                    @if($img1)
                        <img src="{{ $img1 }}"
                             style="width: 90%; height: 260px;
                                    object-fit: cover; border-radius: 5px; display: block;">
                    @endif
                </td>
                <td width="29%" valign="top" style="padding-bottom: 4px;">
                    @if($img2)
                        <img src="{{ $img2 }}"
                             style="width: 90%; height: 260px;
                                    object-fit: cover; border-radius: 5px; display: block;">
                    @endif
                </td>
            </tr>
            <tr>
                {{-- Fila 2 del grid --}}
                <td width="29%" valign="top">
                    @if($img3)
                        <img src="{{ $img3 }}"
                             style="width: 90%; height: 260px;
                                    object-fit: cover; border-radius: 5px; display: block; margin-top: 90px;">
                    @endif
                </td>
                <td width="29%" valign="top">
                    @if($img4)
                        <img src="{{ $img4 }}"
                             style="width: 90%; height: 260px;
                                    object-fit: cover; border-radius: 5px; display: block; margin-top: 90px;">
                    @endif
                </td>
            </tr>
        </table>

        {{-- Separador --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
            <tr>
                <td style="border-top: 6px solid {{ $divCol }}; font-size: 0;">&nbsp;</td>
            </tr>
        </table>

        {{-- Tallas / Colores + Precio --}}
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                {{-- Izquierda --}}
                <td width="60%" valign="top">
                    <div class="seccion-label" style="color: {{ $textCol }};">Tallas</div>
                    @foreach($item['extraido']['tallas'] as $talla)
                        <span class="{{ $badgeClass }}" style="font-weight: bold;">{{ $talla }}</span>
                    @endforeach

                    <br style="line-height: 18px;"><br>

                    <div class="seccion-label" style="color: {{ $textCol }};">
                        Colores Disponibles
                    </div>
                    @foreach($item['extraido']['colores'] as $color)
                        <span class="{{ $badgeClass }}" style="font-weight: bold;">{{ $color }}</span>
                    @endforeach
                </td>

                {{-- Derecha: precio + logo --}}
                <td width="40%" valign="middle" align="right"
                    style="padding-left: 10px;">
                    <span style="font-size: 26px; font-weight: bold; letter-spacing: 3px;
                                 color: {{ $isLight ? '#555' : '#cce0f0' }};">
                        P R E C I O
                    </span><br>
                    <span style="font-size: 26px; font-weight: 900;
                                 color: {{ $textCol }};">
                        ${{ $item['extraido']['precio'] }} c/u
                    </span>
                    <span style="font-size: 11px; font-weight: bold;
                                 color: {{ $isLight ? '#555' : '#cce0f0' }};">MX</span>
                    <br>
                    <img src="{{ $isLight ? $logo : $logo2 }}"
                         style="width: 58px; margin-top: 100px;">
                </td>
            </tr>
        </table>

    </td>
</tr>
</table>

@endforeach

</body>
</html>