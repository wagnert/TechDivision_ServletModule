<?php

/**
 * TechDivision\ServletModule\RequestHandler
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

use TechDivision\Context\Context;
use TechDivision\ApplicationServer\Interfaces\ApplicationInterface;

/**
 * This is a request handler that is necessary to process each request of an
 * application in a separate context.
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class RequestHandler extends \Thread implements Context
{

    /**
     * The application instance we're processing requests for.
     *
     * @return \TechDivision\ApplicationServer\Interfaces\ApplicationInterface
     */
    protected $application;

    /**
     * The actual request instance we have to process.
     *
     * @return \TechDivision\Servlet\Http\HttpServletRequest
     */
    protected $servletRequest;

    /**
     * The actual response instance we have to process.
     *
     * @return \TechDivision\Servlet\Http\HttpServletResponse
     */
    protected $servletResponse;

    /**
     * Initializes the request handler with the application.
     *
     * @return \TechDivision\ApplicationServer\Interfaces\ApplicationInterface The application instance
     */
    public function __construct(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Returns the value with the passed name from the context.
     *
     * @param string $key The key of the value to return from the context.
     *
     * @return mixed The requested attribute
     */
    public function getAttribute($key)
    {
        // do nothing here, it's only to implement the Context interface
    }

    /**
     * This is the main method to handle the an incoming request.
     *
     * @return void
     */
    protected function handle($servletRequest, $servletResponse)
    {

        // set the servlet/response intances
        $this->servletRequest = $servletRequest;
        $this->servletResponse = $servletResponse;

        // start the request processing
        $this->start();
    }

    /**
     * Returns the application instance.
     *
     * @return \TechDivision\ApplicationServer\Interfaces\ApplicationInterface The application instance
     */
    protected function getApplication()
    {
        return $this->application;
    }

    /**
     * The main method that handles the thread in a separate context.
     *
     * @return void
     */
    public function run()
    {

        // reset request/response instance
        $application = $this->application;
        $servletRequest = $this->servletRequest;
        $servletResponse = $this->servletResponse;

        // initialize the class loader with the additional folders
        set_include_path(get_include_path() . PATH_SEPARATOR . $application->getWebappPath());
        set_include_path(get_include_path() . PATH_SEPARATOR . $application->getWebappPath() . DIRECTORY_SEPARATOR . 'WEB-INF' . DIRECTORY_SEPARATOR . 'classes');
        set_include_path(get_include_path() . PATH_SEPARATOR . $application->getWebappPath() . DIRECTORY_SEPARATOR . 'WEB-INF' . DIRECTORY_SEPARATOR . 'lib');

        // register the class loader again, because in a Thread the context has been lost maybe
        $application->getInitialContext()
            ->getClassLoader()
            ->register(true);

        // locate and service the servlet
        $application->getServletContext()
            ->locate($servletRequest)
            ->service($servletRequest, $servletResponse);
    }
}
