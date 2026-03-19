<?php

/* header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept,charset,boundary,Content-Length');
header('Access-Control-Allow-Origin: *'); */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PruebasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ApiOpenAiController;
use App\Http\Controllers\UploadImagenController;
use App\Http\Controllers\ApiConektaController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\ApiPayPalController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\ApiPexelsController;
use App\Http\Controllers\BrandImageController;
use App\Http\Controllers\ApiMetaController;
use App\Http\Controllers\PostGeneralController;
use App\Http\Controllers\CryptController;
use App\Http\Controllers\WebhooksController;
use App\Http\Controllers\WebhooksSandboxController;
use App\Http\Controllers\ApiMetaSandboxController;
use App\Http\Controllers\FrameBaseController;

use App\Http\Controllers\WebhooksWhatsAppController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ApiTextCortexController;
use App\Http\Controllers\BotRespuestaController;
use App\Http\Controllers\BotConfigController;
use App\Http\Controllers\BotClienteController;
use App\Http\Controllers\BotChatController;
use App\Http\Controllers\ApiConektaBotController;
use App\Http\Controllers\BotCitaController;
use App\Http\Controllers\BotFlowController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\CotizacionController;

use App\Http\Controllers\ApiCfdiController;
use App\Http\Controllers\FlowFacturaController;
use App\Http\Controllers\TimbradoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\FacturaAuxController;

use App\Http\Controllers\GoopyCatalogoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//----Pruebas TimbradoController
Route::get('timbrado/timbrar/{factura_id}', [TimbradoController::class, 'timbrar']);
Route::get('timbrado/timbrado/data', [TimbradoController::class, 'timbradoData']);

//----Pruebas ApiCfdiController
Route::get('cfdi/get_token', [ApiCfdiController::class, 'getToken']);
Route::get('cfdi/timbrar', [ApiCfdiController::class, 'timbrar']);
Route::get('cfdi/convert/certificados', [ApiCfdiController::class, 'convertirCertificado']);
Route::get('cfdi/firmar', [ApiCfdiController::class, 'firmarCFDI']);
Route::get('cfdi/sello', [ApiCfdiController::class, 'generarSelloDigital']);
Route::get('cfdi/test', [ApiCfdiController::class, 'test']);
Route::get('cfdi/cadena_original', [ApiCfdiController::class, 'generarCadenaOriginal']);
Route::get('cfdi/cadena_original2', [ApiCfdiController::class, 'getCadenaOriginalCFDI40']);
Route::get('cfdi/cadena_original/test', [ApiCfdiController::class, 'getCadenaOriginalCFDI40Test']);

Route::get('/test', function (Request $request) {
    return 1;
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


//----Pruebas AuthController
Route::post('auth/login/web', [AuthController::class, 'loginWeb']);
Route::post('auth/me', [AuthController::class, 'me']);
Route::get('auth/user', [AuthController::class, 'getAuthenticatedUser']);

//----Pruebas UsuarioController
Route::post('usuarios', [UsuarioController::class, 'store']);

//----Pruebas ApiOpenAiController
Route::get('open_ai/davinci/textos', [ApiOpenAiController::class, 'davinciTextos']);
Route::get('open_ai/davinci/palabras_clave', [ApiOpenAiController::class, 'davinciPalabrasClave']);
Route::get('open_ai/davinci/palabras_clave/post', [ApiOpenAiController::class, 'davinciPalabrasClavePost']);
Route::get('open_ai/davinci/escena', [ApiOpenAiController::class, 'davinciEscena']);
Route::get('open_ai/dalle', [ApiOpenAiController::class, 'dalle']);
Route::get('open_ai/generar/all/textos', [ApiOpenAiController::class, 'generarAllTextos']);
Route::get('open_ai/davinci/respuesta', [ApiOpenAiController::class, 'davinciRespuesta']);

//----Pruebas PostController
//Route::get('posts/index', [PostController::class, 'index']);
Route::get('posts/dercargar/url', [PostController::class, 'dercargarUrl']);
Route::post('posts/binario/to/url', [PostController::class, 'storeLinkImagenTestV1']);
Route::post('posts/base64/to/url', [PostController::class, 'storeLinkImagenTestV2']);
Route::get('posts/allow_origin/{imagen}', [PostController::class, 'imagenAllowOrigin']);
Route::get('post_generales/allow_origin/{imagen}', [PostController::class, 'imagenAllowOriginGeneral']);
Route::post('posts/new_link/imagen', [PostController::class, 'storeNewLinkImagen']);

//----Pruebas BrandController
//Route::get('marcas/ver/todas', [BrandController::class, 'indexAll']);
Route::get('marcas/mi_marca/{id}', [BrandController::class, 'show']);
Route::get('marcas/mi_marca2/{id}', [BrandController::class, 'show2']);
Route::get('marcas/fin/periodo/prueba', [BrandController::class, 'finPeriodoPrueba']);
Route::get('marcas/allow_origin/{imagen}', [BrandController::class, 'imagenAllowOrigin']);

//----Pruebas PruebasController
Route::get('pruebas/check_catalogo/{modelo}/{frase}', [PruebasController::class, 'checkCatalogo']);
Route::get('pruebas/generar_pdf/cotizacion/{cotizacion_id}', [PruebasController::class, 'cotizacionPdf']);
Route::get('pruebas/generar_vista/pedido', [PruebasController::class, 'mostrarVistaPedido']);
Route::get('pruebas/generar_vista/cotizacion/{id}', [PruebasController::class, 'mostrarVistaCotizacion']);
Route::get('pruebas/generar_pdf/factura/{factura_id}', [PruebasController::class, 'facturaPdf']);
Route::get('pruebas/generar_vista/factura/{id}', [PruebasController::class, 'mostrarVistaFactura']);
Route::get('pruebas/generar_pdf', [PruebasController::class, 'generarPdf']);
Route::get('pruebas/hora', [PruebasController::class, 'hora']);
Route::get('pruebas/email/{factura_id}', [PruebasController::class, 'emailTest']);
Route::get('pruebas/encrypt', [PruebasController::class, 'encrypt']);
Route::get('pruebas/decrypt', [PruebasController::class, 'decrypt']);
Route::get('pruebas/test_token', [PruebasController::class, 'testToken']);
Route::get('pruebas/validar_token', [PruebasController::class, 'validarToken']);
Route::get('pruebas/test/catalogos', [PruebasController::class, 'catTest']);
Route::get('pruebas/notificar_new_clientes', [PruebasController::class, 'notificarNewClientes']);
Route::get('pruebas/catalogo_vista/goopy', [PruebasController::class, 'catalogoVistaGoopy']);
Route::get('pruebas/catalogo_pdf/goopy', [PruebasController::class, 'catalogoPdfGoopy']);
Route::get('pruebas/send_email', [PruebasController::class, 'sendEmail']);
Route::get('pruebas/test_ai', [PruebasController::class, 'testAI']);
Route::get('pruebas/test_rate_limit', [PruebasController::class, 'testRateLimit']);

//----Pruebas SistemaController
Route::put('sistema/test/{id}', [SistemaController::class, 'updateTest']);
Route::get('sistema/costo_marca', [SistemaController::class, 'costoMarca']);
Route::get('sistema/costo_bot', [SistemaController::class, 'costoBot']);

//----Pruebas FrameController
Route::get('marcos/allow_origin/{imagen}', [FrameController::class, 'imagenAllowOrigin']);

//----Pruebas FrameBaseController
Route::get('marcos_base', [FrameBaseController::class, 'index']);
Route::get('marcos_base/habilitados/{brand_id}', [FrameBaseController::class, 'indexHabilitados']);
Route::post('marcos_base', [FrameBaseController::class, 'store']);
Route::delete('marcos_base/{id}', [FrameBaseController::class, 'destroy']);
Route::get('marcos_base/allow_origin/{imagen}', [FrameBaseController::class, 'imagenAllowOrigin']);
Route::put('marcos_base/{id}', [FrameBaseController::class, 'update']);

//----Pruebas ApiConektaController
Route::get('conekta/cobros/auto', [ApiConektaController::class, 'cobrosAutomaticos']);
Route::post('conekta/order', [ApiConektaController::class, 'postOrderConekta']);

//----Pruebas ApiPayPalController
Route::get('paypal/test', [ApiPayPalController::class, 'test']);
Route::post('paypal/order', [ApiPayPalController::class, 'order']);

//----Pruebas BrandImageController
Route::get('brand_images/allow_origin/{imagen}', [BrandImageController::class, 'imagenAllowOrigin']);


//----Pruebas ApiMetaController
Route::get('meta/access_token_long_live', [ApiMetaController::class, 'userAccesTokenLongLive']);
Route::get('meta/accounts', [ApiMetaController::class, 'accounts']);
Route::get('meta/publicar/posts_normales', [ApiMetaController::class, 'publicarPostsNormales']);
Route::get('meta/photos', [ApiMetaController::class, 'photos']);
Route::get('meta/picture', [ApiMetaController::class, 'picture']);
Route::get('meta/me/accounts', [ApiMetaController::class, 'meAccounts']);
Route::get('meta/media', [ApiMetaController::class, 'media']);
Route::get('meta/media_publish', [ApiMetaController::class, 'mediaPublish']);
Route::get('meta/subscribed_apps', [ApiMetaController::class, 'getSubscribedApps']);
Route::get('meta/post/subscribed_apps', [ApiMetaController::class, 'postSubscribedApps']);
Route::get('meta/feed', [ApiMetaController::class, 'feed']);
Route::get('meta/comments', [ApiMetaController::class, 'getComments']);
Route::post('meta/comments', [ApiMetaController::class, 'postComments']);
Route::get('meta/post/comments', [ApiMetaController::class, 'postComments']);
Route::get('meta/post/replies', [ApiMetaController::class, 'postReplies']);
Route::post('meta/replies', [ApiMetaController::class, 'postReplies']);

Route::get('pexels/{per_page}/post/{post_id}', [ApiPexelsController::class, 'getImagenesPost']);
Route::get('pexels/count/results/{query}', [ApiPexelsController::class, 'countResults']);
Route::get('pexels/imagenes/marco', [ApiPexelsController::class, 'getImagenesMarco']);

//----Pruebas WebhooksController
Route::get('webhooks/meta', [WebhooksController::class, 'meta']);
Route::post('webhooks/meta', [WebhooksController::class, 'metaHandle']);
Route::get('webhooks/meta/instagram', [WebhooksController::class, 'meta']);
Route::post('webhooks/meta/instagram', [WebhooksController::class, 'metaHandle']);

Route::get('webhooks/whatsapp', [WebhooksWhatsAppController::class, 'meta']);
Route::post('webhooks/whatsapp', [WebhooksWhatsAppController::class, 'metaHandle']);

Route::get('webhooks_sandbox/meta', [WebhooksSandboxController::class, 'meta']);
Route::post('webhooks_sandbox/meta', [WebhooksSandboxController::class, 'metaHandle']);


//----Pruebas ApiMetaSandboxController
Route::get('meta_sandbox/access_token_long_live', [ApiMetaSandboxController::class, 'userAccesTokenLongLive']);
Route::get('meta_sandbox/accounts', [ApiMetaSandboxController::class, 'accounts']);
Route::get('meta_sandbox/publicar/posts_normales', [ApiMetaSandboxController::class, 'publicarPostsNormales']);
Route::get('meta_sandbox/photos', [ApiMetaSandboxController::class, 'photos']);
Route::get('meta_sandbox/picture', [ApiMetaSandboxController::class, 'picture']);
Route::get('meta_sandbox/me/accounts', [ApiMetaSandboxController::class, 'meAccounts']);
Route::get('meta_sandbox/media', [ApiMetaSandboxController::class, 'media']);
Route::get('meta_sandbox/media_publish', [ApiMetaSandboxController::class, 'mediaPublish']);
Route::get('meta_sandbox/subscribed_apps', [ApiMetaSandboxController::class, 'getSubscribedApps']);
Route::get('meta_sandbox/post/subscribed_apps', [ApiMetaSandboxController::class, 'postSubscribedApps']);
Route::get('meta_sandbox/feed', [ApiMetaSandboxController::class, 'feed']);
Route::get('meta_sandbox/comments', [ApiMetaSandboxController::class, 'getComments']);
Route::post('meta_sandbox/comments', [ApiMetaSandboxController::class, 'postComments']);
Route::get('meta_sandbox/post/comments', [ApiMetaSandboxController::class, 'postComments']);
Route::get('meta_sandbox/post/replies', [ApiMetaSandboxController::class, 'postReplies']);
Route::post('meta_sandbox/replies', [ApiMetaSandboxController::class, 'postReplies']);

//----Pruebas BotController
Route::get('bots', [BotController::class, 'index']);
Route::post('bots', [BotController::class, 'store']);
Route::get('bots/message/text', [BotController::class, 'messageText']);
Route::get('bots/encrypt/{cadena}', [BotController::class, 'encryptBot']);
Route::get('bots/alert_token', [BotController::class, 'alertToken']);

//----Pruebas BotRespuestaController
Route::get('bots_respuesta/mensajes/sin_procesar', [BotRespuestaController::class, 'getMensajesSinProcesar']);

//----Pruebas ApiConektaController
Route::get('bots_conekta/cobros/auto', [ApiConektaBotController::class, 'cobrosAutomaticos']);
Route::post('bots_conekta/order', [ApiConektaBotController::class, 'postOrderConekta']);

//----Pruebas BotConfigController
Route::post('bots_config', [BotConfigController::class, 'store']);
Route::get('bots_config/bot/{bot_id}', [BotConfigController::class, 'getConfig']);
Route::put('bots_config/{config_id}', [BotConfigController::class, 'update']);
Route::delete('bots_config/{config_id}', [BotConfigController::class, 'destroy']);

//----Pruebas BotClienteController
Route::get('bots_cliente/fin/periodo/prueba', [BotClienteController::class, 'finPeriodoPrueba']);
Route::get('bots_cliente', [BotClienteController::class, 'index']);
Route::get('bots_cliente/get_cliente/{cliente_id}', [BotClienteController::class, 'getCliente']);
Route::get('bots_cliente/allow_origin/{imagen}', [BotClienteController::class, 'imagenAllowOrigin']);
Route::post('bots_cliente/cambiar_imagenes/{cliente_id}', [BotClienteController::class, 'cambiarImagenes']);

//----Pruebas BotFlowController
Route::get('bots_flow/flows/{bot_id}', [BotFlowController::class, 'indexFlows']);
Route::post('bots_flow/flow', [BotFlowController::class, 'storeFlow']);
Route::get('bots_flow/stages/{flow_id}', [BotFlowController::class, 'indexFlowStages']);
Route::post('bots_flow/stage', [BotFlowController::class, 'storeFlowStage']);
Route::get('bots_flow/validations/{stage_id}', [BotFlowController::class, 'indexStageValidations']);
Route::post('bots_flow/validation', [BotFlowController::class, 'storeStageValidation']);

//----Pruebas BotCitaController
Route::get('bots_cita/mis_citas/{id}', [BotCitaController::class, 'misCitas']);
Route::get('bots_cita/mis_citas/{id}/filter_mes', [BotCitaController::class, 'misCitasFilterMes']);
Route::get('bots_cita/notificar_citas', [BotCitaController::class, 'notificarCitas']);
Route::put('bots_cita/{id}', [BotCitaController::class, 'update']);
Route::delete('bots_cita/{id}', [BotCitaController::class, 'destroy']);
Route::post('bots_cita/{cliente_id}', [BotCitaController::class, 'store']);
Route::get('bots_cita/enviar_sms/{number}/{message}', [BotCitaController::class, 'enviarSMS']);

//----Pruebas BotChatController
Route::get('bots_chat/{cliente_id}', [BotChatController::class, 'show']);
Route::get('bots_chat/cron/borrar/chats', [BotChatController::class, 'autoborrarChats']);
//Route::get('bots_chat/cron/reset/count_querys', [BotChatController::class, 'resetCountQuerys']);

//----Pruebas ProductoController
Route::get('productos/mis_productos/{cliente_id}', [ProductoController::class, 'index']);
Route::post('productos', [ProductoController::class, 'store']);
Route::put('productos/{id}', [ProductoController::class, 'update']);
Route::delete('productos/{id}', [ProductoController::class, 'destroy']);

Route::post('productos/set_imagen/{producto_id}', [ProductoController::class, 'setImagen']);
Route::get('productos/get_imagenes/{producto_id}', [ProductoController::class, 'getImagenes']);
Route::delete('productos/destroy_imagen/{imagen_id}', [ProductoController::class, 'destroyImagen']);

Route::post('productos/set_color/{producto_id}', [ProductoController::class, 'setColor']);
Route::get('productos/get_colores/{producto_id}', [ProductoController::class, 'getColores']);
Route::get('productos/get_colores/activos/{producto_id}', [ProductoController::class, 'getColoresActivos']);
Route::put('productos/update_color/{color_id}', [ProductoController::class, 'updateColor']);

Route::post('productos/set_tipo/{color_id}', [ProductoController::class, 'setTipo']);
Route::get('productos/get_tipos/{color_id}', [ProductoController::class, 'getTipos']);
Route::put('productos/update_tipo/{tipo_id}', [ProductoController::class, 'updateTipo']);
Route::post('productos/upload/archivo', [ProductoController::class, 'uploadArchivo']);

//----Pruebas PedidoController
Route::get('pedidos/mis_pedidos/curso/{cliente_id}', [PedidoController::class, 'index']);
Route::get('pedidos/mis_pedidos/finalizados/{cliente_id}', [PedidoController::class, 'indexFinalizadosFilter']);
Route::get('pedidos/mis_pedidos', [PedidoController::class, 'indexTest']);
//Route::post('pedidos', [PedidoController::class, 'storeTest']);
Route::put('pedidos/finalizar/{id}', [PedidoController::class, 'updateFinalizar']);
Route::put('pedidos/cancelar/{id}', [PedidoController::class, 'updateCancelar']);

//----Pruebas CotizacionController
Route::get('cotizaciones/mis_cotizaciones/curso/{cliente_id}', [CotizacionController::class, 'index']);
Route::get('cotizaciones/mis_cotizaciones/finalizados/{cliente_id}', [CotizacionController::class, 'indexFinalizadosFilter']);
Route::put('cotizaciones/finalizar/{id}', [CotizacionController::class, 'updateFinalizar']);
Route::put('cotizaciones/cancelar/{id}', [CotizacionController::class, 'updateCancelar']);

//----Pruebas FacturaController
Route::get('cfdi/get_cliente_empresa/{cliente_id}', [FacturaController::class, 'getClienteEmpresa']);
Route::get('cfdi/get_codigo_postal/{cp}', [FacturaController::class, 'getCodigoPostal']);
Route::get('cfdi/get_catalogo_regimen', [FacturaController::class, 'getCatalogoRegimen']);
Route::put('cfdi/put_empresa/{empresa_id}', [FacturaController::class, 'update']);
Route::post('cfdi/upload_certificado', [FacturaController::class, 'storeArchivo']);
Route::get('cfdi/get_emitidas/{cliente_id}', [FacturaController::class, 'indexEmitidasFilter']);
Route::get('cfdi/get_canceladas/{cliente_id}', [FacturaController::class, 'indexCanceladasFilter']);
Route::get('cfdi/get_factura/{factura_id}', [FacturaController::class, 'getFactura']);
Route::post('cfdi/cancelar_factura/{factura_id}', [FacturaController::class, 'cancelarFactura']);
Route::get('cfdi/get_catalogo_productos', [FacturaController::class, 'getCatalogoProductos']);
Route::get('cfdi/get_catalogo_unidades', [FacturaController::class, 'getCatalogoUnidades']);
Route::put('cfdi/put_producto_por_defecto/{empresa_id}', [FacturaController::class, 'updateProductoPorDefecto']);
Route::get('cfdi/get_catalogo_forma_pago', [FacturaController::class, 'getCatalogoFormaPago']);
Route::get('cfdi/get_catalogo_metodo_pago', [FacturaController::class, 'getCatalogoMetodoPago']);
Route::get('cfdi/get_clientes_rfc', [FacturaController::class, 'getClientesPorRfc']);
Route::get('cfdi/get_clientes_all', [FacturaController::class, 'getAllClientes']);
Route::get('cfdi/get_catalogo_uso_cfdi', [FacturaController::class, 'getCatalogoUsoCfdi']);

//----Pruebas FlowFacturaController
Route::post('cfdi/timbrar_desde_panel/{empresa_id}', [FlowFacturaController::class, 'timbrarDesdePanel']);

//----Pruebas GoopyCatalogoController
Route::get('goopy/catalogo_pdf/{tipo}', [GoopyCatalogoController::class, 'catalogoPdfGoopy']);

Route::group(['middleware' => ['jwt.verify']], function() {

    //----Pruebas UploadImagenController
    Route::post('imagenes',[UploadImagenController::class, 'store']);

    //----Pruebas UsuarioController
    //Route::get('usuarios', [UsuarioController::class, 'index']);
    //Route::post('usuarios', [UsuarioController::class, 'store']);
    Route::put('usuarios/{id}', [UsuarioController::class, 'update']);
    
    //----Pruebas BrandController
    Route::get('marcas/{user_id}', [BrandController::class, 'index']);
    Route::post('marcas', [BrandController::class, 'store']);
    Route::put('marcas/{id}', [BrandController::class, 'update']);
    Route::get('marcas/servicios/{brand_id}', [BrandController::class, 'getServices']);
    Route::put('marcas/servicios/{brand_id}', [BrandController::class, 'updateServices']);
    Route::get('marcas/horario/{brand_id}', [BrandController::class, 'getHorario']);
    Route::put('marcas/horario/{brand_id}', [BrandController::class, 'updateHorario']);
    Route::delete('marcas/{id}', [BrandController::class, 'destroy']);

    //----Pruebas NetworkController
    Route::get('redes/user/{user_id}', [NetworkController::class, 'misRedes']);
    Route::get('redes/{brand_id}', [NetworkController::class, 'index']);
    Route::post('redes', [NetworkController::class, 'store']);
    Route::delete('redes/{id}', [NetworkController::class, 'destroy']);

    //----Pruebas PostController
    Route::post('posts_p/crear/personal', [PostController::class, 'storePostPersonal']);
    Route::post('/posts_p/crear/personal/vps', [PostController::class, 'storePostPersonalVps']);

    //----Pruebas UsuarioController
    Route::post('usuarios/crear/marca', [UsuarioController::class, 'crearUserMarca']);

    //----Pruebas FrameController
    Route::get('marcos/marca/{brand_id}/contador', [FrameController::class, 'marcosMarcaContador']);
    Route::post('marcos', [FrameController::class, 'store']);

    //----Pruebas BotClienteController
    Route::get('bots_cliente/{cliente_id}', [BotClienteController::class, 'show']);
    Route::put('bots_cliente/status/{cliente_id}', [BotClienteController::class, 'updateStatus']);
    //----Pruebas BotClienteController
    Route::put('bots_cliente/{cliente_id}', [BotClienteController::class, 'update']);

});

Route::group(['middleware' => ['jwt.verify.admin']], function() {

    //----Pruebas UsuarioController
    Route::get('usuarios/clientes', [UsuarioController::class, 'index']);
    Route::put('usuarios/status/{usuario_id}', [UsuarioController::class, 'updateStatus']);
    Route::post('usuarios/crear', [UsuarioController::class, 'crear']);
    Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy']);

    //----Pruebas PostController
    Route::get('posts/index/no_aprobados', [PostController::class, 'indexNoAprobados']);
    Route::post('posts/aprobar/{post_id}', [PostController::class, 'aprobarPost']);
    Route::post('posts/aprobar/link_archivo/{post_id}', [PostController::class, 'aprobarPostLink']);
    Route::delete('posts/eliminar/{post_id}', [PostController::class, 'eliminarPost']);
    Route::post('posts_g/crear/general', [PostController::class, 'storePostGeneral']);

    //----Pruebas BrandController
    Route::put('marcas/status/{brand_id}', [BrandController::class, 'updateStatus']);
    Route::put('marcas/update_admin/{id}', [BrandController::class, 'updateAdmin']);
    Route::get('marcas/index/basico', [BrandController::class, 'indexBasic']);

    //----Pruebas SistemaController
    Route::get('sistema', [SistemaController::class, 'index']);
    Route::post('sistema', [SistemaController::class, 'store']);
    Route::put('sistema/{id}', [SistemaController::class, 'update']);

    //----Pruebas ApiPexelsController
    Route::get('pexels', [ApiPexelsController::class, 'getImagenes']);
    Route::get('pexels/{per_page}/marca/{brand_id}', [ApiPexelsController::class, 'getImagenesMarca']);
    //Route::get('pexels/{per_page}/post/{post_id}', [ApiPexelsController::class, 'getImagenesPost']);

    //----Pruebas FrameController
    Route::get('marcos', [FrameController::class, 'index']);
    Route::get('marcos/marca/{brand_id}', [FrameController::class, 'marcosMarca']);
    Route::get('marcos/marca/{brand_id}/to_posts', [FrameController::class, 'marcosMarcaToPosts']);
    //Route::post('marcos', [FrameController::class, 'store']);
    Route::delete('marcos/{id}', [FrameController::class, 'destroy']);
    Route::post('marcos/con_marco_base', [FrameController::class, 'storeConFrameBase']);

    //----Pruebas BrandImageController
    Route::get('brand_images/marca/{brand_id}', [BrandImageController::class, 'imagenesMarca']);
    Route::get('brand_images/marca/{brand_id}/activas', [BrandImageController::class, 'imagenesMarcaActivas']);
    Route::get('brand_images/marca/{brand_id}/to_posts', [BrandImageController::class, 'imagenesMarcaToPosts']);
    Route::post('brand_images', [BrandImageController::class, 'store']);
    Route::delete('brand_images/{id}', [BrandImageController::class, 'destroy']);
    Route::put('brand_images/{id}', [BrandImageController::class, 'aprobar']);

    //----Pruebas ApiMetaController
    Route::get('meta/publicar/posts_generales', [ApiMetaController::class, 'publicarPostsGenerales']);

    //----Pruebas PostGeneralController
    Route::get('posts_generales/marcas', [PostGeneralController::class, 'indexMarcasToPost']);
    Route::post('posts_generales/publicar/posts', [PostGeneralController::class, 'publicarPostsGenerales']);

    //----Pruebas ApiOpenAiController
    Route::get('open_ai/generar/textos/usuario/{usuario_id}', [ApiOpenAiController::class, 'generarTextosUsuario']);
    Route::get('open_ai/generar/imagenes/{post_id}', [ApiOpenAiController::class, 'generarImagenes']);

    //----Pruebas CryptController
    Route::get('crypt/encrypt/{cadena}', [CryptController::class, 'encrypt']);
    Route::get('crypt/decrypt/{cadena}', [CryptController::class, 'decrypt']);

    //----Pruebas ApiConektaController
    Route::post('conekta/cobro/auto/marca/{marca_id}', [ApiConektaController::class, 'cobroAutomaticoMarca']);

    //----Pruebas BotClienteController
    Route::delete('bots_cliente/{cliente_id}', [BotClienteController::class, 'destroy']);

    //----Pruebas BotController
    Route::put('bots/update/access_token', [BotController::class, 'updateTokenBot']);
    Route::get('bots/get/access_token', [BotController::class, 'getTokenBot']);

});

// Cargar rutas de la plaza del vestido
Route::prefix('plaza_vestido')->group(function () {
    require __DIR__ . '/plaza_vestido/plaza_vestido.php';
});

