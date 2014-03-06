<?php

namespace Core;

use Silex\Application as SilexApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Monolog\Logger;
use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use Dominikzogg\Silex\Provider\DoctrineOrmManagerRegistryProvider;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder ,
    Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder ,
    Symfony\Component\Security\Core\Encoder\EncoderFactory;

class Application extends SilexApplication
{
    use SilexApplication\TwigTrait;
    use SilexApplication\SecurityTrait;    
    use SilexApplication\FormTrait;
    use SilexApplication\UrlGeneratorTrait;
    use SilexApplication\SwiftmailerTrait
    {
	    mail as protected _mail;
    }
    use SilexApplication\MonologTrait;
    use SilexApplication\TranslationTrait;
    
/**
 * 
 * @param array $values
 */    
    public function __construct(array $values = array())    
    {
        parent::__construct( $values );

        $this['route_class'] = 'Core\\Route';

        if( !$this['debug'] )
        {
            ErrorHandler::register();
            $this->error(
            function( \Exception $e , $code )
            {
                return $this->handleError( $e , $code );
            });
        }

        Request::enableHttpMethodParameterOverride();
    }

    public function boot()
    {
        $this->after(
        function( Request $request, Response $response )
        {
            $this->prepareResponse( $response );
        });

        parent::boot();
    }

/**
 * @inheritdoc
 */    
    public function redirect( $url , $status = 302 )
    {
        $response   =	parent::redirect( $url , $status );
        $this->terminate( $this['request'] , $response );
        return $response;
    }

    /**
     *
     * @param \Swift_Message $message
     * @param null $failedRecipients
     * @return bool
     */
    public function mail( \Swift_Message $message , &$failedRecipients = null )
    {    	
        if( $this['debug'] )
        {
            $message->setTo( $this['mailer_user'] );
        }

        return $this->_mail( $message , $failedRecipients );
	
//	else
//	{	    	    
//	    $this->finish
//	    (function() use( $message )
//	    {		
//		$this->_mail( $message );	
//	    });	    
//	    
//	    return null;
//	}
    }    
    
/**
 * 
 * @param \Symfony\Component\HttpFoundation\Request $request
 * @return string
 */    
    protected function logRequest( Request $request )
    {
        $message    =	'request';
        $context    =	(array)$request;

        $this->log( $message , $context );

        return $message;
    }
    
/**
 * 
 * @param \Exception $e
 * @param int $code
 * @return Response
 */    
    protected function handleError( \Exception $e , $code )
    {
        if( $e instanceof HttpExceptionInterface )
        {
            try
            {
                $response	=   $this->render( "/error/{$code}.html.twig" , [ 'error' => $e ] );
            }
            catch( \Twig_Error_Loader $twe )
            {
                $response	=   $this->render( '/error/error.html.twig' , [ 'error' => $e ] );
            }

            $response->headers->add( $e->getHeaders() );
        }
        else
        {
            $response	=   $this->render( '/error/error.html.twig' , [ 'error' => $e ] );
        }

        $this->prepareResponse( $response );


        return $response;
    }
    
/**
 * 
 * @param \Symfony\Component\HttpFoundation\Response $response
 * @return \Symfony\Component\HttpFoundation\Response
 */    
    protected function prepareResponse( Response $response )
    {
        $response->setClientTtl( $this['response_ttl'] );
        $response->setProtocolVersion( '1.1' );
        $response->headers->set( 'Content-Language' , $this['locale'] );
        $response->setCharset( $this['charset'] );
        $response->setVary( array( 'Accept' , 'Accept-Language', 'Cookie' , 'Authorization' , 'Host' , 'Accept-Encoding' ) );

        return $response;
    }
    
    protected function initNativeSession()
    {
        $this->register(new \Silex\Provider\SessionServiceProvider(), array(
           'session.storage.options'	    =>	array(
            'cookie_lifetime'   => 3600 ,
           ),
        ));
    }
    
/**
 * 
 * @param \Doctrine\DBAL\Connection $connection
 */    
    protected function initDbSession( Connection $connection )
    {
        $this->register(new \Silex\Provider\SessionServiceProvider(), array(
           'session.storage.options'	    =>	array(
            'cookie_lifetime'   => 3600 ,
           ),
        ));

        $this['session.storage.handler'] = $this->share(
        function() use( $connection )
        {
            return new PdoSessionHandler(
            $connection->getWrappedConnection(),
            [
                'db_table'      => 'session',
                'db_id_col'     => 'id_session',
                'db_data_col'   => 'value',
                'db_time_col'   => 'timestamp',
            ],
            $this['session.storage.options']
            );
        });
    }
    
/**
 * 
 * @param string $host
 * @param string $dbname
 * @param string $user
 * @param string $password
 */    
    protected function initDoctrine( $host , $dbname , $user , $password )
    {
        $this->register(new \Silex\Provider\DoctrineServiceProvider(), array(
            'dbs.options' => array (
            $dbname => array(
                'driver'    => 'pdo_mysql',
                'host'      =>  $host,
                'dbname'    =>  $dbname,
                'user'      =>  $user,
                'password'  =>  $password,
                'charset'   =>  'UTF8',
            ),
            ),
        ));
    }

    /**
     * if you want to use Entity form type, you must call initForm prior to this
     *
     * @param string $connection
     * @param array $mappings
     */
    protected function initDoctrineOrm( $connection , array $mappings = array() )
    {
        if( empty( $mappings ) )
        {
            $mappings   =
                array(
                    array(
                        "type" => "yml",
                        "namespace" => 'App\Entity',
                        "path" => DIR."/src/Resources/config/doctrine",
                    ),
                );
        }

        $this->register(new DoctrineOrmServiceProvider, array(
            "orm.proxies_dir" => DIR ."/cache/proxies",
            "orm.em.options" => array(
                "mappings" => $mappings ,
                "connection"    =>  $connection ,
            ),
        ));

        $this->register(new DoctrineOrmManagerRegistryProvider());
    }
    
/**
 * 
 * @param integer $ttl
 */    
    protected function initHttpCache( $ttl = 3600 )
    {
        $this->register(new \Silex\Provider\HttpCacheServiceProvider(), array(
           'http_cache.cache_dir'   =>	DIR.'/cache/',
           'http_cache.esi'	    =>	null,
           'http_cache.options'	    =>	array(
            'debug'                  => $this['debug'],
            'default_ttl'            => (int)$ttl,
            'private_headers'        => array('Authorization', 'Cookie'),
            'allow_reload'           => true,
            'allow_revalidate'       => true,
            'stale_while_revalidate' => 2,
            'stale_if_error'         => 60,
           ),
        ));
    }
    
/**
 *
 * @param string $smtp smtp server
 * @param string $user smtp user
 * @param string $password smtp password
 * @param string $sender
 */
    protected function initSwiftmailer( $smtp , $user , $password , $sender = null )
    {
        $this->register(new \Silex\Provider\SwiftmailerServiceProvider(), array(
                'swiftmailer.options'     =>        array(
                    'transport'         =>  'smtp',
                    'host'              =>  $smtp,
                    'username'          =>  $user,
                    'password'          =>  $password ,
                    'sender_address'    =>  $sender ?: $user,
                    'encryption'        =>  'ssl' ,
                    'auth_mode'         =>  'login' ,
                    'port'              =>  465 ,
                        )
        ));

    }

    /**
     *
     * @param string $user Gmail user
     * @param string $password Gmail password
     */
    protected function initSwiftGmailer( $user , $password )
    {
        $this->register(new \Silex\Provider\SwiftmailerServiceProvider(), array(
            'swiftmailer.options'     =>        array(
                'transport'         =>  'gmail',
                'host'              =>  'smtp.gmail.com',
                'username'          =>  $user,
                'password'          =>  $password ,
                'sender_address'    =>  $user,
                'encryption'        =>  'tls' ,
                'auth_mode'         =>  'login' ,
                'port'              =>  587 ,
            )
        ));

    }
    
    protected function initUrlGenerator()
    {
	    $this->register(new \Silex\Provider\UrlGeneratorServiceProvider());
    }

    /**
     * form needs translator for error messages etc, it will init the translator if it's not
     */
    protected function initForm()
    {        
        $this->register(new \Silex\Provider\FormServiceProvider());
        
        if( !isset( $this['translator'] ) )
        {
            $this->initTranslation();
        }
    }
    
/**
 * if you are using a form to authenticate users, you need to enable session first
 *
 * @param array $security security config
 */    
    protected function initSecurity( array $security = array() )
    {		
	    $this->register(new \Silex\Provider\SecurityServiceProvider(), $security + [ 'security.hide_user_not_found ' => $this['debug'] ? false : true ] );

        $this['security.encoder.digest'] = $this->share(function ($app)
        {
            return new MessageDigestPasswordEncoder( 'ripemd160', false , 50 );
        });

        $this['security.encoder.plaintext'] = $this->share(
            function( $app )
            {
                return new PlaintextPasswordEncoder();
            });

        $this['security.encoder_factory'] = $this->share(function ($app) {
            return new EncoderFactory(array(
                'Symfony\Component\Security\Core\User\User' => $app['security.encoder.plaintext'],
                'App\Entity\User' => $app['security.encoder.digest'],
            ));
        });
    }
    
/**
 * 
 * @param string $pattern
 * @param array $users
 */
    protected function initBasicHttpSecurity( $pattern , array $users = array( 'admin' => [ 'ROLE_ADMIN' , 'vstup' ] ) )
    {		
        $this['security.role_hierarchy'] = array(
            'ROLE_ADMIN' => [ 'ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH' ],
        );

        $this['security.firewalls']   =
        [
            'admin' =>
            [
            'pattern'   =>  $pattern,
            'http'	    =>  true ,
            'stateless' =>  true ,
            'users'	    =>	$users
            ]
        ];

        $this->initSecurity();
    }

    /**
     * @param string $path path to templates dir, default src/Resources/views
     */
    protected function initTwig( $path = 'src/Resources/views' )
    {
        $this->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => DIR.'/'.(string)$path,
        ));
    }

    protected function initMonolog()
    {
        $this->register(new \Silex\Provider\MonologServiceProvider(), array(
            'monolog.logfile'	=>  DIR.'/logs/'. ( $this['debug'] ? 'dev' : 'prod' ) .'.log',
            'monolog.level'	=>  $this['debug'] ? Logger::DEBUG : Logger::INFO ,
            'monolog.name'	=>  'app' ,
        ));
    }
    
    protected function initValidator()
    {
	    $this->register(new \Silex\Provider\ValidatorServiceProvider());
    }
    
    protected function initTranslation( $locale = null , array $localeFallbacks = array() )
    {
        $config =   array(
            'locale_fallbacks'	=>  $localeFallbacks + [ 'en' ] ,
        );

        if( $locale )
        {
            $config['locale']   =   $locale;
        }

        $this->register(new \Silex\Provider\TranslationServiceProvider(), $config );

        $this['translator.domains'] = array(
            'messages' => array(
            'en' => array(
                'hello'     => 'Hello %name%',
                'goodbye'   => 'Goodbye %name%',
            ),
            'de' => array(
                'hello'     => 'Hallo %name%',
                'goodbye'   => 'Tschüss %name%',
            ),
            'fr' => array(
                'hello'     => 'Bonjour %name%',
                'goodbye'   => 'Au revoir %name%',
            ),
            'cs' => array(
                'Bad credentials'     => 'Chybné uživatelské údaje',
                'Your session has timed out, or you have disabled cookies.' =>  'Vaše session vypršela nebo máte vypnuté cookies'
            ),
            ),
            'validators' => array(
            'fr' => array(
                'This value should be a valid number.' => 'Cette valeur doit être un nombre.',
            ),
            ),
        );
    }

    protected function initWhoops()
    {
        $this->register(new \Whoops\Provider\Silex\WhoopsServiceProvider());
    }

    /**
     * @param string $role
     * @param bool   $throwException
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @return bool
     */
    public function isGranted( $role , $throwException = false )
    {
        if( $this['security']->isGranted( 'ROLE_IDDQD' ) )
        {
            return true;
        }
        else
        {
            $isGranted  =   $this['security']->isGranted( $role );

            if( $throwException && !$isGranted )
            {
                throw new AccessDeniedException();
            }

            return $isGranted;
        }
    }

    /**
     * Maps a PATCH request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Controller
     */
    public function patch($pattern, $to)
    {
        return $this['controllers']->match($pattern, $to)->method( 'PATCH' );
    }
}