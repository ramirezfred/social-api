<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet">

    <style>
        @media print {
            @page { margin: 0; padding: 0; }
            body { margin: 0; padding: 0; }
            html { margin: 0; padding: 0; }
        }

        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; }
        html { margin: 0; padding: 0; }

        /* Estilos para el div contenedor */
        .container {
            width: 8.5in;
            height: 6in;
            position: relative;
            margin: 0 auto;
            padding: 0;
            overflow: hidden;
        }

        /* Estilos para la imagen superior */
        .top-image {
            display: block;
            margin: 0 auto;
            width: 150px;
            height: auto;
        }

        /* Estilos para la imagen inferior */
        .bottom-image {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
        }

        /* Estilos para el contenido del div */
        .content {
            padding: 15px;
            font-size: 12px;
            height: 25px;
            font-family: 'Roboto', sans-serif;
                    }

        /* Color de fondo personalizado para el contenedor */
        .container {
            background-color: #FFFFFF;
        }

        /* Color de fondo personalizado para el texto "Hola, {{$Nombre}}" */
        #div1 {
            background-color: {{$color_b}};
            padding: 20px;
            border: 20px solid {{$color_a}};

        }

    </style>

</head>
<body>

<div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
    <!-- Imagen superior -->
    <img class="top-image" src={{$logo}} alt="Imagen Superior">

    <!-- Contenido del div -->
    <div class="content">

        <div id="div1">
          <div style="text-align: center;">
              Hola, {{$Nombre}}
          </div>

          <div style="text-align: center;">
              <strong>Rfc: {{$Rfc}}</strong>
          </div>
          <br>

          <div style="text-align: center;">
              Te adjuntamos tu factura.
          </div>
       

          <br><br>
          <div style="text-align: center; font-size: 18px;">
              Estamos a tu servicio.
          </div>

          <br><br><br>
          <div style="text-align: center;">
              <a href="https://wa.me/5215564828212" target="_blank" style="color: #000000;">
                Usando la IA de InterNow
              </a>
          </div>

          <div style="text-align: center;">
            <a href="https://wa.me/5215564828212" target="_blank" style="color: #000000;">
              +52 55 6482 8212
            </a>
          </div>
        </div>
    </div>

</div>

</body>
</html>