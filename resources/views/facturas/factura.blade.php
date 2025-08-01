<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <style>

      @media print {
        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; }
        html {
          margin: 0;
          padding: 0;
        }
      }

        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; }
        html {
          margin: 0;
          padding: 0;
        }

        /* Estilos para el div contenedor */
        .container {
            width: 8.5in; /* Ancho de hoja carta en pulgadas menos los márgenes izquierdo y derecho (8.5in - 1in - 1in) */
            height: 11in; /* Altura de hoja carta en pulgadas */
            position: relative; /* Para posicionar las imágenes dentro del div */
            margin: 0 auto; /* Margen superior e inferior 0, margen izquierdo y derecho automático para centrar */
            padding: 0; /* Eliminar relleno */
            overflow: hidden; /* Evitar desbordamiento de contenido */

        }

        /* Estilos para la imagen superior */
        .top-image {
            position: absolute; /* Posición absoluta dentro del div */
            top: 0; /* Arriba del div */
            left: 0; /* A la izquierda del div */
            width: 100%; /* Ancho completo del div */
            height: auto; /* Altura automática para mantener la proporción de la imagen */
        }

        /* Estilos para la imagen inferior */
        .bottom-image {
            position: absolute; /* Posición absoluta dentro del div */
            bottom: 0; /* Abajo del div */
            left: 0; /* A la izquierda del div */
            width: 100%; /* Ancho completo del div */
            height: 90px; /* Altura automática para mantener la proporción de la imagen */
        }

        /* Estilos para el contenido del div */
        .content {
            margin-top: 154px; /* Margen superior para evitar que el texto se oculte detrás de la imagen superior */
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
        <!-- Imagen superior -->
        <img class="top-image" src={{$header}} alt="Imagen Superior">

        <!-- Contenido del div -->
        <div class="content">

          <div style="padding: 15px; font-size: 12px;height: 25px;">

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>RFC emisor:</strong> {{$emisor->Rfc}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Folio:</strong> {{$factura->Folio}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Nombre emisor:</strong> {{$emisor->RazonSocial}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>No. de serie:</strong> {{$factura->Serie}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>RFC receptor:</strong> {{$factura->receptor->Rfc}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Cod. postal, fecha y hora de emisión:</strong> {{$emisor->CP}} {{$factura->Fecha}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Nombre receptor:</strong> {{$factura->receptor->Nombre}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Efecto de comprobante:</strong> Ingreso
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Cod. postal receptor:</strong> {{$factura->receptor->DomicilioFiscalReceptor}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Régimen fiscal:</strong> {{$emisor->mi_regimen_fiscal->texto}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Régimen fiscal receptor:</strong> {{$factura->receptor->mi_regimen_fiscal->texto}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Exportación:</strong> No aplica
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Uso CFDI:</strong> {{$factura->receptor->mi_uso_cfdi->texto}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                </td>
              </tr>
            </table>

            <div> 
              <strong>Conceptos:</strong>    
            </div>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr>
                    <th scope="col">Clave Prod y/o Serv</th>
                    <th scope="col">Cantidad</th>
                    <th scope="col">Clave de unidad</th>
                    <th scope="col">Unidad</th>
                    <th scope="col">Valor unitario</th>
                    <th scope="col">Importe</th>
                    <th scope="col">Descuento</th>
                    <th scope="col">Objeto impuesto</th>
                </tr>
              </thead>
              <tbody style="font-size: 12px;height: 25px;">
                @foreach ($factura->conceptos as $item)
                    <tr>
                      <td style="text-align: center;">
                        {{$item->mi_clave_prod_serv->id}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->Cantidad}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->mi_clave_unidad->id}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->Unidad}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->ValorUnitario}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->Importe}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->Descuento}}
                      </td>
                      <td style="text-align: center;">
                        {{$item->ObjetoImp}}
                      </td>
                    </tr>
                    <tr>
                      <td colspan="1" style="text-align: center; font-weight: bold;">
                        Descripción
                      </td>
                      <td colspan="3" style="text-align: center;">
                        {{$item->Descripcion}}
                      </td>
                      <td colspan="4" style="text-align: center; font-size: 10px;height: 24px;">
                        @if ($item->ObjetoImp == "Si obj de impuesto.")
                        <table width="100%" style="border-collapse: collapse; border: none;">
                          <tr style="margin: 0px; padding: 0px;">
                            <td style="margin: 0px; padding: 0px;">
                              <strong>Impuesto</strong>
                            </td>
                            <td style="margin: 0px; padding: 0px;">
                              <strong>Tipo</strong>
                            </td>
                            <td style="margin: 0px; padding: 0px;">
                              <strong>Base</strong>
                            </td>
                            <td style="margin: 0px; padding: 0px;">
                              <strong>Tipo Factor</strong>
                            </td>
                            <td style="margin: 0px; padding: 0px;">
                              <strong>Tasa o Cuota</strong>
                            </td>
                            <td style="margin: 0px; padding: 0px;">
                              <strong>Importe</strong>
                            </td>
                          </tr>
                          @foreach ($item->Impuestos as $item_imp)
                            <tr style="margin: 0px; padding: 0px;">
                              <td style="margin: 0px; padding: 0px;">
                                {{$item_imp->Impuesto}}
                              </td>
                              <td style="margin: 0px; padding: 0px;">
                                {{$item_imp->Tipo}}
                              </td>
                              <td style="margin: 0px; padding: 0px;">
                                {{$item_imp->Base}}
                              </td>
                              <td style="margin: 0px; padding: 0px;">
                                {{$item_imp->TipoFactor}}
                              </td>
                              <td style="margin: 0px; padding: 0px;">
                                {{$item_imp->TasaOCuota}}
                              </td>
                              <td style="margin: 0px; padding: 0px;">
                                {{$item_imp->Importe}}
                              </td>
                            </tr>
                          @endforeach
                        </table>
                        @endif
                      </td>
                    </tr>
                @endforeach
              </tbody>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Moneda:</strong> Peso Mexicano
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Subtotal</strong> ${{$factura->Subtotal}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Forma de pago:</strong> {{$factura->mi_forma_pago->texto}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Descuento</strong> ${{$factura->Descuento}}
                </td>
              </tr>
            </table>
      
            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Método de pago:</strong> {{$factura->mi_metodo_pago->texto}}
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Impuestos trasladados</strong> IVA 16.00% ${{$factura->TotalImpuestosTrasladados}}
                </td>
              </tr>
            </table>
            
            @if ($factura->conceptos[0]->ObjetoImpRet == 1)
            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Impuestos retenidos</strong> IVA ${{$factura->TotalImpuestosRetenidosIva}}
                </td>
              </tr>
            </table>
            @endif

            @if ($factura->conceptos[0]->ObjetoImpRet == 1)
            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Impuestos retenidos</strong> ISR ${{$factura->TotalImpuestosRetenidosIsr}}
                </td>
              </tr>
            </table>
            @endif

            <table width="100%">
              <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  
                </td>
                <td style="width: 50%; margin: 0px; padding: 0px;">
                  <strong>Total</strong> ${{$factura->Total}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 20%; margin: 0px; padding: 0px;">
                  <img src="data:image/png;base64,{{ $factura->archivo->png }}" alt="Imagen Base64">
                </td>
                <td style="width: 80%; margin: 0px; padding: 0px;">

                      <div style="margin: 0px; padding: 0px;">
                        <p class="parrafo-multilinea">
                          <strong>Sello digital del CFDI</strong> {{$factura->Sello}}
                        </p>
                      </div>

                </td>
              </tr>
            </table>


          </div>

            
        </div>

        <!-- Imagen inferior -->
        <img class="bottom-image" src={{$footer}} alt="Imagen Inferior">
    </div>
    
</body>
</html>
