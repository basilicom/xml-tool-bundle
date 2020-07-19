<?php

namespace Basilicom\XmlToolBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends FrontendController
{
    /**
     * @Route("/basilicom_tree_exporter")
     */
    public function indexAction(Request $request)
    {
        return new Response('Hello world from basilicom_tree_exporter');
    }
}
