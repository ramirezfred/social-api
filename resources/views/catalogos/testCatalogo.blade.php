<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Catálogo</title>
    <style>
      @page { margin: 0; }

      body {
          font-family: Arial, sans-serif;
          font-size: 12px;
          color: #2c2c2c;
      }

      .top-image {
          position: fixed;
          top: 0; left: 0;
          width: 100%;
      }

      .bottom-image {
          position: fixed;
          bottom: 0; left: 0;
          width: 100%;
      }

      .page {
          padding: 120px 20px 60px 20px;
      }

      .page-break {
          page-break-after: always;
      }

      .seccion-label {
          font-size: 9px;
          font-weight: bold;
          color: #999;
          text-transform: uppercase;
          letter-spacing: 0.6px;
          margin-bottom: 3px;
      }

      .talla-badge {
          display: inline-block;
          background: #e8f0fb;
          border: 1px solid #1a4fa0;
          color: #0d3278;
          border-radius: 3px;
          padding: 2px 6px;
          font-size: 10px;
          font-weight: bold;
          margin: 1px 1px 1px 0;
      }

      .color-badge {
          display: inline-block;
          background: #2c2c2c;
          color: #fff;
          border-radius: 10px;
          padding: 2px 7px;
          font-size: 9px;
          margin: 1px 1px 1px 0;
      }

      .precio-box {
          display: inline-block;
          background: #1a4fa0;
          color: #fff;
          padding: 3px 10px;
          border-radius: 3px;
          font-size: 16px;
          font-weight: bold;
      }
    </style>
</head>
<body>

    <img src="{{ $header }}" class="top-image">
    <img src="{{ $footer }}" class="bottom-image">

    @php $pages = collect($data)->chunk(2); @endphp

    @foreach($pages as $page)
    <div class="page {{ !$loop->last ? 'page-break' : '' }}">

        @foreach($page as $item)
        <table width="100%" style="margin-bottom: 20px; border-bottom: 1px solid #ccc;">

            {{-- ENCABEZADO --}}
            <tr>
                <td colspan="2" style="padding-left: 50px; padding-bottom: 1px;">
                    <span style="font-size: 18px; font-weight: bold; color: #1a4fa0; text-transform: uppercase; letter-spacing: 0.7px;">
                        {{ $item['supplier']['razon_social'] }}
                    </span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-left: 50px; padding-bottom: 6px; border-left: 3px solid #1a4fa0;">
                    <span style="font-size: 10px; font-weight: bold; color: #1a1a1a;">
                        {{ $item['extraido']['producto'] }}
                    </span>
                </td>
            </tr>

            {{-- CONTENIDO PRINCIPAL --}}
            <tr>
                {{-- DETALLE --}}
                <td style="width: 60%; vertical-align: top; padding-left: 50px;">

                    <div class="precio-box" style="margin-bottom: 8px;">${{ $item['extraido']['precio'] }}</div>

                    <div class="seccion-label">Tallas</div>
                    @foreach($item['extraido']['tallas'] as $talla)
                        <span class="talla-badge">{{ $talla }}</span>
                    @endforeach

                    <br><br>

                    <div class="seccion-label">Colores</div>
                    @foreach($item['extraido']['colores'] as $color)
                        <span class="color-badge">{{ $color }}</span>
                    @endforeach

                </td>

                {{-- IMAGEN PRINCIPAL --}}
                <td style="width: 40%;">
                    @if(!empty($item['images'][0]['url']))
                        <img src="{{ $item['images'][0]['url'] }}" style="width: 68%; max-height: 220px; border-radius: 5px; object-fit: cover;">
                    @endif
                </td>
            </tr>

            {{-- GALERÍA --}}
            <tr>
                <td colspan="2">
                    <table width="100%">
                        <tr>
                            @foreach(collect($item['images'])->slice(1, 3) as $img)
                            <td style="width: 33%; padding: 3px; text-align: center;">
                                @if(!empty($img['url']))
                                    <img src="{{ $img['url'] }}" style="width: 40%; height: 120px; border-radius: 5px; object-fit: cover;">
                                @endif
                            </td>
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>

        </table>
        @endforeach

    </div>
    @endforeach

</body>
</html>