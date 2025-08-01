<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;

use DOMDocument;
use XSLTProcessor;

class ApiCfdiController extends Controller
{
    public function getToken()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'http://dev33.facturacfdi.mx/WSForcogsaService?wsdl=null',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wser="http://wservicios/">
            <soapenv:Header/>
                <soapenv:Body>
                    <wser:Autenticar>
                        <!--Optional:-->
                        <usuario>pruebasWS</usuario>
                        <!--Optional:-->
                        <contrasena>pruebasWS</contrasena>
                    </wser:Autenticar>
                </soapenv:Body>
        </soapenv:Envelope>',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: text/xml'
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        // //echo $response;
        // //return $response;
        // dd($response);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return response()->json([
                'error'=>'Error al conectar con Cfdi.',
                'cfdi'=>$err
            ], 500);

        } else {

            // Parsear la respuesta XML
            $xml = simplexml_load_string($response);

            if ($xml->xpath('//token')) {
                $token = (string) $xml->xpath('//token')[0];
                return response()->json([
                    'token'=>$token,
                    //'response'=>$response,
                ], 200);
            } elseif ($xml->xpath('//mensaje')) {
                $mensaje = (string) $xml->xpath('//mensaje')[0];
                return response()->json([
                    'error'=>$mensaje,
                    //'response'=>$response,
                ], 500);
            } else {
                return response()->json([
                    'error'=>'Respuesta inesperada del servidor',
                    'response'=>$response,
                ], 500);
            }
        }

        
    }

    public function timbrar()
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'http://dev33.facturacfdi.mx/WSTimbradoCFDIService?wsdl=null',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wser="http://wservicios/">
   <soapenv:Header/>
   <soapenv:Body>
      <wser:TimbrarCFDI>
         <!--Optional:-->
         <accesos>
            <!--Optional:-->
            <password>pruebasWS</password>
            <!--Optional:-->
            <usuario>pruebasWS</usuario>
         </accesos>
         <!--Optional:-->
         <comprobante><![CDATA[
                <cfdi:Comprobante xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd" Version="4.0" Fecha="2023-09-27T16:46:23" Sello="deplu7oWA6/qbs5cU91u7N4lBP8tMq31Bn/+VKq7C5AQFptLdvwtIOGJO8Tp4fsxUrOK07YYzIN6DqYQtWnYSR3Ep5I571x1fDx5lo5MbBX7wPJfjh2VMpfBjJHF0j38S6tT5DqBSbueTFBb+9fSrFjPdEz2krChK6SUsZZzn06oUGx9389fvf7Oi2sSHj6Ahxvfj0/5oP+JjAxzaXbMpCzFFj0WFy4W20dDduKgX4c3lomnCXbxWyKxX6Wyx2HyI5BXdAXFO2rJmbElm0UiloNUCmgjqIdeWc9Muy3IQy14b87+FwpjkjhU2Xv11QP0XqUH2xj0psjU2cMfsCUVKQ==" FormaPago="03" NoCertificado="00001000000519197045" Certificado="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" SubTotal="500.00" Moneda="MXN" Total="580.00" TipoDeComprobante="I" Exportacion="01" MetodoPago="PUE" LugarExpedicion="43612" xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <cfdi:Emisor Rfc="ICO2209056Y1" Nombre="INTERNOW CORP" RegimenFiscal="601" />
  <cfdi:Receptor Rfc="VIDE9602275P5" Nombre="EDUARDO VICENTE DIONICIO" DomicilioFiscalReceptor="43660" RegimenFiscalReceptor="612" UsoCFDI="G03" />
  <cfdi:Conceptos>
    <cfdi:Concepto ClaveProdServ="53102903" Cantidad="1.00" ClaveUnidad="H87" Unidad="Pieza" Descripcion="GOOPY SERV ENVIO #65955" ValorUnitario="250" Importe="250.00" ObjetoImp="02">
      <cfdi:Impuestos>
        <cfdi:Traslados>
          <cfdi:Traslado Base="250.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="40.00" />
        </cfdi:Traslados>
      </cfdi:Impuestos>
    </cfdi:Concepto>
    <cfdi:Concepto ClaveProdServ="81141601" Cantidad="1.00" ClaveUnidad="E48" Unidad="Unidad de servicio" Descripcion="SERVICIO DE ENVIO" ValorUnitario="250" Importe="250.00" ObjetoImp="02">
      <cfdi:Impuestos>
        <cfdi:Traslados>
          <cfdi:Traslado Base="250.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="40.00" />
        </cfdi:Traslados>
      </cfdi:Impuestos>
    </cfdi:Concepto>
  </cfdi:Conceptos>
  <cfdi:Impuestos TotalImpuestosTrasladados="80.00">
    <cfdi:Traslados>
      <cfdi:Traslado Base="500.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="80.00" />
    </cfdi:Traslados>
  </cfdi:Impuestos>
</cfdi:Comprobante>
         ]]></comprobante>
      </wser:TimbrarCFDI>
   </soapenv:Body>
</soapenv:Envelope>',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: text/xml'
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        //echo $response;
        //return $response;
        //dd($response);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return response()->json([
                'error'=>'Error al conectar con Cfdi.',
                'cfdi'=>$err
            ], 500);

        } else {

            // Parsear la respuesta XML
            $xml = simplexml_load_string($response);

            if ($xml->xpath('//xmlTimbrado')) {
                $xmlTimbrado = (string) $xml->xpath('//xmlTimbrado')[0];
                return response()->json([
                    'xmlTimbrado'=>$xmlTimbrado,
                    //'response'=>$response,
                ], 200);
            } else if ($xml->xpath('//mensaje')) {
                $mensaje = (string) $xml->xpath('//mensaje')[0];
                return response()->json([
                    'error'=>$mensaje,
                    //'response'=>$response,
                ], 500);
            } else if ($xml->xpath('//codigoError') && $xml->xpath('//error')) {
                $codigoError = (string) $xml->xpath('//codigoError')[0];
                $error = (string) $xml->xpath('//error')[0];
                return response()->json([
                    'codigoError'=>$codigoError,
                    'error'=>$error,
                    //'response'=>$response,
                ], 500);
            }else {
                return response()->json([
                    'error'=>'Respuesta inesperada del servidor',
                    'response'=>$response,
                ], 500);
            }
        }

    }

    public function convertirCertificado()
    {
        // Ruta al archivo .cer
        $rutaCertificadoCer = public_path('CSD_Sucursal_1_CACX7605101P8_20230509_130254.cer');
        //$rutaCertificadoCer = public_path('auma9101171b4.cer');
        //$rutaCertificadoCer = public_path('00001000000702505449.cer');

        if (!file_exists($rutaCertificadoCer)) {
            // El archivo existe, puedes continuar con tu código aquí.
            echo "El archivo $rutaCertificadoCer no existe.";
            return;
        }

        // Ruta para guardar el archivo .pem
        $rutaCertificadoPem = public_path('Cer_CSD_Sucursal_1_CACX7605101P8_20230509_130254.pem');
        //$rutaCertificadoPem = public_path('Cer_auma9101171b4.pem');
        //$rutaCertificadoPem = public_path('Cer_00001000000702505449.pem');

        // Convierte el certificado .cer a .pem
        $output = [];
        $returnVar = 0;
        exec("openssl x509 -inform DER -outform PEM -in $rutaCertificadoCer -out $rutaCertificadoPem", $output, $returnVar);

        if ($returnVar !== 0) {
            return "Error en la conversión del certificado: " . implode(PHP_EOL, $output);
        }

        // Ruta al archivo .key
        $rutaClaveKey = public_path('CSD_Sucursal_1_CACX7605101P8_20230509_130254.key');
        //$rutaClaveKey = public_path('Claveprivada_FIEL_AUMA9101171B4_20230417_165115.key');
        //$rutaClaveKey = public_path('CSD_TULANCINGO_AUMA9101171B4_20230926_111026.key');

        if (!file_exists($rutaClaveKey)) {
            // El archivo existe, puedes continuar con tu código aquí.
            echo "El archivo $rutaClaveKey no existe.";
            return;
        }

        // Ruta para guardar el archivo .pem
        $rutaClavePem = public_path('Key_CSD_Sucursal_1_CACX7605101P8_20230509_130254.pem');
        //$rutaClavePem = public_path('Key_Claveprivada_FIEL_AUMA9101171B4_20230417_165115.pem');
        //$rutaClavePem = public_path('Key_CSD_TULANCINGO_AUMA9101171B4_20230926_111026.pem');

        // Convierte la clave .key a .pem
        $output2 = [];
        $returnVar2 = 0;
        //exec("openssl rsa -inform PEM -outform PEM -in $rutaClaveKey -out $rutaClavePem", $output2, $returnVar2);
        exec("openssl pkcs8 -inform DER -in $rutaClaveKey -passin pass:12345678a -out $rutaClavePem", $output2, $returnVar2);
        //exec("openssl pkcs8 -inform DER -in $rutaClaveKey -passin pass:12345678a -out $rutaClavePem", $output2, $returnVar2);
        //exec("openssl pkcs8 -inform DET -in $rutaClaveKey -passin pass:Roma2023 -out $rutaClavePem", $output2, $returnVar2);
        //exec("openssl pkcs12 -in $rutaClaveKey -out $rutaClavePem", $output2, $returnVar2);

        if ($returnVar2 !== 0) {
            return "Error en la conversión de la clave: " . implode(PHP_EOL, $output2);
        }

        return response()->json([
            'message'=>"Archivos .cer y .key convertidos a .pem",
        ], 200);
    }

    public function firmarCFDI(Request $request)
    {
        try {
            // Ruta al archivo XML del CFDI 4.0
            $rutaXml = public_path('cfdi4_generico.xml');

            // Ruta al certificado (.cer) y la clave privada (.key)
            $rutaCertificado = public_path('CSD_Sucursal_1_CACX7605101P8_20230509_130254.cer');
            $rutaClavePrivada = public_path('CSD_Sucursal_1_CACX7605101P8_20230509_130254.key');

            // Validar la existencia de archivos
            if (!file_exists($rutaXml) || !file_exists($rutaCertificado) || !file_exists($rutaClavePrivada)) {
                throw new \Exception('Alguno de los archivos no existe.');
            }

            // Cargar el contenido del XML
            $xml = file_get_contents($rutaXml);

            // Cargar el certificado
            $certificado = file_get_contents($rutaCertificado);

            // Cargar la clave privada y configurar la contraseña si es necesaria
            $clavePrivada = file_get_contents($rutaClavePrivada);
            $clavePrivadaPassphrase = '12345678a'; // Configura la contraseña adecuada

            // Crear una instancia de OpenSSL
            $openssl = openssl_pkey_get_private($clavePrivada, $clavePrivadaPassphrase);

            if (!$openssl) {
                throw new \Exception('No se pudo cargar la clave privada de OpenSSL.');
            }

            // Firmar el XML
            $success = openssl_sign($xml, $signature, $openssl, OPENSSL_ALGO_SHA256);

            if (!$success) {
                throw new \Exception('Error al firmar el XML con OpenSSL.');
            }

            // Codificar la firma en base64
            $signatureBase64 = base64_encode($signature);

            // Agregar el sello digital al XML
            $xmlSellado = str_replace('</cfdi:Comprobante>', "<cfdi:Sello>$signatureBase64</cfdi:Sello></cfdi:Comprobante>", $xml);

            // Puedes guardar el XML sellado en un archivo o imprimirlo, según tus necesidades
            // file_put_contents('cfdi_sellado.xml', $xmlSellado);
            echo $xmlSellado;
        } catch (\Exception $e) {
            // Manejar la excepción adecuadamente, por ejemplo, devolver un mensaje de error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generarSelloDigital()
    {
        $xmlFilePath = public_path('cfdi4_generico.xml');
        $privateKeyFilePath = public_path('Key_CSD_Sucursal_1_CACX7605101P8_20230509_130254.pem');

        // Cargar el contenido del XML
        $xml = file_get_contents($xmlFilePath);

        // Generar la cadena original y calcular su hash SHA-256
        $cadenaOriginal = $xml; // Puedes personalizar la generación de la cadena original según tus necesidades
        $cadenaOriginalSha256 = hash('sha256', $cadenaOriginal);

        // Cargar la clave privada desde un archivo PEM
        $privateKey = file_get_contents($privateKeyFilePath);

        // Intentar cargar la clave privada
        $privateKeyResource = openssl_pkey_get_private($privateKey);

        if (!$privateKeyResource) {
            return ['error' => 'No se pudo cargar la clave privada de OpenSSL.'];
        }

        // Firmar el hash SHA-256 con la clave privada
        $success = openssl_sign($cadenaOriginalSha256, $firma, $privateKeyResource, OPENSSL_ALGO_SHA256);

        if (!$success) {
            return ['error' => 'Error al firmar el hash SHA-256.'];
        }

        // Codificar la firma en base64 para obtener el sello digital
        $selloDigital = base64_encode($firma);

        // Liberar los recursos
        openssl_free_key($privateKeyResource);

        // Retornar el sello digital
        return $selloDigital;
    }

    /**
   * Encodes data from UTF-8 to ISO-8859-1 throw HTML entities
   *
   * @param string $text
   * @return string
   */
  private function encText( $text )
  {
    return html_entity_decode( htmlentities( $text, ENT_QUOTES, 'UTF-8' ),
                               ENT_QUOTES, 'ISO-8859-1' );
  }

    /**
   * Trasforms a CFD array into a CFD XML
   *
   * @param array $data
   * @return string CFD XML
   */
    public function getXML(array $data)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Comprobante
        $comprobante = $dom->createElement('cfdi:Comprobante');
        $dom->appendChild($comprobante);

        $comprobante->setAttribute('xmlns:cfdi', 'http://www.sat.gob.mx/cfd/4');
        $comprobante->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $comprobante->setAttribute('xsi:schemaLocation', 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd');
        $comprobante->setAttribute('Version', $data['Version']);
        $comprobante->setAttribute('Fecha', $data['Fecha']);
        $comprobante->setAttribute('Sello', $data['Sello']);
        $comprobante->setAttribute('FormaPago', $data['FormaPago']);
        $comprobante->setAttribute('NoCertificado', $data['NoCertificado']);
        $comprobante->setAttribute('Certificado', $data['Certificado']);
        $comprobante->setAttribute('SubTotal', $data['SubTotal']);
        $comprobante->setAttribute('Moneda', $data['Moneda']);
        $comprobante->setAttribute('Total', $data['Total']);
        $comprobante->setAttribute('TipoDeComprobante', $data['TipoDeComprobante']);
        $comprobante->setAttribute('Exportacion', $data['Exportacion']);
        $comprobante->setAttribute('MetodoPago', $data['MetodoPago']);
        $comprobante->setAttribute('LugarExpedicion', $data['LugarExpedicion']);

        // Emisor
        $emisor = $dom->createElement('cfdi:Emisor');
        $comprobante->appendChild($emisor);

        $emisor->setAttribute('Rfc', $data['Emisor']['Rfc']);
        $emisor->setAttribute('Nombre', $data['Emisor']['Nombre']);
        $emisor->setAttribute('RegimenFiscal', $data['Emisor']['RegimenFiscal']);

        // Receptor
        $receptor = $dom->createElement('cfdi:Receptor');
        $comprobante->appendChild($receptor);

        $receptor->setAttribute('Rfc', $data['Receptor']['Rfc']);
        $receptor->setAttribute('Nombre', $data['Receptor']['Nombre']);
        $receptor->setAttribute('DomicilioFiscalReceptor', $data['Receptor']['DomicilioFiscalReceptor']);
        $receptor->setAttribute('RegimenFiscalReceptor', $data['Receptor']['RegimenFiscalReceptor']);
        $receptor->setAttribute('UsoCFDI', $data['Receptor']['UsoCFDI']);

        // Conceptos
        $conceptos = $dom->createElement('cfdi:Conceptos');
        $comprobante->appendChild($conceptos);

        foreach ($data['Conceptos'] as $conceptoData) {
            $concepto = $dom->createElement('cfdi:Concepto');
            $conceptos->appendChild($concepto);

            $concepto->setAttribute('ClaveProdServ', $conceptoData['ClaveProdServ']);
            $concepto->setAttribute('Cantidad', $conceptoData['Cantidad']);
            $concepto->setAttribute('ClaveUnidad', $conceptoData['ClaveUnidad']);
            $concepto->setAttribute('Unidad', $conceptoData['Unidad']);
            $concepto->setAttribute('Descripcion', $conceptoData['Descripcion']);
            $concepto->setAttribute('ValorUnitario', $conceptoData['ValorUnitario']);
            $concepto->setAttribute('Importe', $conceptoData['Importe']);
            $concepto->setAttribute('ObjetoImp', $conceptoData['ObjetoImp']);

            // Impuestos
            $impuestos = $dom->createElement('cfdi:Impuestos');
            $concepto->appendChild($impuestos);

            $traslados = $dom->createElement('cfdi:Traslados');
            $impuestos->appendChild($traslados);

            foreach ($conceptoData['Impuestos']['Traslados'] as $trasladoData) {
                $traslado = $dom->createElement('cfdi:Traslado');
                $traslados->appendChild($traslado);

                $traslado->setAttribute('Base', $trasladoData['Base']);
                $traslado->setAttribute('Impuesto', $trasladoData['Impuesto']);
                $traslado->setAttribute('TipoFactor', $trasladoData['TipoFactor']);
                $traslado->setAttribute('TasaOCuota', $trasladoData['TasaOCuota']);
                $traslado->setAttribute('Importe', $trasladoData['Importe']);
            }
        }

        // Impuestos
        $impuestos = $dom->createElement('cfdi:Impuestos');
        $comprobante->appendChild($impuestos);

        $impuestos->setAttribute('TotalImpuestosTrasladados', $data['Impuestos']['TotalImpuestosTrasladados']);

        $traslados = $dom->createElement('cfdi:Traslados');
        $impuestos->appendChild($traslados);

        foreach ($conceptoData['Impuestos']['Traslados'] as $trasladoData) {
            $traslado = $dom->createElement('cfdi:Traslado');
            $traslados->appendChild($traslado);

            $traslado->setAttribute('Base', $trasladoData['Base']);
            $traslado->setAttribute('Impuesto', $trasladoData['Impuesto']);
            $traslado->setAttribute('TipoFactor', $trasladoData['TipoFactor']);
            $traslado->setAttribute('TasaOCuota', $trasladoData['TasaOCuota']);
            $traslado->setAttribute('Importe', $trasladoData['Importe']);
        }

        // Guarda el XML en un archivo o devuelve como string
        return $dom->saveXML();
    }



    /**
   * Validates and transforma an array of data to a | (pipe) separated string
   *
   * @param array contains the FEA data
   * @return string separated by | (pipe)
   */
    public function getOriginalString(array &$data)
    {
        if (!$data) {
            return false;
        }

        $string = '';

        // Comprobante
        $string .= '||4.0|';
        $string .= isset($data['Fecha']) ? $data['Fecha'] : '';
        $string .= '|';
        $string .= isset($data['NoCertificado']) ? $data['NoCertificado'] : '';
        $string .= '|';
        $string .= isset($data['Certificado']) ? $data['Certificado'] : '';
        $string .= '|';
        $string .= isset($data['SubTotal']) ? $data['SubTotal'] : '';
        $string .= '|';
        $string .= isset($data['Moneda']) ? $data['Moneda'] : '';
        $string .= '|';
        $string .= isset($data['Total']) ? $data['Total'] : '';
        $string .= '|';
        $string .= isset($data['TipoDeComprobante']) ? $data['TipoDeComprobante'] : '';
        $string .= '|';
        $string .= isset($data['LugarExpedicion']) ? $data['LugarExpedicion'] : '';

        // Emisor
        if (!isset($data['Emisor'])) {
            die('You must provide the Emisor in your array' . "\n");
        }
        $string .= '|';
        $string .= isset($data['Emisor']['Rfc']) ? $data['Emisor']['Rfc'] : '';
        $string .= '|';
        $string .= isset($data['Emisor']['Nombre']) ? $data['Emisor']['Nombre'] : '';
        $string .= '|';
        $string .= isset($data['Emisor']['RegimenFiscal']) ? $data['Emisor']['RegimenFiscal'] : '';

        // Receptor
        if (!isset($data['Receptor'])) {
            die('You must provide the Receptor in your array' . "\n");
        }
        $string .= '|';
        $string .= isset($data['Receptor']['Rfc']) ? $data['Receptor']['Rfc'] : '';
        $string .= '|';
        $string .= isset($data['Receptor']['Nombre']) ? $data['Receptor']['Nombre'] : '';
        $string .= '|';
        $string .= isset($data['Receptor']['DomicilioFiscalReceptor']) ? $data['Receptor']['DomicilioFiscalReceptor'] : '';
        $string .= '|';
        $string .= isset($data['Receptor']['RegimenFiscalReceptor']) ? $data['Receptor']['RegimenFiscalReceptor'] : '';
        $string .= '|';
        $string .= isset($data['Receptor']['UsoCFDI']) ? $data['Receptor']['UsoCFDI'] : '';

        // Conceptos
        if (!isset($data['Conceptos'])) {
            die('You must provide at least one Concepto in your array' . "\n");
        }
        foreach ($data['Conceptos'] as $concepto) {
            $string .= '|';
            $string .= isset($concepto['ClaveProdServ']) ? $concepto['ClaveProdServ'] : '';
            $string .= '|';
            $string .= isset($concepto['Cantidad']) ? $concepto['Cantidad'] : '';
            $string .= '|';
            $string .= isset($concepto['ClaveUnidad']) ? $concepto['ClaveUnidad'] : '';
            $string .= '|';
            $string .= isset($concepto['Unidad']) ? $concepto['Unidad'] : '';
            $string .= '|';
            $string .= isset($concepto['Descripcion']) ? $concepto['Descripcion'] : '';
            $string .= '|';
            $string .= isset($concepto['ValorUnitario']) ? $concepto['ValorUnitario'] : '';
            $string .= '|';
            $string .= isset($concepto['Importe']) ? $concepto['Importe'] : '';

            // Impuestos
            if (isset($concepto['Impuestos']) && isset($concepto['Impuestos']['Traslados'])) {
                $string .= '|';
                $string .= isset($concepto['Impuestos']['TotalImpuestosTrasladados']) ? $concepto['Impuestos']['TotalImpuestosTrasladados'] : '';

                // Traslados
                foreach ($concepto['Impuestos']['Traslados'] as $traslado) {
                    $string .= '|';
                    $string .= isset($traslado['Base']) ? $traslado['Base'] : '';
                    $string .= '|';
                    $string .= isset($traslado['Impuesto']) ? $traslado['Impuesto'] : '';
                    $string .= '|';
                    $string .= isset($traslado['TipoFactor']) ? $traslado['TipoFactor'] : '';
                    $string .= '|';
                    $string .= isset($traslado['TasaOCuota']) ? $traslado['TasaOCuota'] : '';
                    $string .= '|';
                    $string .= isset($traslado['Importe']) ? $traslado['Importe'] : '';
                }
            }
        }

        return $string;
    }


    /**
   * Returns the private key from DER to PEM format, uses openssl from shell
   *
   * @param string $key_path the path of the private key in DER format
   * @param string $password the private key password
   * @return string the private key in a PEM format
   */
  public function getPrivateKey ( $key_path, $password )
  {
    $cmd = 'openssl pkcs8 -inform DER -in '.$key_path.' -passin pass:'.$password;
    if ( $result = shell_exec( $cmd ) ) {
      unset( $cmd );

      return $result;
    }

    return false;
  }

    /**
   * Return the certificate from DER to PEM on two formats, uses openssl from shell
   * if to_string is true resutns the certificate in a string as is (multiline)
   * but if set to false returns only the certificate in a one line string.
   *
   * @param string $cer_path the path of the certificate in DER format
   * @param boolean $to_string a flag to set the format required
   * @return string the certificate in PEM format
   */
  public function getCertificate ( $cer_path, $to_string = true )
  {
    $cmd = 'openssl x509 -inform DER -outform PEM -in '.$cer_path.' -pubkey';
    if ( $result = shell_exec( $cmd ) ) {
      unset( $cmd );

      if ( $to_string ) {

        return $result;
      }

      $split = preg_split( '/\n(-*(BEGIN|END)\sCERTIFICATE-*\n)/', $result );
      unset( $result );

      return preg_replace( '/\n/', '', $split[1] );
    }

    return false;
  }

  /**
   * Signs data with the key and returns it in a base64 string
   *
   * @param string $key string containing the key in PEM format
   * @param string $data data to sign
   * @return string the signed data in base64
   */
  public function signData ( $key, $data )
  {
    $pkeyid = openssl_get_privatekey( $key );

    // On 2011 Signing algorythm changes from MD5 to SHA1 (Thanks to eDwaRd for the reminder)
    //if ( openssl_sign( $data, $cryptedata, $pkeyid,OPENSSL_ALGO_SHA1 ) ) {
    if ( openssl_sign( $data, $cryptedata, $pkeyid,OPENSSL_ALGO_SHA256 ) ) {

      openssl_free_key( $pkeyid );

      return base64_encode( $cryptedata );
    }
  }

  /**
   * Returns the serial number from a DER certificate, uses openssl from shell
   *
   * @param string $cer_path the certificate path in DER format
   * @return string the serial number of the certificate in ASCII
   */
  public function getSerialFromCertificate ( $cer_path )
  {
    $cmd = 'openssl x509 -inform DER -outform PEM -in '.$cer_path.' -pubkey | '.
           'openssl x509 -serial -noout';
    if ( $serial = shell_exec( $cmd ) ) {
      unset( $cmd );

      if ( preg_match( "/([0-9]{40})/", $serial, $match ) ) {
        unset( $serial );

        return implode( '', array_map( 'chr', array_map( 'hexdec', str_split( $match[1], 2 ) ) ) );
      }
    }

    return false;
  }

  public function test (  )
  {
    // set User vars
    // $password = '12345678a';
    // $cer_path = public_path('CSD_Sucursal_1_CACX7605101P8_20230509_130254.cer');
    // $key_path = public_path('CSD_Sucursal_1_CACX7605101P8_20230509_130254.key');

    $password = 'Roma2023';
    $cer_path = public_path('auma9101171b4.cer');
    $key_path = public_path('Claveprivada_FIEL_AUMA9101171B4_20230417_165115.key');

    $array['Version'] = '4.0';
    $array['Fecha'] = '2023-09-27T16:46:23'; // ISO 8601 aaaa-mm-ddThh:mm:ss
    $array['FormaPago'] = '03'; // Pago en una sola exhibición | Parcialidad 1 de X.
    $array['NoCertificado'] = '00001000000700836704';
    $array['NoAprobacion'] = "00000000000000";
    $array['AnoAprobacion'] = "2010";
    $array['Folio'] = '00000000000000000000';
    $array['FormaPago'] = '03'; // Pago en una sola exhibición | Parcialidad 1 de X.
    $array['TipoDeComprobante'] = 'I'; // Tipo de comprobante ingreso (Factura)
    $array['Moneda'] = "MXN";
    $array['Exportacion'] = "01";
    $array['MetodoPago'] = "PUE";
    $array['LugarExpedicion'] = "43612";

    $array['Emisor']['Rfc'] = 'ICO2209056Y1';
    $array['Emisor']['Nombre'] = 'INTERNOW CORP';
    $array['Emisor']['RegimenFiscal'] = '601';

    $array['Receptor']['Rfc'] = 'VIDE9602275P5';
    $array['Receptor']['Nombre'] = 'EDUARDO VICENTE DIONICIO';
    $array['Receptor']['DomicilioFiscalReceptor'] = '43660';
    $array['Receptor']['RegimenFiscalReceptor'] = '612';
    $array['Receptor']['UsoCFDI'] = 'G03'; // Uso de CFDI, en este caso, es Gastos en general

    $array['Conceptos'][0]['ClaveProdServ'] = '53102903';
    $array['Conceptos'][0]['Cantidad'] = 1.00;
    $array['Conceptos'][0]['ClaveUnidad'] = 'H87';
    $array['Conceptos'][0]['Unidad'] = 'Pieza';
    $array['Conceptos'][0]['Descripcion'] = 'GOOPY SERV ENVIO #65955';
    $array['Conceptos'][0]['ValorUnitario'] = 250.00;
    $array['Conceptos'][0]['Importe'] = 250.00;
    $array['Conceptos'][0]['ObjetoImp'] = '02'; // Objeto de impuestos

    $array['Conceptos'][0]['Impuestos']['Traslados'][0]['Base'] = 250.00;
    $array['Conceptos'][0]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // Código de impuesto (IVA)
    $array['Conceptos'][0]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
    $array['Conceptos'][0]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000'; // Tasa de IVA (16%)
    $array['Conceptos'][0]['Impuestos']['Traslados'][0]['Importe'] = 40.00;

    $array['Conceptos'][1]['ClaveProdServ'] = '81141601';
    $array['Conceptos'][1]['Cantidad'] = 1.00;
    $array['Conceptos'][1]['ClaveUnidad'] = 'E48';
    $array['Conceptos'][1]['Unidad'] = 'Unidad de servicio';
    $array['Conceptos'][1]['Descripcion'] = 'SERVICIO DE ENVIO';
    $array['Conceptos'][1]['ValorUnitario'] = 250.00;
    $array['Conceptos'][1]['Importe'] = 250.00;
    $array['Conceptos'][1]['ObjetoImp'] = '02'; // Objeto de impuestos

    $array['Conceptos'][1]['Impuestos']['Traslados'][0]['Base'] = 250.00;
    $array['Conceptos'][1]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // Código de impuesto (IVA)
    $array['Conceptos'][1]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
    $array['Conceptos'][1]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000'; // Tasa de IVA (16%)
    $array['Conceptos'][1]['Impuestos']['Traslados'][0]['Importe'] = 40.00;

    $array['Impuestos']['TotalImpuestosTrasladados'] = 80.00;

    $array['Impuestos']['Traslados'][0]['Base'] = 500.00;
    $array['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // Código de impuesto (IVA)
    $array['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
    $array['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000'; // Tasa de IVA (16%)
    $array['Impuestos']['Traslados'][0]['Importe'] = 80.00;

    $array['SubTotal'] = 500.00;

    //$array['Descuento'] = '';
    $array['Total'] = 580.00;

    $array['Sello'] = $this->signData( $this->getPrivateKey( $key_path, $password ),
                                       $this->getOriginalString( $array ) );
    $array['NoCertificado'] = $this->getSerialFromCertificate( $cer_path );
    $array['Certificado'] = $this->getCertificate( $cer_path, false );
    $array['CadenaOriginal'] = $this->getOriginalString( $array );

    dd($array);

    // return the CDF as XML
    //dd($this->getXML( $array ));

  }

    public function generarCadenaOriginal()
    {
        $xmlFilePath = public_path('cfdi4_generico.xml');

        // Verificar que el archivo XML existe
        if (!file_exists($xmlFilePath)) {
            return 'Verificar que el archivo XML existe';
        }

        // Cargar el contenido del archivo XML
        $xmlContent = file_get_contents($xmlFilePath);

        // Crear un objeto DOMDocument para procesar el XML
        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);

        // Obtener el nodo Comprobante
        $comprobanteNode = $dom->getElementsByTagName('cfdi:Comprobante')->item(0);

        // Verificar si se encontró el nodo Comprobante
        if (!$comprobanteNode) {
            return 'Verificar si se encontró el nodo Comprobante';
        }

        // Obtener el nodo TimbreFiscalDigital
        $timbreFiscalNode = $dom->getElementsByTagName('tfd:TimbreFiscalDigital')->item(0);

        // Verificar si se encontró el nodo TimbreFiscalDigital
        if (!$timbreFiscalNode) {
            return 'Verificar si se encontró el nodo TimbreFiscalDigital';
        }

        // Construir la cadena original
        $cadenaOriginal = $comprobanteNode->getAttribute('Version') . '|' .
            $comprobanteNode->getAttribute('Fecha') . '|' .
            $comprobanteNode->getAttribute('Sello') . '|' .
            $comprobanteNode->getAttribute('FormaPago') . '|' .
            $comprobanteNode->getAttribute('NoCertificado') . '|' .
            $comprobanteNode->getAttribute('Certificado') . '|' .
            $comprobanteNode->getAttribute('SubTotal') . '|' .
            $comprobanteNode->getAttribute('Moneda') . '|' .
            $comprobanteNode->getAttribute('Total') . '|' .
            $comprobanteNode->getAttribute('TipoDeComprobante') . '|' .
            $comprobanteNode->getAttribute('MetodoPago') . '|' .
            $comprobanteNode->getAttribute('LugarExpedicion') . '|' .
            $timbreFiscalNode->getAttribute('UUID');

        // Obtener los nodos de los conceptos
        $conceptosNodeList = $dom->getElementsByTagName('cfdi:Concepto');

        // Iterar a través de los nodos de conceptos y agregar sus atributos a la cadena original
        foreach ($conceptosNodeList as $conceptoNode) {
            $cantidad = $conceptoNode->getAttribute('Cantidad');
            $claveProdServ = $conceptoNode->getAttribute('ClaveProdServ');
            $claveUnidad = $conceptoNode->getAttribute('ClaveUnidad');
            $descripcion = $conceptoNode->getAttribute('Descripcion');
            $valorUnitario = $conceptoNode->getAttribute('ValorUnitario');
            $importe = $conceptoNode->getAttribute('Importe');

            $cadenaOriginal .= "|$cantidad|$claveProdServ|$claveUnidad|$descripcion|$valorUnitario|$importe";
        }

        // Obtener el nodo Impuestos
        $impuestosNode = $dom->getElementsByTagName('cfdi:Impuestos')->item(0);

        // Verificar si se encontró el nodo Impuestos
        if ($impuestosNode) {
            $totalImpuestosTrasladados = $impuestosNode->getAttribute('TotalImpuestosTrasladados');

            $cadenaOriginal .= "|$totalImpuestosTrasladados";

            // Obtener los nodos de los traslados
            $trasladosNodeList = $dom->getElementsByTagName('cfdi:Traslado');

            // Iterar a través de los nodos de traslados y agregar sus atributos a la cadena original
            foreach ($trasladosNodeList as $trasladoNode) {
                $base = $trasladoNode->getAttribute('Base');
                $impuesto = $trasladoNode->getAttribute('Impuesto');
                $tipoFactor = $trasladoNode->getAttribute('TipoFactor');
                $tasaOCuota = $trasladoNode->getAttribute('TasaOCuota');
                $importe = $trasladoNode->getAttribute('Importe');

                $cadenaOriginal .= "|$base|$impuesto|$tipoFactor|$tasaOCuota|$importe";
            }
        }

        return $cadenaOriginal;
    }

    public function getCadenaOriginalCFDI40Test() {
        
        //ruta al archivo XML del CFDI
        $xmlFile=public_path('cfdi4_generico.xml');
     
        // Ruta al archivo XSLT
        $xslFile = public_path('sat.gob.mx_sitio_internet_cfd_4_cadenaoriginal_4_0_cadenaoriginal_4_0.xslt'); 
     
        // Crear un objeto DOMDocument para cargar el CFDI
        $xml = new DOMDocument("1.0","UTF-8"); 
        // Cargar el CFDI
        $xml->load($xmlFile);
     
        // Crear un objeto DOMDocument para cargar el archivo de transformación XSLT
        $xsl = new DOMDocument();
        $xsl->load($xslFile);
     
        // Crear el procesador XSLT que nos generará la cadena original con base en las reglas descritas en el XSLT
        $proc = new XSLTProcessor;
        // Cargar las reglas de transformación desde el archivo XSLT.
        $proc->importStyleSheet($xsl);
        // Generar la cadena original y asignarla a una variable
        $cadenaOriginal = $proc->transformToXML($xml);
     
        echo $cadenaOriginal;
    }

    public function getCadenaOriginalCFDI401() {

        $xmlFilePath = public_path('cfdi4_generico.xml');

        // Verificar que el archivo XML existe
        if (!file_exists($xmlFilePath)) {
            return 'El archivo XML no existe';
        }

        $xml = file_get_contents($xmlFilePath);

        $xsltFilePath = public_path('sat.gob.mx_sitio_internet_cfd_4_cadenaoriginal_4_0_cadenaoriginal_4_0.xslt');

        // Verificar que el archivo XML existe
        if (!file_exists($xsltFilePath)) {
            return 'El archivo XSLT no existe';
        }

        // Cargamos el archivo XSLT que genera la cadena original
        $xslt = simplexml_load_file($xsltFilePath);

        // Convertimos el XML a una instancia de DOMDocument
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        // Ejecutamos la transformación XSLT
        $transform = new XSLTProcessor();
        $transform->importStylesheet($xslt);

        // Obtenemos el resultado de la transformación
        $result = $transform->transformToXml($dom);

        // Eliminamos los espacios en blanco del resultado
        $result = preg_replace('/\s+/', '', $result);

        return $result;
    }

    public function getCadenaOriginalCFDI402() {

        $xmlFilePath = public_path('cfdi4_generico.xml');

        // Verificar que el archivo XML existe
        if (!file_exists($xmlFilePath)) {
            return 'El archivo XML no existe';
        }

        $xml = file_get_contents($xmlFilePath);

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xsltFilePath = public_path('sat.gob.mx_sitio_internet_cfd_4_cadenaoriginal_4_0_cadenaoriginal_4_0.xslt');

        // Verificar que el archivo XML existe
        if (!file_exists($xsltFilePath)) {
            return 'El archivo XSLT no existe';
        }

        // Cargamos el archivo XSLT que genera la cadena original
        $xslt = simplexml_load_file($xsltFilePath);

        // Ejecutamos la transformación XSLT
        $transform = new XSLTProcessor();
        $transform->importStylesheet($xslt);

        // Obtenemos el resultado de la transformación
        $result = $transform->transformToXml($dom);

        // Eliminamos los espacios en blanco del resultado
        $result = preg_replace('/\s+/', '', $result);

        // Eliminamos los caracteres especiales del resultado
        //$result = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $result);

        return $result;
    }

    public function getCadenaOriginalCFDI403() {
        $xmlFilePath = public_path('cfdi4_generico.xml');

        // Verificar que el archivo XML existe
        if (!file_exists($xmlFilePath)) {
            return 'El archivo XML no existe';
        }

        $xmlContent = file_get_contents($xmlFilePath);

        // Crear un objeto DOMDocument para procesar el XML
        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);

        // Obtener el nodo Comprobante
        $comprobanteNode = $dom->getElementsByTagName('cfdi:Comprobante')->item(0);

        // Verificar si se encontró el nodo Comprobante
        if (!$comprobanteNode) {
            return 'No se encontró el nodo Comprobante en el XML';
        }

        // Construir la cadena original
        $cadenaOriginal = $comprobanteNode->getAttribute('Version') . '|'
            . $comprobanteNode->getAttribute('Fecha') . '|'
            . $comprobanteNode->getAttribute('Sello') . '|'
            . $comprobanteNode->getAttribute('FormaPago') . '|'
            . $comprobanteNode->getAttribute('NoCertificado') . '|'
            . $comprobanteNode->getAttribute('Certificado') . '|'
            . $comprobanteNode->getAttribute('SubTotal') . '|'
            . $comprobanteNode->getAttribute('Moneda') . '|'
            . $comprobanteNode->getAttribute('Total') . '|'
            . $comprobanteNode->getAttribute('TipoDeComprobante') . '|'
            . $comprobanteNode->getAttribute('MetodoPago') . '|'
            . $comprobanteNode->getAttribute('LugarExpedicion');

        // Obtener el nodo TimbreFiscalDigital
        $timbreFiscalNode = $dom->getElementsByTagName('tfd:TimbreFiscalDigital')->item(0);

        // Verificar si se encontró el nodo TimbreFiscalDigital
        if (!$timbreFiscalNode) {
            return 'No se encontró el nodo TimbreFiscalDigital en el XML';
        }

        // Agregar el UUID al final de la cadena original
        $cadenaOriginal .= '|' . $timbreFiscalNode->getAttribute('UUID');

        return $cadenaOriginal;
    }

    public function getCadenaOriginalCFDI40() {
        //ruta al archivo XML del CFDI
        $xmlFile=public_path('cfdi4_generico.xml');
        //$xmlFile = "https://apisocial.internow.com.mx/cfdi4_generico.xml";
     
        // Ruta al archivo XSLT
        //$xslFile = "https://www.sat.gob.mx/sitio_internet/cfd/4/cadenaoriginal_4_0/cadenaoriginal_4_0.xslt";

        $xslFile = public_path('sat.gob.mx_sitio_internet_cfd_4_cadenaoriginal_4_0_cadenaoriginal_4_0.xslt'); 
     
        // Crear un objeto DOMDocument para cargar el CFDI
        $xml = new DOMDocument("1.0","UTF-8"); 
        // Cargar el CFDI
        $xml->load($xmlFile);
     
        // Crear un objeto DOMDocument para cargar el archivo de transformación XSLT
        $xsl = new DOMDocument("1.0","UTF-8");
        $xsl->load($xslFile);
     
        // Crear el procesador XSLT que nos generará la cadena original con base en las reglas descritas en el XSLT
        $proc = new XSLTProcessor;
        // Cargar las reglas de transformación desde el archivo XSLT.
        $proc->importStyleSheet($xsl);
        // Generar la cadena original y asignarla a una variable
        $cadenaOriginal = $proc->transformToXML($xml);
     
        echo $cadenaOriginal;
    }

}
