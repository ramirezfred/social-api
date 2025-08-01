<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;

use DB;

use Exception;

// use Mail;
// use Session;
// use Redirect;
// use Swift_SmtpTransport;
// use Swift_Mailer;

use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Swift_SmtpTransport;
use Swift_Mailer;
use Swift_Message;

trait EmailTrait
{

    // public static $Username = "hola@internow.com.mx";
    // public static $Password = "k9r[~Z]PHYHv";
    // public static $Instance = "mail.internow.com.mx";

    public static $Username = "hola@goopy.app";
    public static $Password = "BEK2fTNnx%q[";
    public static $Instance = "mail.goopy.app";

    public function sendMailV1(
        $vista_blade,$subject,$data,$correo = 'ramirez.fred016@gmail.com'
    )
    {
        // Backup your default mailer
        $backup = Mail::getSwiftMailer();

        // Setup your gmail mailer
        $transport = Swift_SmtpTransport::newInstance('mail.internow.com.mx', 465, 'ssl');
        $transport->setUsername("hola@goopy.app");
        $transport->setPassword("BEK2fTNnx%q[");

        // Any other mailer configuration stuff needed...
        $gmail = new Swift_Mailer($transport);

        // Set the mailer as gmail
        Mail::setSwiftMailer($gmail);

        // Send your message
        Mail::send('emails/'.$vista_blade, $data, function($msj) use ($correo,$subject){
            $msj->subject($subject);
            $msj->to($correo);
        });

        // Restore your original mailer
        Mail::setSwiftMailer($backup);
    }

    public function sendMail(
        $vista_blade,$subject,$data,$correo = 'ramirez.fred016@gmail.com'
    )
    {
        $transport = (new Swift_SmtpTransport('mail.internow.com.mx', 465, 'ssl'))
            ->setUsername('hola@internow.com.mx')
            ->setPassword('k9r[~Z]PHYHv');

        $mailer = new Swift_Mailer($transport);

        $message = (new Swift_Message('Asunto del correo'))
            ->setFrom(['hola@internow.com.mx' => 'Tu Nombre'])
            ->setTo(['ramirez.fred016@gmail.com'])
            ->setBody('Contenido del correo electrónico');

        //$result = $mailer->send($message);

        // try {
        //     Mail::send([], [], function (Message $message) use ($mailer, $message) {
        //         $message->setSwiftMailer($mailer);
        //         $message->setBody($message->getBody(), 'text/html');
        //         $message->setSubject($message->getSubject());
        //         $message->setTo($message->getTo());
        //         $message->setFrom($message->getFrom());

        //         $mailer->send($message);
        //     });
        //     Log::info('Correo electrónico enviado exitosamente');
        // } catch (\Exception $e) {
        //     Log::error('Error al enviar correo electrónico: ' . $e->getMessage());
        // }

        //Enviamos el correo 
        Mail::send('emails/notificacion', $data, function($msj) use ($correo,$subject){
            $msj->subject($subject);
            $msj->to($correo);
        });
       

    }

    public function _sendMailPagoMarca($user_email,$marca)
    {
        $data = array( 
            'mensaje'=>'El usuario '.$user_email.' realizó el pago de su marca '.$marca,
        );

        static::sendMail('notificacion','Nuevo pago de Marca',$data,'ramirez.fred016@gmail.com');

        return 0;
    }


   

}
