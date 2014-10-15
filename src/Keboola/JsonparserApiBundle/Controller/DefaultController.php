<?php

namespace Keboola\JsonparserApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('KeboolaJsonparserApiBundle:Default:index.html.twig', array('name' => $name));
    }
}
