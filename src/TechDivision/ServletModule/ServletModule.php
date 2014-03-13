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
use TechDivision\ServletEngine\Application;
use TechDivision\ServletEngine\VirtualHost;
use TechDivision\ServletEngine\ServletDeployment;
use TechDivision\ServletEngine\DefaultSessionSettings;
use TechDivision\ServletEngine\StandardSessionManager;
use TechDivision\ServletEngine\Http\Session;
use TechDivision\ServletEngine\Http\Request;
use TechDivision\ServletEngine\Http\Response;
use TechDivision\ServletEngine\Http\HttpRequestContext;
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
            
            // initialize the engine
            $this->engine = new Engine();
            $this->engine->injectContainer($this->getContainer());
            $this->engine->injectApplications($this->getApplications());
            $this->engine->injectManager($this->getManager());
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
            
            // initialize the servlet request with the Http request values
            $servletRequest->setDocumentRoot($request->getDocumentRoot());
            $servletRequest->setHeaders($request->getHeaders());
            $servletRequest->setMethod($request->getMethod());
            $servletRequest->setParameterMap($request->getParams());
            $servletRequest->setQueryString($request->getQueryString());
            $servletRequest->setUri($request->getUri());
            
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
            $servletResponse->setHeaders($response->getHeaders());
            $servletResponse->setStatusCode($response->getStatusCode());
            $servletResponse->setStatusLine($response->getStatusLine());
            $servletResponse->setStatusReasonPhrase($response->getStatusReasonPhrase());

            // create the request context and inject it into the servlet request
            $requestContext = new HttpRequestContext();
            $requestContext->injectServerVars($serverContext->getServerVars());
            $servletRequest->injectContext($requestContext);
            
            // process the servlet request in a separate thread
            $process = new ServletProcess($engine, $servletRequest, $servletResponse);
            $process->start(PTHREADS_INHERIT_ALL | PTHREADS_ALLOW_HEADERS);
            $process->join();
            
            // add the content of the servlet response back to the Http response
            $response->appendBodyStream($process->getResponse()->getBodyStream());
            
            // set the response status code
            $response->setStatusCode($process->getResponse()->getStatusCode());
            
            // add the headers of the servlet response back to the Http response
            foreach ($process->getResponse()->getHeaders() as $name => $value) {
                $response->addHeader($name, $value);
            }
            
            // transform the servlet response cookies into Http headers
            foreach ($process->getResponse()->getCookies() as $cookie) {
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
    protected function getManager()
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
     * Returns the deployed application instances.
     *
     * @return array The deployed application instances
     */
    protected function getApplications()
    {

        // create a new API app service instance
        $appService = $this->getAppService();
        
        // load the initialized applications
        $applications = $this->getDeployment()->deploy()->getApplications();
        
        // iterate over the applications and register them in the system configuration
        foreach ($applications as $application) {
            
            // check if the application has already been registered
            $appNode = $appService->loadByWebappPath($application->getWebappPath());
        
            // check if the app has already been attached to the container
            if ($appNode == null) { // if not, create a new node and attach it
                $appNode = $appService->create($application);
                $appNode->setParentUuid($this->getContainerNode()->getParentUuid());
                $appService->persist($appNode);
            }
        }
        
        // return the applications
        return $applications;
    }
    
    /**
     * Returns the array with the virtual host configuration for the
     * servlet engine.
     * 
     * @return array The array with the virtual host configuration
     */
    protected function getVHosts()
    {
            
        // initialize the array with the servlet engines virtual hosts
        $vhosts = array();

        // load the document root and the web servers virtual host configuration
        $documentRoot = $this->getDocumentRoot();
        $virtualHosts = $this->getVirtualHosts();
        
        // prepare the virtual host configurations
        foreach ($virtualHosts as $domain => $virtualHost) {
            
            // prepare the applications base directory
            $appBase = str_replace($documentRoot, '', $virtualHost['documentRoot']);
            
            // append the virtual host to the array
            $vhosts[] = new VirtualHost($domain, $appBase);
        }
        
        // return the array with the servlet engines virtual hosts
        return $vhosts;
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
     * Returns the deployment instance for the container for
     * this container thread.
     *
     * @return \TechDivision\ApplicationServer\Interfaces\DeploymentInterface The deployment instance for this container thread
     */
    protected function getDeployment()
    {

        // initialize the servlet engine deployment
        $deployment = new ServletDeployment();
        $deployment->injectInitialContext($this->getInitialContext());
        $deployment->injectVirtualHosts($this->getVHosts());
        $deployment->injectBaseDirectory($this->getBaseDirectory());
        $deployment->injectAppBase($this->getAppBase());
        
        // return the initialized deployment instance
        return $deployment;
    }
    
    /**
     * Returns the web servers virtual host configuration as array.
     * 
     * @return array The web servers virtual host configuration
     */
    protected function getVirtualHosts()
    {
        return $this->getServerContext()->getServerConfig()->getVirtualHosts();
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
     * Returns the inital context instance.
     *
     * @return \TechDivision\ApplicationServer\InitialContext The initial context instance
     */
    protected function getInitialContext()
    {
        return $this->getContainer()->getInitialContext();
    }
}
