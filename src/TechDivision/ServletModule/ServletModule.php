<?php

/**
 * TechDivision\ServletModule\ServletModule
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */

namespace TechDivision\ServletModule;

use TechDivision\Http\HttpProtocol;
use TechDivision\Http\HttpRequestInterface;
use TechDivision\Http\HttpResponseInterface;
use TechDivision\Storage\StackableStorage;
use TechDivision\Servlet\Http\Cookie;
use TechDivision\ServletEngine\Engine;
use TechDivision\ServletEngine\DefaultSessionSettings;
use TechDivision\ServletEngine\StandardSessionManager;
use TechDivision\ServletEngine\CoreValve;
use TechDivision\ServletEngine\ServletValve;
use TechDivision\ServletEngine\Authentication\AuthenticationValve;
use TechDivision\ServletEngine\Authentication\StandardAuthenticationManager;
use TechDivision\ServletEngine\Http\Session;
use TechDivision\ServletEngine\Http\Request;
use TechDivision\ServletEngine\Http\Response;
use TechDivision\ServletEngine\Http\HttpRequestContext;
use TechDivision\WebContainer\VirtualHost;
use TechDivision\WebServer\Dictionaries\ServerVars;
use TechDivision\WebServer\Interfaces\ModuleInterface;
use TechDivision\WebServer\Interfaces\ServerContextInterface;
use TechDivision\WebServer\Exceptions\ModuleException;
use TechDivision\ApplicationServer\Api\AppService;
use TechDivision\ApplicationServer\Api\ContainerService;
use TechDivision\ApplicationServer\Api\Node\AppNode;

/**
 * This is a servlet module that handles a servlet request.
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class ServletModule implements ModuleInterface
{
    
    /**
     * The unique module name in the web server context.
     * 
     * @var string
     */
    const MODULE_NAME = 'servlet';
    
    /**
     * The server context instance.
     * 
     * @var \TechDivision\WebServer\Interfaces\ServerContextInterface
     */
    protected $serverContext;
    
    /**
     * The servlet engine instance.
     * 
     * @var \TechDivision\ServletEngine\Engine
     */
    protected $engine;
    
    /**
     * Array that contains the initialized applications.
     * 
     * @var array
     */
    protected $applications = array();

    /**
     * Returns an array of module names which should be executed first.
     *
     * @return array The array of module names
     */
    public function getDependencies()
    {
        return array();
    }

    /**
     * Returns the module name.
     *
     * @return string The module name
     */
    public function getModuleName()
    {
        return ServletModule::MODULE_NAME;
    }

    /**
     * Initializes the module.
     *
     * @param \TechDivision\WebServer\Interfaces\ServerContextInterface $serverContext The servers context instance
     * 
     * @return void
     * @throws \TechDivision\WebServer\Exceptions\ModuleException
     */
    public function init(ServerContextInterface $serverContext)
    {
        try {
            
            // initialize the server context
            $this->serverContext = $serverContext;

            // register the class loader
            $this->registerClassLoader();
            
            $this->applications = $this->getApplications();
            
            // initialize the applications
            // $this->deployApplications();
            
            // initialize the engine
            $this->engine = new Engine();
            $this->engine->injectValves($this->getValves());
            $this->engine->init();
            
        } catch (\Exception $e) {
            throw new ModuleException($e);
        }
    }

    /**
     * Process the servlet engine.
     *
     * @param \TechDivision\Http\HttpRequestInterface  $request  The request instance
     * @param \TechDivision\Http\HttpResponseInterface $response The response instance
     * 
     * @return void
     * @throws \TechDivision\WebServer\Exceptions\ModuleException 
     */
    public function process(HttpRequestInterface $request, HttpResponseInterface $response)
    {
        
        try {
            
            // make server context local
            $serverContext = $this->getServerContext();
            
            // check if we are the handler that has to process this request
            if ($serverContext->getServerVar(ServerVars::SERVER_HANDLER) !== $this->getModuleName()) {
                return;
            }

            // make engine locally available
            $engine = $this->getEngine();

            // make the handlers locally available
            $handlers = $serverContext->getServerConfig()->getHandlers();
            
            // intialize servlet session, request + response
            $servletRequest = new Request();
            $servletRequest->injectHttpRequest($request);
            
            // transform the cookie headers into real servlet cookies
            if ($servletRequest->hasHeader(HttpProtocol::HEADER_COOKIE)) {
                
                // explode the cookie headers
                $cookieHeaders = explode('; ', $servletRequest->getHeader(HttpProtocol::HEADER_COOKIE));
                
                // create real cookie for each cookie key/value pair
                foreach ($cookieHeaders as $cookieHeader) {
                    
                    // explode the data and create a cookie instance
                    list ($name, $value) = explode('=', $cookieHeader);
                    $servletRequest->addCookie(new Cookie($name, $value));
                }
            }
            
            // get uri without querystring
            $uriWithoutQueryString = str_replace('?' . $request->getQueryString(), '', $request->getUri());
            
            // initialize the path information and the directory to start with
            list ($dirname, $basename, $extension) = array_values(pathinfo($uriWithoutQueryString));
            
            do { // descent the directory structure down to find the (almost virtual) servlet file
                
                // bingo we found a (again: almost virtual) servlet file
                if (array_key_exists(".$extension", $handlers) && $handlers[".$extension"] === $this->getModuleName()) {
                    
                    // prepare the servlet path
                    if ($dirname === '/') {
                        $servletPath = DIRECTORY_SEPARATOR . $basename;
                    } else {
                        $servletPath = $dirname . DIRECTORY_SEPARATOR . $basename;
                    }
                    
                    // we set the basename, because this is the servlet path
                    $servletRequest->setServletPath($servletPath);
                    
                    // we set the path info, what is the request URI with stripped dir- and basename
                    $servletRequest->setPathInfo(
                        $pathInfo = str_replace(
                            $servletPath,
                            '',
                            $uriWithoutQueryString
                        )
                    );
        
                    // we've found what we were looking for, so break here
                    break;
                }
            
                // descendent down the directory tree
                list ($dirname, $basename, $extension) = array_values(pathinfo($dirname));
            
            } while ($dirname !== false); // stop until we reached the root of the URI
            
            // initialize the servlet response with the Http response values
            $servletResponse = new Response();
            $servletResponse->injectHttpResponse($response);
            $servletRequest->injectResponse($servletResponse);

            // create the request context and inject it into the servlet request
            $requestContext = new HttpRequestContext();
            $requestContext->injectServerVars($serverContext->getServerVars());
            $requestContext->injectSessionManager($this->getSessionManager());
            $requestContext->injectAuthenticationManager($this->getAuthenticationManager());
            $requestContext->injectApplications($this->getApplications());
            $servletRequest->injectContext($requestContext);
            
            // let the servlet engine process the request
            $engine->process($servletRequest, $servletResponse);
            
            // transform the servlet response cookies into Http headers
            foreach ($servletResponse->getCookies() as $cookie) {
                $response->addHeader(HttpProtocol::HEADER_SET_COOKIE, $cookie->__toString());
            }
            
        } catch (\Exception $e) {
            throw new ModuleException($e);
        }
    }
    
    /**
     * Returns the servlet instance.
     * 
     * @return \TechDivision\ServletEngine\Engine The servlet engine instance
     */
    protected function getEngine()
    {
        return $this->engine;
    }
    
    /**
     * Returns the server context instance.
     * 
     * @return \TechDivision\WebServer\Interfaces\ServerContextInterface The server context instance
     */
    protected function getServerContext()
    {
        return $this->serverContext;
    }
    
    /**
     * Returns an initialized session manager instance.
     * 
     * @return \TechDivision\ServletEngine\SessionManager The session manager instance
     */
    protected function getSessionManager()
    {

        // initialize the session settings + storage
        $storage = new StackableStorage();
        $storage->injectStorage($this->getServerContext()->getContainer()->getInitialContext()->getStorage()->getStorage());

        // initialize the session manager
        $manager = new StandardSessionManager();
        $manager->injectSettings(new DefaultSessionSettings());
        $manager->injectStorage($storage);
        
        // return the initialized session manager instance
        return $manager;
    }

    /**
     * Returns the web servers base directory.
     *
     * @param string|null $directoryToAppend Append this directory to the base directory before returning it
     *
     * @return string The base directory
     */
    protected function getBaseDirectory($directoryToAppend = null)
    {
        return $this->getContainerService()->getBaseDirectory($directoryToAppend);
    }
    
    /**
     * Returns the servers document root.
     * 
     * @return string The servers document root
     */
    protected function getDocumentRoot()
    {
        return $this->getServerContext()->getServerConfig()->getDocumentRoot();
    }

    /**
     * Returns the path, relative to the base directory, containing the web applications.
     *
     * @return string The path, relative to the base directory, containing the web applications.
     */
    protected function getAppBase()
    {
        return str_replace($this->getBaseDirectory(), '', $this->getDocumentRoot());
    }
    
    /**
     * Returns the valves that handles the request.
     * 
     * @return \SplObjectStorage The valves to handle the request
     */
    protected function getValves()
    {
        $valves = new \SplObjectStorage();
        $valves->attach(new CoreValve());
        $valves->attach(new AuthenticationValve());
        $valves->attach(new ServletValve());
        return $valves;
    }
    
    /**
     * Returns the initialized applications.
     * 
     * @return array The array with the applications
     */
    protected function getApplications()
    {
        
        /*
         * Build an array with patterns as key and an array with application name and document root as value. This
         * helps to improve speed when matching an request to find the application to handle it.
         *
         * The array looks something like this:
         *
         * /^www.appserver.io(\/([a-z0-9+\$_-]\.?)+)*\/?/               => application
         * /^appserver.io(\/([a-z0-9+\$_-]\.?)+)*\/?/                   => application
         * /^appserver.local(\/([a-z0-9+\$_-]\.?)+)*\/?/                => application
         * /^neos.local(\/([a-z0-9+\$_-]\.?)+)*\/?/                     => application
         * /^neos.appserver.io(\/([a-z0-9+\$_-]\.?)+)*\/?/              => application
         * /^[a-z0-9-.]*\/neos(\/([a-z0-9+\$_-]\.?)+)*\/?/              => application
         * /^[a-z0-9-.]*\/example(\/([a-z0-9+\$_-]\.?)+)*\/?/           => application
         * /^[a-z0-9-.]*\/magento-1.8.1.0(\/([a-z0-9+\$_-]\.?)+)*\/?/   => application
         *
         * This should also match request URI's like:
         *
         * 127.0.0.1:8586/magento-1.8.1.0/index.php/admin/dashboard/index/key/8394a99f7bd5f4aca531d7c752a5fdb1/
         */
    
        $applications = array();
        
        // iterate over a applications vhost/alias configuration
        foreach ($this->getContainer()->getApplications() as $application) {
        
            // iterate over the virtual hosts
            foreach ($this->getVirtualHosts() as $virtualHost) {
        
                // check if the virtual host match the application
                if ($virtualHost->match($application)) {
        
                    // bind the virtual host to the application
                    $application->addVirtualHost($virtualHost);
                    
                    // add the application to the internal array
                    $applications = array('/^' . $virtualHost->getName() . '(\/([a-z0-9+\$_-]\.?)+)*\/?/' => $application) + $applications;
                }
            }
        
            // finally APPEND a wildcard pattern for each application to the patterns array
            $applications = $applications + array('/^[a-z0-9-.]*\/' . $application->getName() . '(\/([a-z0-9+\$_-]\.?)+)*\/?/' => $application);
        }
        
        return $applications;
    }
    
    /**
     * Returns the web servers virtual host configuration as array.
     * 
     * @return array The web servers virtual host configuration
     */
    protected function getVirtualHosts()
    {
        
        // initialize the array with the servlet engines virtual hosts
        $virtualHosts = array();
        
        // load the document root and the web servers virtual host configuration
        $documentRoot = $this->getDocumentRoot();
        
        // prepare the virtual host configurations
        foreach ($this->getServerContext()->getServerConfig()->getVirtualHosts() as $domain => $virtualHost) {
        
            // prepare the applications base directory
            $appBase = str_replace($documentRoot, '', $virtualHost['documentRoot']);
        
            // append the virtual host to the array
            $virtualHosts[] = new VirtualHost($domain, $appBase);
        }
        
        // return the array with the servlet engines virtual hosts
        return $virtualHosts;
    }
    
    /**
     * Returns the app service.
     * 
     * @return \TechDivision\ApplicationServer\Api\AppService The app service instance
     */
    protected function getAppService()
    {
        return new AppService($this->getInitialContext());
    }

    /**
     * Returns the container instance.
     *
     * @return \TechDivision\ApplicationServer\Interfaces\Container The container instance
     */
    protected function getContainer()
    {
        return $this->getServerContext()->getContainer();
    }

    /**
     * Returns the container node.
     *
     * @return \TechDivision\ApplicationServer\Api\Node\ContainerNode The container node
     */
    protected function getContainerNode()
    {
        return $this->getContainer()->getContainerNode();
    }
    
    /**
     * Returns the container service.
     * 
     * @return \TechDivision\ApplicationServer\Api\ContainerService The container service instance
     */
    protected function getContainerService()
    {
        return new ContainerService($this->getInitialContext());
    }
    
    /**
     * Returns the authentication manager.
     * 
     * @return \TechDivision\ServletEngine\Authentication\AuthenticationManager
     */
    protected function getAuthenticationManager()
    {
        return new StandardAuthenticationManager();
    }

    /**
     * Returns the inital context instance.
     *
     * @return \TechDivision\ApplicationServer\InitialContext The initial context instance
     */
    protected function getInitialContext()
    {
        return $this->getContainer()->getInitialContext();
    }
    
    /**
     * Register the class loader again, because in a thread the context 
     * lost all class loader information.
     * 
     * @return void
     */
    protected function registerClassLoader()
    {
        $this->getInitialContext()->getClassLoader()->register(true);
    }
}
